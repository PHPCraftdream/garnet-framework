<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload {
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;

    /**
     * Framework-level pending upload manager.
     *
     * Phase 1: Upload files to temp dir, track in pending table.
     * Phase 2: Business logic calls commit() to move files to entity dir.
     *
     * Usage (Phase 1 -- in upload controller):
     *   $manager = new PendingUploadManager($app->uploadDir, $sessionId, $accountId);
     *   $pending = $manager->store($fileData, UploadRules::imagesOnly());
     *   // Returns PendingUpload with temp URL for preview
     *
     * Usage (Phase 2 -- in save controller):
     *   $committed = $manager->commit($pendingId, 'courses/42');
     *   // Moves file from temp to courses/42/, returns UploadedFileInfo
     */
    class PendingUploadManager {
        private string $tempDir;

        private string $baseDir;

        private string $sessionId;

        private int $accountId;

        public function __construct(string $uploadDir, string $sessionId, int $accountId) {
            $this->baseDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR;
            $this->tempDir = $this->baseDir . 'pending' . DIRECTORY_SEPARATOR;
            $this->sessionId = $sessionId;
            $this->accountId = $accountId;

            if (!is_dir($this->tempDir)) {
                @mkdir($this->tempDir, 0o755, true);
            }
        }

        /**
         * Store uploaded file in temp dir and record in pending table.
         */
        public function store(array $fileData, UploadRules $rules): ?PendingUpload {
            // Validate and store to temp dir
            $manager = new FileUploadManager($this->baseDir, 'pending');
            $result = $manager->storeAll($fileData, $rules);

            if (!$result->hasFiles) {
                return null;
            }

            $info = $result->files[0];
            $now = time();

            // Insert into pending table
            $id = $this->insertPending($info, $now);

            if (!$id) {
                return null;
            }

            return new PendingUpload(
                id: $id,
                sessionId: $this->sessionId,
                accountId: $this->accountId,
                storedName: $info->storedName,
                originalName: $info->originalName,
                mimeType: $info->mimeType,
                size: $info->size,
                createdAt: $now,
            );
        }

        /**
         * Commit a pending upload -- move from temp to entity directory.
         * Called by business logic when entity is saved.
         *
         * @param int    $pendingId  ID from fw_pending_uploads
         * @param string $entityDir  Target subdirectory (e.g. 'courses/42')
         * @return UploadedFileInfo|null
         */
        public function commit(int $pendingId, string $entityDir): ?UploadedFileInfo {
            $pending = $this->findPending($pendingId);

            if (!$pending) {
                return null;
            }

            // Verify ownership
            if ((int)$pending['account_id'] !== $this->accountId) {
                return null;
            }

            $srcPath = $this->tempDir . basename($pending['stored_name']);

            if (!is_file($srcPath)) {
                return null;
            }

            // Create target directory
            $targetDir = $this->baseDir . trim($entityDir, '/\\') . DIRECTORY_SEPARATOR;

            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0o755, true);
            }

            $targetPath = $targetDir . basename($pending['stored_name']);

            if (!rename($srcPath, $targetPath)) {
                return null;
            }

            // Remove from pending table
            $this->deletePending($pendingId);

            return new UploadedFileInfo(
                storedName: $pending['stored_name'],
                originalName: $pending['original_name'],
                mimeType: $pending['mime_type'],
                size: (int)$pending['size'],
                subDir: trim($entityDir, '/\\'),
            );
        }

        /**
         * Get pending upload info (for preview/download).
         * Only returns if owned by current account.
         */
        public function getPending(int $pendingId): ?PendingUpload {
            $row = $this->findPending($pendingId);

            if (!$row || (int)$row['account_id'] !== $this->accountId) {
                return null;
            }

            return new PendingUpload(
                id: (int)$row['id'],
                sessionId: $row['session_id'],
                accountId: (int)$row['account_id'],
                storedName: $row['stored_name'],
                originalName: $row['original_name'],
                mimeType: $row['mime_type'],
                size: (int)$row['size'],
                createdAt: (int)$row['created_at'],
            );
        }

        /**
         * Serve a pending file (for preview before save).
         * Only accessible by the upload owner.
         */
        public function servePending(int $pendingId): mixed {
            $pending = $this->getPending($pendingId);

            if (!$pending) {
                return ControllerTools::JSON(['error' => 'Not found'], status: 404);
            }

            return SecureFileServing::serve(
                uploadDir: $this->baseDir,
                subDir: 'pending',
                storedName: $pending->storedName,
                displayName: $pending->originalName,
                accessCheck: fn () => true, // already verified ownership above
            );
        }

        /**
         * Delete a pending upload and its file from disk.
         * Called when user removes a file before saving.
         */
        public function removePending(int $pendingId): bool {
            $row = $this->findPending($pendingId);

            if (!$row || (int)$row['account_id'] !== $this->accountId) {
                return false;
            }

            // Delete file from temp dir
            $path = $this->tempDir . basename($row['stored_name']);

            if (is_file($path)) {
                @unlink($path);
            }

            $this->deletePending($pendingId);

            return true;
        }

        /**
         * Delete expired pending uploads (call from cron).
         * @param int $maxAgeSeconds Default: 24 hours
         */
        public static function cleanupExpired(string $uploadDir, int $maxAgeSeconds = 86400): int {
            $tempDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . 'pending' . DIRECTORY_SEPARATOR;
            $cutoff = time() - $maxAgeSeconds;
            $deleted = 0;

            $table = PendingUploadsTable::get();
            $expired = $table->selectAll(function ($q) use ($cutoff): void {
                $q->where('`created_at` < ?', [$cutoff]);
            });

            foreach ($expired as $row) {
                $path = $tempDir . basename($row['stored_name']);

                if (is_file($path)) {
                    @unlink($path);
                }
                $table->deleteById((int)$row['id']);
                $deleted++;
            }

            return $deleted;
        }

        // -- DB helpers (using PendingUploadsTable) --------

        private function insertPending(UploadedFileInfo $info, int $now): int {
            $id = PendingUploadsTable::get()->insert([
                'session_id' => $this->sessionId,
                'account_id' => $this->accountId,
                'stored_name' => $info->storedName,
                'original_name' => $info->originalName,
                'mime_type' => $info->mimeType,
                'size' => $info->size,
                'created_at' => $now,
            ]);

            return $id !== false ? (int)$id : 0;
        }

        private function findPending(int $id): ?array {
            return PendingUploadsTable::get()->selectById($id);
        }

        private function deletePending(int $id): void {
            PendingUploadsTable::get()->deleteById($id);
        }
    }
}

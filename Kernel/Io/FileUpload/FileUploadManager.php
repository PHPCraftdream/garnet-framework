<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload {
    use finfo;
    use PHPCraftdream\Garnet\Kernel\Io\Router\Mime;

    /**
     * Reusable file upload manager.
     *
     * Handles validation, safe storage, and deletion of uploaded files.
     * Framework-level — no business logic. Modules (support, IM, comments)
     * use this class and add their own access control on top.
     *
     * Usage:
     *   $manager = new FileUploadManager($app->uploadDir, 'support');
     *   $results = $manager->storeAll($_FILES['attachments'], UploadRules::documentsAndImages());
     */
    class FileUploadManager {
        protected string $baseDir;

        /**
         * @param string $uploadDir  App-level upload directory (BaseAppInit::uploadDir)
         * @param string $subDir     Module subdirectory (e.g. 'support', 'im', 'comments')
         */
        public function __construct(string $uploadDir, string $subDir = '') {
            $this->baseDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR;

            if ($subDir !== '') {
                $this->baseDir .= trim($subDir, '/\\') . DIRECTORY_SEPARATOR;
            }

            if (!is_dir($this->baseDir)) {
                @mkdir($this->baseDir, 0o755, true);
            }
        }

        /**
         * Store multiple uploaded files from $_FILES array.
         *
         * Accepts both single file and multi-file formats:
         *   $_FILES['file'] — single file
         *   $_FILES['files'] — array of files (name/tmp_name/etc are arrays)
         *
         * @param array       $filesArray  Single entry from $_FILES (e.g. $_FILES['attachments'])
         * @param UploadRules $rules       Validation rules
         * @return UploadResult            Contains stored files and/or errors
         */
        public function storeAll(array $filesArray, UploadRules $rules): UploadResult {
            $normalized = self::normalizeFiles($filesArray);

            if (count($normalized) > $rules->maxFilesCount) {
                return UploadResult::error("Too many files (max {$rules->maxFilesCount})");
            }

            $stored = [];
            $errors = [];

            foreach ($normalized as $i => $file) {
                $error = $this->validateFile($file, $rules);

                if ($error !== null) {
                    $errors[] = "File #{$i} ({$file['name']}): {$error}";

                    continue;
                }

                $info = $this->storeSingle($file);

                if ($info === null) {
                    $errors[] = "File #{$i} ({$file['name']}): failed to store";

                    continue;
                }

                $stored[] = $info;
            }

            return new UploadResult($stored, $errors);
        }

        /**
         * Store a single file from a normalized file entry.
         */
        public function storeSingle(array $file): ?UploadedFileInfo {
            $originalName = basename($file['name']);
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $storedName = bin2hex(random_bytes(16)) . ($ext ? ".{$ext}" : '');
            $destPath = $this->baseDir . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                return null;
            }

            $mimeType = Mime::getFileMime($originalName) ?? 'application/octet-stream';

            return new UploadedFileInfo(
                storedName: $storedName,
                originalName: $originalName,
                mimeType: $mimeType,
                size: (int)$file['size'],
                subDir: basename(rtrim($this->baseDir, '/\\')),
            );
        }

        /**
         * Delete a stored file.
         */
        public function delete(string $storedName): bool {
            // Prevent path traversal
            $safe = basename($storedName);
            $path = $this->baseDir . $safe;

            if (is_file($path)) {
                return unlink($path);
            }

            return false;
        }

        /**
         * Get the full filesystem path for a stored file.
         */
        public function getPath(string $storedName): string {
            return $this->baseDir . basename($storedName);
        }

        /**
         * Check if a stored file exists.
         */
        public function exists(string $storedName): bool {
            return is_file($this->baseDir . basename($storedName));
        }

        // ── Validation ───────────────────────────────────────────────

        protected function validateFile(array $file, UploadRules $rules): ?string {
            // PHP upload error
            if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
                return 'Upload error: ' . ($file['error'] ?? 'unknown');
            }

            // Empty file
            if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                return 'Invalid upload';
            }

            // Size check
            $size = (int)($file['size'] ?? 0);

            if ($size > $rules->maxFileSize) {
                $maxMb = round($rules->maxFileSize / 1024 / 1024, 1);

                return "File too large (max {$maxMb}MB)";
            }

            // Extension check
            $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

            if (!empty($rules->allowedExtensions) && !in_array($ext, $rules->allowedExtensions, true)) {
                return "File type not allowed: .{$ext}";
            }

            // MIME type check (use finfo for real MIME, not client-reported)
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($file['tmp_name']);

            if ($realMime !== false && !empty($rules->allowedTypes)) {
                $allowed = false;

                foreach ($rules->allowedTypes as $type) {
                    if ($realMime === $type || str_starts_with($realMime, rtrim($type, '/') . '/')) {
                        $allowed = true;

                        break;
                    }
                }

                if (!$allowed) {
                    return "MIME type not allowed: {$realMime}";
                }
            }

            return null;
        }

        // ── Helpers ──────────────────────────────────────────────────

        /**
         * Normalize PHP's $_FILES array into a flat array of file entries.
         * Handles both single-file and multi-file uploads.
         */
        protected static function normalizeFiles(array $filesEntry): array {
            if (!isset($filesEntry['name'])) {
                return [];
            }

            // Single file: name is string
            if (is_string($filesEntry['name'])) {
                if (empty($filesEntry['tmp_name'])) {
                    return [];
                }

                return [$filesEntry];
            }

            // Multi file: name is array
            $result = [];

            foreach ($filesEntry['name'] as $i => $name) {
                if (empty($filesEntry['tmp_name'][$i])) {
                    continue;
                }
                $result[] = [
                    'name' => $name,
                    'type' => $filesEntry['type'][$i] ?? '',
                    'tmp_name' => $filesEntry['tmp_name'][$i],
                    'error' => $filesEntry['error'][$i] ?? 0,
                    'size' => $filesEntry['size'][$i] ?? 0,
                ];
            }

            return $result;
        }
    }
}

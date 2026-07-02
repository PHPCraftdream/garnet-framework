<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload {
    /**
     * Abstract base for entity-specific upload managers.
     *
     * Extends the pending upload pattern: when a business entity is saved,
     * pending uploads are committed (moved from temp to entity dir) and
     * recorded in a business-level table.
     *
     * Subclass pattern:
     *   class CourseUploadManager extends EntityUploadManager {
     *       protected function getEntityDir(int $entityId): string { return "courses/{$entityId}"; }
     *       protected function recordAttachment(int $entityId, UploadedFileInfo $file): int { ... }
     *   }
     */
    abstract class EntityUploadManager {
        /**
         * Return the subdirectory path for the entity.
         * Example: "courses/42", "lessons/7".
         */
        abstract protected function getEntityDir(int $entityId): string;

        /**
         * Record the committed file in the business-level table.
         * Return the new attachment row ID.
         */
        abstract protected function recordAttachment(int $entityId, UploadedFileInfo $file): int;

        /**
         * Commit a pending upload to an entity.
         * Moves the file from temp dir to entity dir, records in business table.
         *
         * @param int    $pendingId   Pending upload ID
         * @param int    $entityId    Business entity ID (course, lesson, etc.)
         * @param string $uploadDir   Base upload directory (BaseAppInit::uploadDir)
         * @param string $sessionId   Current session identifier
         * @param int    $accountId   Current user account ID
         * @return array{file: UploadedFileInfo, attachmentId: int}|null
         */
        public function commitPendingToEntity(
            int $pendingId,
            int $entityId,
            string $uploadDir,
            string $sessionId,
            int $accountId,
        ): ?array {
            $manager = new PendingUploadManager($uploadDir, $sessionId, $accountId);
            $file = $manager->commit($pendingId, $this->getEntityDir($entityId));

            if (!$file) {
                return null;
            }

            $attachmentId = $this->recordAttachment($entityId, $file);

            return ['file' => $file, 'attachmentId' => $attachmentId];
        }

        /**
         * Commit multiple pending uploads to an entity in one call.
         *
         * @param int[]  $pendingIds  Array of pending upload IDs
         * @param int    $entityId    Business entity ID
         * @param string $uploadDir   Base upload directory
         * @param string $sessionId   Current session identifier
         * @param int    $accountId   Current user account ID
         * @return array{file: UploadedFileInfo, attachmentId: int}[]
         */
        public function commitAllToEntity(
            array $pendingIds,
            int $entityId,
            string $uploadDir,
            string $sessionId,
            int $accountId,
        ): array {
            $results = [];

            foreach ($pendingIds as $pendingId) {
                $result = $this->commitPendingToEntity(
                    (int)$pendingId,
                    $entityId,
                    $uploadDir,
                    $sessionId,
                    $accountId,
                );

                if ($result) {
                    $results[] = $result;
                }
            }

            return $results;
        }
    }
}

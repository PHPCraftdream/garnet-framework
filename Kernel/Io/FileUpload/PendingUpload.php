<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload {
    /**
     * Pending upload record — file uploaded but not yet attached to an entity.
     * Stored in temp dir, tracked in fw_pending_uploads table.
     * Automatically cleaned after 24h if not committed.
     */
    final class PendingUpload {
        public function __construct(
            public readonly int $id,
            public readonly string $sessionId,
            public readonly int $accountId,
            public readonly string $storedName,
            public readonly string $originalName,
            public readonly string $mimeType,
            public readonly int $size,
            public readonly int $createdAt,
        ) {
        }
    }
}

<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload {
    /**
     * Immutable value object describing a successfully stored file.
     * Returned by FileUploadManager::store().
     */
    final class UploadedFileInfo {
        public function __construct(
            public readonly string $storedName,
            public readonly string $originalName,
            public readonly string $mimeType,
            public readonly int $size,
            public readonly string $subDir,
        ) {
        }
    }
}

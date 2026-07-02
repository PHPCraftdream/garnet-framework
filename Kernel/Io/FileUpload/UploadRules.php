<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload {
    /**
     * Validation rules for file uploads.
     * Reusable across modules — support tickets, IM messages, comments, etc.
     */
    final class UploadRules {
        /** @var string[] Allowed MIME type prefixes (e.g. 'image/', 'application/pdf') */
        public readonly array $allowedTypes;

        /** @var string[] Allowed file extensions (lowercase, no dot) */
        public readonly array $allowedExtensions;

        public function __construct(
            public readonly int $maxFileSize = 5 * 1024 * 1024,  // 5 MB
            public readonly int $maxFilesCount = 5,
            array $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'],
            array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'log'],
        ) {
            $this->allowedTypes = $allowedTypes;
            $this->allowedExtensions = $allowedExtensions;
        }

        /**
         * Preset: images only (for avatars, screenshots).
         */
        public static function imagesOnly(int $maxSize = 5 * 1024 * 1024): self {
            return new self(
                maxFileSize: $maxSize,
                maxFilesCount: 10,
                allowedTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                allowedExtensions: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            );
        }

        /**
         * Preset: documents + images (for support tickets, comments).
         */
        public static function documentsAndImages(int $maxSize = 5 * 1024 * 1024): self {
            return new self(maxFileSize: $maxSize);
        }

        /**
         * Preset: lesson materials — broad document support (PDF, Office, EPUB, text, images).
         */
        public static function lessonMaterials(int $maxSize = 20 * 1024 * 1024): self {
            return new self(
                maxFileSize: $maxSize,
                maxFilesCount: 1,
                allowedTypes: [
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'application/vnd.ms-powerpoint',
                    'application/epub+zip',
                    'text/plain',
                    'text/markdown',
                    'application/vnd.oasis.opendocument.text',
                    'application/vnd.oasis.opendocument.spreadsheet',
                    'application/vnd.oasis.opendocument.presentation',
                ],
                allowedExtensions: [
                    'jpg', 'jpeg', 'png', 'gif', 'webp',
                    'pdf',
                    'doc', 'docx',
                    'xls', 'xlsx',
                    'ppt', 'pptx',
                    'epub',
                    'txt', 'md',
                    'odt', 'ods', 'odp',
                ],
            );
        }
    }
}

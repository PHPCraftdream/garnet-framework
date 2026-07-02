<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\FileUpload {
    /**
     * Result of a batch file upload operation.
     * Contains successfully stored files and any validation errors.
     */
    final class UploadResult {
        /** @var UploadedFileInfo[] */
        public readonly array $files;

        /** @var string[] */
        public readonly array $errors;

        public readonly bool $hasErrors;

        public readonly bool $hasFiles;

        /**
         * @param UploadedFileInfo[] $files
         * @param string[]           $errors
         */
        public function __construct(array $files = [], array $errors = []) {
            $this->files = $files;
            $this->errors = $errors;
            $this->hasErrors = !empty($errors);
            $this->hasFiles = !empty($files);
        }

        public static function error(string $message): self {
            return new self([], [$message]);
        }

        public static function empty(): self {
            return new self();
        }
    }
}

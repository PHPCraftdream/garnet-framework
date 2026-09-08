<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Forms {
    class ImageUploadParams {
        public function __construct(
            public readonly string $uploadDir,
            public readonly string $fileNameField,
            public readonly ?string $uploadTmpFile = null,
            public readonly ?string $prevFileName = null,
            public readonly ?ImageCropParams $cropParams = null,
            /**
             * PHP's own verdict on the upload (`$_FILES[...]['error']`).
             *
             * Needed because "no file arrived" is ambiguous: it is what a
             * removal looks like, and it is also what an upload PHP refused —
             * one over `upload_max_filesize`, say — looks like. Without this,
             * a photo too big to accept was read as a photo the person asked
             * to delete, and the stored one was removed.
             */
            public readonly int $uploadError = UPLOAD_ERR_OK,
        ) {
        }
    }
}

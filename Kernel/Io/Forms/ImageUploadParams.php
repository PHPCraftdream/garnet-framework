<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Forms {
    class ImageUploadParams {
        public function __construct(
            public readonly string $uploadDir,
            public readonly string $fileNameField,
            public readonly ?string $uploadTmpFile = null,
            public readonly ?string $prevFileName = null,
            public readonly ?ImageCropParams $cropParams = null,
        ) {
        }
    }
}

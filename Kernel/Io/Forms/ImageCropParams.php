<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Forms {
    class ImageCropParams {
        public function __construct(
            public readonly string $fileNameField,
            public readonly string $infoField,
            public readonly ?ImageCrop $imgCrop = null,
            public readonly ?ImageCrop $prevImgCrop = null,
            public readonly ?string $prevFileName = null,
        ) {
        }
    }
}

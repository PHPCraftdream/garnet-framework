<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity {
    class SaveFilesParams {
        public function __construct(
            public readonly array $files,
            public readonly string $baseDir,
            public readonly array $prevData = [],
        ) {
        }

        public static function make(array $files, string $baseDir, array $prevData = []): SaveFilesParams {
            return new static($files, $baseDir, $prevData);
        }
    }
}

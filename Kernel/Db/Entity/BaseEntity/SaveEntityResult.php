<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity {
    use PHPCraftdream\Garnet\Kernel\Io\Forms\Updater;

    class SaveEntityResult {
        public function __construct(
            public readonly Updater $update,
            public readonly ?Updater $addData = null
        ) {
        }

        public function getParams(): Updater {
            return $this->update;
        }

        public function getAddData(): ?Updater {
            return $this->addData;
        }
    }
}

<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Db;

use PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity\SaveEntityResult;
use PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity\SaveFilesParams;

interface IEntityConfig {
    public static function getEntityConfig(): IEntityConfig;

    public function idField(): string;

    public function getFieldsInfo(array $fields = null): array;

    public function getGridInfo(): array;

    public function selectFields(): array;

    public function manageFormFields(): array;

    public function manageGridFields(): array;

    public function viewFields(): array;

    public function editFields(): array;

    public function dataFields(): array;

    public function patchItem(array &$item): array;

    public function saveOne(array $postData, array $fields, ?SaveFilesParams $saveFiles = null): SaveEntityResult;
}

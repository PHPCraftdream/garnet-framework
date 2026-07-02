<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Db;

interface IDbM2M extends IDbTable {
    public function getTable1(): string;

    public function getEntityName1(): string;

    public function getKey1(): string;

    public function getTable2(): string;

    public function getEntityName2(): string;

    public function getKey2(): string;

    public function getTableName(): string;

    public function getPrimaryKey(): string;

    public function createLinks(array|int $ids1, array|int $ids2, int $value = 1): bool;

    public function deleteLinks(array|int $ids1, array|int $ids2): bool;

    public function updateKey1Links(int $idKey1, array|int $ids2, int $value = 1): bool;

    public function updateKey2Links(int $idKey1, array|int $ids2, int $value = 1): bool;
}

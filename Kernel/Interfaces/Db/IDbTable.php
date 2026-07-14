<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Db;

use Aura\SqlQuery\Common\DeleteInterface;
use Aura\SqlQuery\Common\InsertInterface;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\Common\UpdateInterface;
use Closure;
use PHPCraftdream\Garnet\Kernel\Db\Query\QueryEx;
use PHPCraftdream\Garnet\Kernel\Db\Tables\PageData;

interface IDbTable {
    public function dropTable(): bool;

    public function getPrimaryKey(): string;

    public function getTableName(): string;

    public function getEntityName(): string;

    public function getPageSize(): int;

    public function getQueryEx(): QueryEx;

    public function newSelect(): SelectInterface;

    public function newInsert(): InsertInterface;

    public function newUpdate(): UpdateInterface;

    public function newDelete(): DeleteInterface;

    public function getCountAsync(?callable $queryCallback = null, ?callable $callback = null): void;

    public function getCount(?callable $queryCallback = null): int;

    public function existsByIdAsync(string|int $id, ?callable $callback = null): void;

    public function existsById(string|int $id): bool;

    public function selectAllAsync(?Closure $queryCallback = null, ?callable $callback = null): IDbMySQLiLink;

    public function selectAll(?Closure $queryCallback = null): array;

    public function selectPageAsync(int $page, ?Closure $queryCallback = null, ?callable $callback = null): IDbMySQLiLink;

    public function selectPage(int $page, ?Closure $queryCallback = null): PageData;

    public function selectByFieldAsync(
        string $field,
        int|string|array $value,
        ?Closure $queryCallback = null,
        ?callable $callback = null
    ): IDbMySQLiLink;

    public function selectByField(string $field, int|string|array $value, ?Closure $queryCallback = null): array;

    public function simpleSelectByFieldAsync(
        string $field,
        int|float|string $value,
        ?callable $callback = null,
    ): IDbMySQLiLink;

    public function simpleSelectByField(string $field, int|float|string $value): ?array;

    public function selectOneByFieldAsync(
        string $field,
        int|string|array $value,
        ?Closure $queryCallback = null,
        ?callable $callback = null,
    ): IDbMySQLiLink;

    public function selectOneByField(string $field, int|string|array $value, ?Closure $queryCallback = null): ?array;

    public function simpleSelectOneByFieldAsync(
        string $field,
        int|float|string $value,
        ?callable $callback = null,
    ): IDbMySQLiLink;

    public function simpleSelectOneByField(string $field, int|float|string $value): ?array;

    public function selectByIdsAsync(
        int|string|array $ids,
        ?Closure $queryCallback = null,
        ?callable $callback = null
    ): IDbMySQLiLink;

    public function selectByIds(int|string|array $ids, ?Closure $queryCallback = null): array;

    public function selectById(int|string $id, ?Closure $queryCallback = null): ?array;

    public function selectByIdAsync(int|string $id, ?Closure $queryCallback = null, ?callable $callback = null): IDbMySQLiLink;

    public function insertAsync(array $data, ?Closure $queryCallback = null, ?callable $callback = null): IDbMySQLiLink;

    public function insert(array $data, ?Closure $queryCallback = null): false|string;

    public function insertBatchAsync(array $queryData, ?string $onConflict = null, ?callable $callback = null): IDbMySQLiLink;

    public function insertBatch(array $queryData, ?string $onConflict = null): bool;

    public function updateByAsync(array $updateData, Closure $queryCallback, ?callable $callback = null): IDbMySQLiLink;

    public function updateBy(array $updateData, Closure $queryCallback): bool;

    public function updateByIdAsync(array $updateData, int|string|array $id, ?callable $callback = null): IDbMySQLiLink;

    public function updateById(array $updateData, int|string|array $id): bool;

    public function updateByFieldAsync(
        array $updateData,
        string $field,
        int|string|array $value,
        ?callable $callback = null
    ): IDbMySQLiLink;

    public function updateByField(array $updateData, string $field, int|string|array $value): bool;

    public function deleteByAsync(Closure $queryCallback, ?callable $callback = null): IDbMySQLiLink;

    public function deleteBy(Closure $queryCallback): bool;

    public function deleteByIdAsync(int|string|array $id, ?callable $callback = null): IDbMySQLiLink;

    public function deleteById(int|string|array $id): bool;

    public function deleteByFieldAsync(string $field, int|string|array $value, ?callable $callback = null): IDbMySQLiLink;

    public function deleteByField(string $field, int|string|array $value): bool;
}

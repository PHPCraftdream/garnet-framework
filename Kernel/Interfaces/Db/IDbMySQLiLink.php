<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Db;

use MySQLi;

interface IDbMySQLiLink {
    public function getMysqli(): MySQLi;

    public function getLastAffectedRows(): int;

    public function isBusy(): bool;

    public function queryAsync(string $sql, callable $callBack = null): IDbMySQLiLink;

    public function query(string $sql, array $params = []): array|int|string|bool;

    public function poll(): array|int|string|bool|null;
}

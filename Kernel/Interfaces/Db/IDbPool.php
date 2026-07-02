<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Db;

use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
use PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

interface IDbPool {
    /**
     * @return IDbMySQLiLink
     * @throws IniConfigException
     */
    public function newLink(): IDbMySQLiLink;

    /**
     * @return IniConfig
     * @throws IniConfigException
     */
    public function getDbConfig(): IniConfig;

    /**
     * @param string $sql
     * @param array $args
     * @param callable|null $callBack
     * @return IDbMySQLiLink
     * @throws DbException
     */
    public function queryAsync(string $sql, array $args = [], callable $callBack = null): IDbMySQLiLink;

    /**
     * @param string $sql
     * @param array $args
     * @return array|int|string|bool
     * @throws DbException
     */
    public function query(string $sql, array $args = []): array|int|string|bool;

    public function poll(): void;

    public function pollFinishAll(): void;

    public function getLinksCount(): int;
}

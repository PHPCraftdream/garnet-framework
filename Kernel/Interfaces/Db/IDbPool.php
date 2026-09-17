<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Db;

use PHPCraftdream\Garnet\Kernel\Exceptions\Db\DbException;
use PHPCraftdream\Garnet\Kernel\Exceptions\Io\IniConfigException;
use PHPCraftdream\Garnet\Kernel\Io\Services\IniConfig\IniConfig;

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
    public function queryAsync(string $sql, array $args = [], ?callable $callBack = null): IDbMySQLiLink;

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

    /**
     * Сколько запросов пул отправил с начала процесса. Монотонный
     * счётчик: стоимость одного запроса — это разница двух чтений.
     */
    public function getQueryCount(): int;
}

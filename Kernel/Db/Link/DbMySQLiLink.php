<?php
declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link {
    use Closure;
    use MySQLi;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
    use Throwable;

    class DbMySQLiLink implements IDbMySQLiLink {
        protected static int $idCounter = 1;

        protected int $id = 0;

        protected string|null $sql = null;

        protected bool $busy = false;

        protected Closure|null $callBack = null;

        protected int $lastAffectedRows = 0;

        /**
         * mysqli::$insert_id is a property of the connection, not of the
         * last-executed statement -- it keeps whatever value the most
         * recent INSERT/REPLACE produced until another INSERT/REPLACE
         * changes it. On a pooled connection, surfacing it for every
         * non-row-returning result (including UPDATE/DELETE) leaks that
         * stale id instead of the statement's own outcome. Only
         * INSERT/REPLACE are expected to report an insert id here.
         */
        protected function resolveNonRowResult(string $sql, bool|int|string $result): bool|int|string {
            $firstWord = strtoupper((string)strtok(ltrim($sql), " \t\n\r"));

            if (in_array($firstWord, ['INSERT', 'REPLACE'], true) && $this->link->insert_id) {
                return intval($this->link->insert_id);
            }

            return $result;
        }

        public function __construct(protected MySQLi $link) {
            $this->id = DbMySQLiLink::$idCounter;
            DbMySQLiLink::$idCounter += 1;
        }

        public function getMysqli(): MySQLi {
            return $this->link;
        }

        public function getLastAffectedRows(): int {
            return $this->lastAffectedRows;
        }

        public function isBusy(): bool {
            return $this->busy;
        }

        public function getId(): int {
            return $this->id;
        }

        public function queryAsync(string $sql, ?callable $callBack = null): IDbMySQLiLink {
            if ($this->busy) {
                throw new DbException('Link is busy');
            }

            $this->sql = $sql;
            $this->callBack = $callBack ? $callBack(...) : null;

            // Logger::get(Logger::SYSTEM_LOGGER)->append('sql', $sql);

            if (!$this->link->query($sql, MYSQLI_ASYNC)) {
                $this->sql = null;
                $this->callBack = null;

                throw new DbException("Failed to dispatch async query: [{$sql}]");
            }

            $this->busy = true;

            return $this;
        }

        public function query(string $sql, array $params = []): array|int|string|bool {
            if ($this->busy) {
                throw new DbException('Link is busy');
            }

            $this->busy = true;

            try {
                $stmt = $this->link->prepare($sql);
                $stmt->execute($params);
                $this->lastAffectedRows = (int)$stmt->affected_rows;
                $result = $stmt->get_result();

                if (!is_object($result)) {
                    $res = $this->resolveNonRowResult($sql, $result);

                    $this->busy = false;

                    return $res;
                }

                $data = $result->fetch_all(MYSQLI_ASSOC);
                $this->busy = false;

                return $data;
            } catch (Throwable $e) {
                $this->busy = false;

                throw new DbException($e->getMessage() . "\n on query: [{$sql}]\n", $e->getCode(), $e);
            }
        }

        public function poll(): array|int|string|bool|null {
            if (!$this->busy) {
                return null;
            }

            $links = [$this->link];
            $errors = [$this->link];
            $reject = [$this->link];

            if (!mysqli_poll($links, $errors, $reject, 0)) {
                return null;
            }

            $data = [];

            try {
                $polledSql = (string)$this->sql;
                $result = $this->link->reap_async_query();
                $this->sql = null;

                if (!is_object($result)) {
                    $this->lastAffectedRows = (int)$this->link->affected_rows;
                    $res = $this->resolveNonRowResult($polledSql, $result);

                    /* @phpstan-ignore-next-line */
                    if (!empty($this->callBack)) {
                        $callBack = $this->callBack;
                        $callBack($res);
                        $this->callBack = null;
                    }

                    $this->busy = false;

                    return $res;
                }

                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }

                if (!empty($this->callBack)) {
                    $callBack = $this->callBack;
                    $callBack($data);
                    $this->callBack = null;
                }

                $this->busy = false;

                return $data;
            } catch (Throwable $e) {
                $sql = $this->sql;
                $this->sql = null;
                $this->callBack = null;
                $this->busy = false;

                throw new DbException($e->getMessage() . "\n on query: [{$sql}]\n", $e->getCode(), $e);
            }
        }
    }
}

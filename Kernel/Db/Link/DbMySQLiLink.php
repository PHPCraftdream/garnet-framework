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

            $this->busy = true;
            $this->sql = $sql;

            // Logger::get(Logger::SYSTEM_LOGGER)->append('sql', $sql);

            $this->link->query($sql, MYSQLI_ASYNC);

            if ($callBack) {
                $this->callBack = $callBack(...);
            }

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
                    $res = $result;

                    if ($this->link->insert_id) {
                        $res = intval($this->link->insert_id);
                    }

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
                $result = $this->link->reap_async_query();
                $this->sql = null;

                if (!is_object($result)) {
                    $res = $result;

                    if ($this->link->insert_id) {
                        $res = intval($this->link->insert_id);
                    }

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
                $this->busy = false;

                throw new DbException($e->getMessage() . "\n on query: [{$sql}]\n", $e->getCode(), $e);
            }
        }
    }
}

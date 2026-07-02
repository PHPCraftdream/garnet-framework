<?php
declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link {
    use PHPCraftdream\Garnet\Kernel\Core\Benchmark\BenchmarkLog;
    use PHPCraftdream\Garnet\Kernel\Db\Query\QueryTools;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbPool;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

    class DbPool implements IDbPool {
        protected static ?DbPool $instance = null;

        protected function __construct() {
        }

        public static function get(): IDbPool {
            if (empty(static::$instance)) {
                $item = new static();
                static::$instance = $item;
            }

            return static::$instance;
        }

        public static function pollLinks(array &$links, bool $finishAll = true): void {
            // Batch all busy mysqli handles into a single kernel-side
            // mysqli_poll() with a 50ms upper-bound. Two reasons:
            //
            //  1. The previous loop slept via usleep(1000), but Windows'
            //     timer quantum is 15.6ms, so the "1ms nap" was really
            //     ~15ms on every iteration of every async query — and a
            //     typical request finished 40+ poll cycles. This was the
            //     dominant single cost in the benchmark log (1.0s avg
            //     for sessioned requests).
            //  2. Batching across all busy links means one syscall serves
            //     N pending queries instead of N separate poll(0) calls.
            //
            // mysqli_poll() with $microseconds > 0 blocks in select()/
            // WSAPoll(), which uses the OS scheduler — no user-space
            // sleep involved.
            while (true) {
                $busyMysqli = [];

                foreach ($links as $link) {
                    if ($link->isBusy()) {
                        $busyMysqli[] = $link->getMysqli();
                    }
                }

                if (empty($busyMysqli)) {
                    $links = [];

                    return;
                }

                $reads = $errors = $reject = $busyMysqli;
                mysqli_poll($reads, $errors, $reject, 0, 50000);

                foreach ($links as $link) {
                    if ($link->isBusy()) {
                        $link->poll();
                    }
                }

                $links = array_values(array_filter($links, fn ($l) => $l->isBusy()));

                if (empty($links)) {
                    return;
                }

                if (!$finishAll) {
                    return;
                }
            }
        }

        /**
         * @return IniConfig
         * @throws IniConfigException
         */
        public function getDbConfig(): IniConfig {
            /** @var IniConfig */
            return IniConfig::db();
        }

        protected array $links = [];

        /**
         * @return IDbMySQLiLink
         * @throws IniConfigException
         */
        public function newLink(): IDbMySQLiLink {
            $config = IniConfig::db();

            $host = $config->param('host');
            $user = $config->param('user');
            $password = $config->param('password');
            $dbname = $config->param('dbname');
            $port = (int)$config->param('port');

            $mysqli = mysqli_connect(
                hostname: $host,
                username: $user,
                password: $password,
                database: $dbname,
                port: $port,
            );

            $options = $config->param('options');
            $initCmd = $options['MYSQL_ATTR_INIT_COMMAND'] ?? null;

            if ($initCmd) {
                $mysqli->real_query($initCmd);
            }

            $link = new DbMySQLiLink($mysqli);
            $this->links[] = $link;

            return $link;
        }

        /**
         * @return DbMySQLiLink
         * @throws IniConfigException
         */
        protected function getLink(): IDbMySQLiLink {
            if (empty($this->links)) {
                /** @var DbMySQLiLink */
                return $this->newLink();
            }

            foreach ($this->links as $myLink) {
                if (!$myLink->isBusy()) {
                    /** @var DbMySQLiLink */
                    return $myLink;
                }

                $myLink->poll();
            }

            foreach ($this->links as $myLink) {
                $myLink->poll();

                if (!$myLink->isBusy()) {
                    /** @var DbMySQLiLink */
                    return $myLink;
                }
            }

            /** @var DbMySQLiLink */
            return $this->newLink();
        }

        /**
         * @param string $sql
         * @param array $args
         * @param callable|null $callBack
         * @return IDbMySQLiLink
         * @throws DbException
         */
        public function queryAsync(string $sql, array $args = [], callable $callBack = null): IDbMySQLiLink {
            $newSql = empty($args) ? $sql : QueryTools::buildSql($sql, $args);

            return $this->getLink()->queryAsync($newSql, $callBack);
        }

        /**
         * @param string $sql
         * @param array $args
         * @return array|int|string|bool
         * @throws DbException
         */
        public function query(string $sql, array $args = []): array|int|string|bool {
            return $this->getLink()->query($sql, $args);
        }

        /**
         * @return void
         */
        public function poll(): void {
            foreach ($this->links as $myLink) {
                $myLink->poll();
            }
        }

        /**
         * @return void
         */
        public function pollFinishAll(): void {
            // See pollLinks() above for the rationale — same usleep ->
            // mysqli_poll() switch, applied to the pool's own link set.
            while (true) {
                BenchmarkLog::log('pollFinishAll');

                $busyMysqli = [];

                foreach ($this->links as $myLink) {
                    if ($myLink->isBusy()) {
                        $busyMysqli[] = $myLink->getMysqli();
                    }
                }

                if (empty($busyMysqli)) {
                    return;
                }

                $reads = $errors = $reject = $busyMysqli;
                mysqli_poll($reads, $errors, $reject, 0, 50000);

                foreach ($this->links as $myLink) {
                    if ($myLink->isBusy()) {
                        $myLink->poll();
                    }
                }
            }
        }

        /**
         * @return int
         */
        public function getLinksCount(): int {
            return count($this->links);
        }
    }
}

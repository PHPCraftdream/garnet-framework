<?php
declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link {
    use mysqli_sql_exception;
    use PHPCraftdream\Garnet\Kernel\Core\Benchmark\BenchmarkLog;
    use PHPCraftdream\Garnet\Kernel\Db\Query\QueryTools;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbPool;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

    class DbPool implements IDbPool {
        // pollFinishAll() must never spin forever: a link stuck busy=true
        // (e.g. queryAsync()'s dispatch failing without throwing) would
        // otherwise hang the request permanently under runtimes with no
        // max_execution_time (e.g. `php -S`'s dev server).
        protected const POLL_FINISH_ALL_TIMEOUT_SECONDS = 10.0;

        protected static ?DbPool $instance = null;

        /**
         * @var list<callable(): void> Hooks invoked at the end of
         *      closeAll(), after every mysqli handle has been closed and
         *      $links emptied. Lets higher-level classes built on top of
         *      DbPool (e.g. NamedLock, which pins connections outside the
         *      normal getLink()-borrows-any-free-link path) discard their
         *      own references to those now-closed connections without
         *      DbPool having to know those classes exist. Registered via
         *      onCloseAll(); intentionally NOT cleared by closeAll() itself
         *      so the same hook keeps firing on every subsequent closeAll()
         *      call in a long-running process (e.g. Kahlan running many
         *      spec files back-to-back against the shared singleton).
         */
        protected static array $closeAllHooks = [];

        protected function __construct() {
        }

        /**
         * Register a callback to run whenever closeAll() closes every
         * connection this pool holds. See $closeAllHooks for why this
         * exists instead of DbPool calling into higher-level classes
         * directly: DbPool is the lower-level primitive here (NamedLock
         * depends on DbPool, never the other way around), so the
         * dependency is inverted into a subscription instead.
         *
         * @param callable(): void $hook
         * @return void
         */
        public static function onCloseAll(callable $hook): void {
            static::$closeAllHooks[] = $hook;
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
            //
            // Must never spin forever: a link stuck busy=true (e.g.
            // queryAsync()'s dispatch failing without throwing) would
            // otherwise hang the request permanently under runtimes with
            // no max_execution_time (e.g. `php -S`'s dev server).
            $deadline = microtime(true) + self::POLL_FINISH_ALL_TIMEOUT_SECONDS;

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

                if (microtime(true) >= $deadline) {
                    throw new DbException(
                        'pollLinks() timed out after ' . self::POLL_FINISH_ALL_TIMEOUT_SECONDS
                        . 's waiting for ' . count($busyMysqli) . ' busy link(s) to finish'
                    );
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
         * @throws DbException
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

            // Charset must be set on the connection itself (not just via
            // "SET NAMES" in options[MYSQL_ATTR_INIT_COMMAND], which is
            // optional and easy to omit) so that mysqli_real_escape_string()
            // in QueryTools::escapeSqlParam() escapes using the connection's
            // actual charset. Without this, multi-byte-unsafe charsets can
            // make manual/real_escape_string escaping bypassable.
            $mysqli->set_charset('utf8mb4');

            $options = $config->param('options');
            $initCmd = $options['MYSQL_ATTR_INIT_COMMAND'] ?? null;

            if ($initCmd) {
                $mysqli->real_query($initCmd);

                // Drain any result set the init command may have produced.
                // If real_query() returns rows (e.g. "SELECT 1" as an init command),
                // that result set must be consumed before the next query can run,
                // otherwise we get "Commands out of sync; you can't run this command now".
                if ($res = $mysqli->store_result()) {
                    $res->free();
                }

                // Drain any additional result sets from multi-statement init commands.
                while ($mysqli->more_results() && $mysqli->next_result()) {
                    if ($res = $mysqli->store_result()) {
                        $res->free();
                    }
                }
            }

            // Guard against MySQL sql_mode values that would break escaping
            // assumptions:
            // - NO_BACKSLASH_ESCAPES: makes \' parse as literal backslash+quote,
            //   not an escaped quote, bypassing mysqli_real_escape_string()
            // - ANSI_QUOTES: makes "..." parse as identifiers instead of string
            //   literals, breaking buildSql/fieldVal/fieldValIn's value wrapping
            //
            // This guard runs AFTER MYSQL_ATTR_INIT_COMMAND so it observes the
            // final, actually-active session mode (not the mode before init_command).
            //
            // Since PHP 8.1, mysqli defaults to MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT,
            // meaning a failing query throws mysqli_sql_exception instead of returning false.
            // We catch both exception and false-return (defense-in-depth for apps that
            // explicitly disable error reporting via mysqli_report(MYSQLI_REPORT_OFF)).
            try {
                $sqlModeResult = $mysqli->query('SELECT @@session.sql_mode AS sql_mode');
            } catch (mysqli_sql_exception $e) {
                $mysqli->close();

                throw new DbException(
                    'Refusing to establish DB connection: could not verify sql_mode (probe query failed)',
                    0,
                    $e
                );
            }

            if (!$sqlModeResult) {
                // Defense-in-depth for the (currently unreachable, since mysqli defaults to
                // exception-throwing on error) case where mysqli error reporting has been
                // disabled by the application (mysqli_report(MYSQLI_REPORT_OFF)).
                $mysqli->close();

                throw new DbException('Refusing to establish DB connection: could not verify sql_mode (probe query failed)');
            }

            $row = $sqlModeResult->fetch_assoc();
            $sqlModeResult->free();

            if ($row && isset($row['sql_mode'])) {
                $sqlMode = $row['sql_mode'];
                $dangerousModes = ['NO_BACKSLASH_ESCAPES', 'ANSI_QUOTES'];

                foreach ($dangerousModes as $mode) {
                    if (stripos($sqlMode, $mode) !== false) {
                        $mysqli->close();

                        throw new DbException(
                            "Refusing to establish DB connection: sql_mode contains {$mode}, "
                            . 'which would break escaping safety assumptions. '
                            . "Current sql_mode: {$sqlMode}"
                        );
                    }
                }
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
        public function queryAsync(string $sql, array $args = [], ?callable $callBack = null): IDbMySQLiLink {
            $link = $this->getLink();

            // mysqli has no async prepared-statement execution (MYSQLI_ASYNC
            // only works with mysqli::query() on a raw string, never with
            // mysqli_stmt::execute()), so the async path can't use real
            // bound parameters like the sync path (DbMySQLiLink::query())
            // does. Values are escaped via mysqli_real_escape_string()
            // against this connection (charset-aware) and interpolated.
            $newSql = empty($args) ? $sql : QueryTools::buildSql($sql, $args, $link->getMysqli());

            return $link->queryAsync($newSql, $callBack);
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
            $deadline = microtime(true) + self::POLL_FINISH_ALL_TIMEOUT_SECONDS;

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

                if (microtime(true) >= $deadline) {
                    throw new DbException(
                        'pollFinishAll() timed out after ' . self::POLL_FINISH_ALL_TIMEOUT_SECONDS
                        . 's waiting for ' . count($busyMysqli) . ' busy link(s) to finish'
                    );
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

        /**
         * Close every open mysqli connection this pool holds. Not called
         * from the request lifecycle: a single php-fpm/`php -S` worker
         * keeps a small, steady-state pool of reused connections for its
         * whole life, which is intentional (see getLink()). This exists
         * for test-process hygiene, where many independent Kahlan spec
         * files run back-to-back in one PHP process and would otherwise
         * each accumulate their own links against the shared singleton
         * until the DB's max_connections is exhausted.
         *
         * Deliberately does NOT reset static::$instance to null: other
         * singletons (e.g. QueryEx::$instance) capture the DbPool object
         * itself via DbPool::get() and keep that reference for the life
         * of the process. Replacing the singleton here would leave those
         * callers holding a stale IDbPool wrapping closed connections, so
         * this mutates the existing instance's link list in place instead
         * — every current and future holder of the DbPool object observes
         * the same emptied pool and transparently opens fresh connections
         * on next use.
         *
         * @return void
         */
        public static function closeAll(): void {
            if (static::$instance === null) {
                return;
            }

            foreach (static::$instance->links as $link) {
                $link->getMysqli()->close();
            }

            static::$instance->links = [];

            // Let subscribers (e.g. NamedLock) discard their own references
            // to the connections just closed above. See $closeAllHooks.
            foreach (static::$closeAllHooks as $hook) {
                $hook();
            }
        }
    }
}

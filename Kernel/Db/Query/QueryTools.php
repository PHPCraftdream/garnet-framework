<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query {
    use mysqli;

    class QueryTools {
        public static function makeInsertBatchNamed(
            string $table,
            array $queryData,
            ?string $onDuplicateKey = null,
            bool $insertDelayed = false
        ): array {
            $queries = [];
            $params = [];
            $fieldNames = [];

            foreach ($queryData as $ind => $item) {
                $insParams = [];

                foreach ($item as $param => $value) {
                    $paramFix = ':' . $param . $ind;
                    $params[$paramFix] = $value;
                    $insParams[] = $paramFix;

                    $fieldNames[$param] = true;
                }
                $queries[] = '(' . implode(', ', $insParams) . ')';
            }

            $fieldNames = array_keys($fieldNames);
            $fieldNames = array_map(static fn ($field) => "`{$field}`", $fieldNames);
            $fieldNames = '(' . implode(', ', $fieldNames) . ')';

            $insert = $insertDelayed ? 'INSERT DELAYED' : 'INSERT';

            $values = implode(', ', $queries);
            $sql = "{$insert} INTO {$table} {$fieldNames} VALUES {$values}";

            if (!empty($onDuplicateKey)) {
                $sql .= ' ON DUPLICATE KEY UPDATE ' . $onDuplicateKey;
            }

            return [$sql, $params];
        }

        // --------------------------------------------------------------------------------------------------------------

        public static function makeInsertBatchIndexed(
            string $table,
            array $queryData,
            ?string $onDuplicateKey = null,
            bool $insertDelayed = false
        ): array {
            $queries = [];
            $params = [];
            $fieldNames = [];

            $keys = [];

            foreach ($queryData as $item) {
                foreach ($item as $param => $value) {
                    $fieldNames[$param] = true;
                    $keys[] = $param;
                }

                break;
            }

            foreach ($queryData as $item) {
                $insParams = [];

                foreach ($keys as $param) {
                    $value = $item[$param] ?? null;

                    $params[] = $value;
                    $insParams[] = '?';
                }
                $queries[] = '(' . implode(', ', $insParams) . ')';
            }

            $fieldNames = array_keys($fieldNames);
            $fieldNames = array_map(static fn ($field) => "`{$field}`", $fieldNames);
            $fieldNames = '(' . implode(', ', $fieldNames) . ')';

            $insert = $insertDelayed ? 'INSERT DELAYED' : 'INSERT';

            $values = implode(', ', $queries);
            $sql = "{$insert} INTO {$table} {$fieldNames} VALUES {$values}";

            if (!empty($onDuplicateKey)) {
                $sql .= ' ON DUPLICATE KEY UPDATE ' . $onDuplicateKey;
            }

            return [$sql, $params];
        }

        // ##############################################################################################################

        public static function patchArgsNamed(string $sql, array $args): array {
            $orderedArgs = [];
            $parametrizedArgs = [];
            $newArgs = [];

            foreach ($args as $key => $val) {
                $strKey1 = intval($key) . '';
                $strKey2 = $key . '';

                if ($strKey1 === $strKey2) {
                    $orderedArgs[] = $val;
                } else {
                    $parametrizedArgs[$key] = $val;
                }
            }

            $ind = 0;
            $newSql = '';
            $start = 0;
            $pos = strpos($sql, '?', $start);

            while ($pos !== false) {
                $param = 'p' . $ind;
                $value = $orderedArgs[$ind] ?? null;

                if ($value === null) {
                    goto prepare_next_step;
                }

                if (is_array($value)) {
                    $placeholders = [];
                    $pInd = 0;

                    foreach ($value as $v) {
                        $newParam = $param . $pInd;
                        $pInd += 1;
                        $newArgs[$newParam] = $v;
                        $placeholders[] = ':' . $newParam;
                    }

                    $placeholder = implode(', ', $placeholders);
                } else {
                    $newArgs[$param] = $value;
                    $placeholder = ':' . $param;
                }

                $newSql .= substr($sql, $start, $pos - $start) . $placeholder;

                prepare_next_step: {
                    $start = $pos + 1;
                    $pos = strpos($sql, '?', $start);
                    $ind += 1;
                }
            }

            if (!empty($newSql)) {
                $newSql .= substr($sql, $start);
                $sql = $newSql;
            }

            foreach ($parametrizedArgs as $key => $values) {
                if (!is_array($values)) {
                    $newArgs[$key] = $values;

                    continue;
                }

                $keys = [];

                foreach ($values as $ind => $v) {
                    $newKey = $key . $ind;
                    $newArgs[$newKey] = $v;
                    $keys[] = ':' . $newKey;
                }

                $sql = str_replace(':' . $key, implode(', ', $keys), $sql);
            }

            return [$sql, $newArgs];
        }

        // --------------------------------------------------------------------------------------------------------------

        public static function patchArgsIndexed(string $sql, array $args): array {
            $newArgs = [];
            $currentInd = 0;

            $sql = preg_replace_callback('#(\?)|(:(\w+))#is', function ($matches) use (&$newArgs, &$currentInd, $args) {
                $statement = $matches[3] ?? $matches[0];
                $isQ = $statement === '?';
                $key = $isQ ? $currentInd : $statement;

                $value = $args[$key] ?? ($args[':' . $key] ?? null);

                if ($isQ) {
                    $currentInd += 1;
                }

                if (is_array($value)) {
                    $arrRes = [];

                    foreach ($value as $arrItem) {
                        $newArgs[] = $arrItem;
                        $arrRes[] = '?';
                    }

                    return join(', ', $arrRes);
                }

                $newArgs[] = $value;

                return '?';
            }, $sql);

            return [$sql, $newArgs];
        }

        // --------------------------------------------------------------------------------------------------------------

        /**
         * Escapes a scalar for interpolation into a raw SQL string.
         *
         * INTERNAL. This exists only to support {@see DbPool::queryAsync()},
         * which cannot use real bound parameters because mysqli has no async
         * prepared-statement execution (MYSQLI_ASYNC only works with
         * mysqli::query() on a raw string). It is NOT a general-purpose
         * sanitization helper — do not use it in app-level code as a
         * substitute for parameterized queries. Prefer `?`/`:name`
         * placeholders through `DbTable`/`QueryEx`/`DbPool::query()`
         * (the sync path), which use real `mysqli::prepare()` + bound
         * params.
         *
         * When a `mysqli` connection is supplied, escaping is delegated to
         * `mysqli_real_escape_string()` against that connection so it is
         * charset-aware and does not corrupt combining marks / multi-byte
         * sequences. Without a connection, a conservative fallback is used
         * that assumes an ASCII-safe connection charset (utf8mb4/utf8);
         * callers on the async DB path always supply the connection.
         *
         * @param string|int|float|bool $value
         * @param mysqli|null $link
         * @return string
         */
        public static function escapeSqlParam(string|int|float|bool $value, ?mysqli $link = null): string {
            if (is_numeric($value)) {
                return (string)$value;
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            if (!mb_check_encoding($value, 'UTF-8')) {
                return '';
            }

            if ($link !== null) {
                return $link->real_escape_string($value);
            }

            // Mirrors mysqli_real_escape_string()'s escape table (NUL, \n,
            // \r, \, ', ", Ctrl-Z) for callers without a live connection.
            return str_replace(
                ['\\', "'", '"', "\x00", "\n", "\r", "\x1a"],
                ['\\\\', "\\'", '\\"', '\\0', '\\n', '\\r', '\\Z'],
                $value
            );
        }

        /**
         * INTERNAL — see {@see self::escapeSqlParam()}. Not a general-purpose
         * sanitization helper; do not use for app-level query building.
         *
         * @param string $sql
         * @param array $args
         * @param mysqli|null $link
         * @return string
         */
        public static function buildSql(string $sql, array $args = [], ?mysqli $link = null): string {
            if (empty($args)) {
                return $sql;
            }

            $index = -1;

            $sql = preg_replace_callback('/[?]/', function () use (&$args, &$index, $link) {
                $index += 1;

                $isset = array_key_exists($index, $args);

                if ($isset && $args[$index] === null) {
                    return 'NULL';
                }

                return $isset ? '"' . static::escapeSqlParam($args[$index], $link) . '"' : '?';
            }, $sql);

            $sql = preg_replace_callback('/:([a-zA-Z_][a-zA-Z0-9_]*)/', function ($matches) use ($args, $link) {
                $key = $matches[1];
                $isset = array_key_exists($key, $args);

                if ($isset && $args[$key] === null) {
                    return 'NULL';
                }

                if ($isset) {
                    return '"' . static::escapeSqlParam($args[$key], $link) . '"';
                }

                $key = ':' . $key;

                if (array_key_exists($key, $args)) {
                    return '"' . static::escapeSqlParam($args[$key], $link) . '"';
                }

                return $matches[0];
            }, $sql);

            return $sql;
        }

        // --------------------------------------------------------------------------------------------------------------

        /**
         * INTERNAL — see {@see self::escapeSqlParam()}. Not a general-purpose
         * sanitization helper. Prefer `?`/`:name` placeholders with bound
         * args instead of interpolating this fragment into a query string.
         */
        public static function fieldVal(string $fieldName, string|int|float $value): string {
            $v = is_string($value) ? static::escapeSqlParam($value) : $value;

            return "`{$fieldName}` = \"{$v}\"";
        }

        /**
         * INTERNAL — see {@see self::escapeSqlParam()}. Not a general-purpose
         * sanitization helper. Prefer `?`/`:name` placeholders with bound
         * args instead of interpolating this fragment into a query string.
         */
        public static function fieldValIn(string $fieldName, array $array): string {
            $res = join(
                ', ',
                array_map(
                    fn ($v) => '"' . (is_string($v) ? static::escapeSqlParam($v) : $v) . '"',
                    $array
                )
            );

            return "`{$fieldName}` IN ({$res})";
        }
    }
}

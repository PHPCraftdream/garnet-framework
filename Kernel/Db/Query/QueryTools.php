<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query {
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

        public static function escapeSqlParam(string|int|float|bool $value): string {
            if (is_numeric($value)) {
                return (string)$value;
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            if (!mb_check_encoding($value, 'UTF-8')) {
                return '';
            }

            $value = preg_replace('/[^\p{L}\p{N}\p{P}\p{Z}\p{S}\r\n\t]/u', ' ', $value);

            if (empty($value)) {
                return '';
            }

            $value = str_replace(
                ['\\', "'", '"', "\t", "\r", "\n"],
                ['\\\\', "\'", '\"', '\\t', '\\r', '\\n'],
                $value
            );

            return preg_replace('/\s+/u', ' ', $value);
        }

        public static function buildSql(string $sql, array $args = []): string {
            if (empty($args)) {
                return $sql;
            }

            $index = -1;

            $sql = preg_replace_callback('/[?]/', function () use (&$args, &$index) {
                $index += 1;

                $isset = array_key_exists($index, $args);

                if ($isset && $args[$index] === null) {
                    return 'NULL';
                }

                return $isset ? '"' . static::escapeSqlParam($args[$index]) . '"' : '?';
            }, $sql);

            $sql = preg_replace_callback('/:([a-zA-Z_][a-zA-Z0-9_]*)/', function ($matches) use ($args) {
                $key = $matches[1];
                $isset = array_key_exists($key, $args);

                if ($isset && $args[$key] === null) {
                    return 'NULL';
                }

                if ($isset) {
                    return '"' . static::escapeSqlParam($args[$key]) . '"';
                }

                $key = ':' . $key;

                if (array_key_exists($key, $args)) {
                    return '"' . static::escapeSqlParam($args[$key]) . '"';
                }

                return $matches[0];
            }, $sql);

            return $sql;
        }

        // --------------------------------------------------------------------------------------------------------------

        public static function fieldVal(string $fieldName, string|int|float $value): string {
            $v = is_string($value) ? static::escapeSqlParam($value) : $value;

            return "`{$fieldName}` = \"{$v}\"";
        }

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

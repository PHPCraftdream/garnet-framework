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

            $sql = preg_replace_callback('#(\?)|(:([a-zA-Z_]\w*))#is', function ($matches) use (&$newArgs, &$currentInd, $args) {
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
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            // Convert non-string types to string for further processing
            $strValue = is_string($value) ? $value : (string)$value;

            // Numeric fast-path: only bypass escaping for canonical numeric forms
            // (no whitespace, no exponent, no leading zeros). This prevents edge
            // cases like " 1 " (whitespace), "1e309" (exponent), or INF/NAN from
            // float overflow from being returned verbatim. The regex matches:
            // - Optional leading minus sign
            // - Integer part: either 0 or non-zero digit followed by digits
            // - Optional decimal part: dot followed by one or more digits
            // The D modifier ensures $ matches only at true end (not before newline)
            if (is_numeric($strValue)) {
                if (preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/D', $strValue)) {
                    return $strValue; // Canonical form, safe to return verbatim
                }
                // Non-canonical numeric string (e.g. " 1 ", "1e309", "0777") falls through
            }

            // For non-canonical numeric strings or other types, escape+quote
            if (!mb_check_encoding($strValue, 'UTF-8')) {
                return '';
            }

            if ($link !== null) {
                return $link->real_escape_string($strValue);
            }

            // Mirrors mysqli_real_escape_string()'s escape table (NUL, \n,
            // \r, \, ', ", Ctrl-Z) for callers without a live connection.
            return str_replace(
                ['\\', "'", '"', "\x00", "\n", "\r", "\x1a"],
                ['\\\\', "\\'", '\\"', '\\0', '\\n', '\\r', '\\Z'],
                $strValue
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

            $index = 0;
            $result = '';
            $lastEnd = 0;

            // Single-pass tokenization: find ? and :name in one scan over the
            // original template, never re-scanning substituted output. This prevents
            // cross-pass re-substitution bugs where a positional value containing
            // ":name" could be re-expanded by a later named-placeholder pass.
            if (preg_match_all('#(\?)|(:([a-zA-Z_]\w*))#is', $sql, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $i => [$fullMatch, $matchPos]) {
                    // Append literal text before this match
                    $result .= substr($sql, $lastEnd, $matchPos - $lastEnd);
                    $lastEnd = $matchPos + strlen($fullMatch);

                    // Determine if this is a ? or :name placeholder
                    $isQuestionMark = $matches[1][$i][0] !== '';

                    if ($isQuestionMark) {
                        // Positional placeholder ?
                        $isset = array_key_exists($index, $args);

                        if ($isset && $args[$index] === null) {
                            $result .= 'NULL';
                        } elseif ($isset) {
                            $result .= '"' . static::escapeSqlParam($args[$index], $link) . '"';
                        } else {
                            $result .= '?';
                        }

                        $index += 1;
                    } else {
                        // Named placeholder :name
                        $name = $matches[3][$i][0];
                        $key = $name;
                        $isset = array_key_exists($key, $args);

                        if ($isset && $args[$key] === null) {
                            $result .= 'NULL';
                        } elseif ($isset) {
                            $result .= '"' . static::escapeSqlParam($args[$key], $link) . '"';
                        } else {
                            // Try with : prefix (supports both 'name' and ':name' keys)
                            $key = ':' . $name;

                            if (array_key_exists($key, $args)) {
                                if ($args[$key] === null) {
                                    $result .= 'NULL';
                                } else {
                                    $result .= '"' . static::escapeSqlParam($args[$key], $link) . '"';
                                }
                            } else {
                                // No matching key, leave placeholder literal
                                $result .= $fullMatch;
                            }
                        }
                    }
                }

                // Append remaining text after last match
                $result .= substr($sql, $lastEnd);

                return $result;
            }

            // No placeholders found, return original
            return $sql;
        }

        // --------------------------------------------------------------------------------------------------------------

        /**
         * INTERNAL — see {@see self::escapeSqlParam()}. Not a general-purpose
         * sanitization helper. Prefer `?`/`:name` placeholders with bound
         * args instead of interpolating this fragment into a query string.
         *
         * @param string $fieldName
         * @param string|int|float $value
         * @param mysqli|null $link Optional connection for charset-aware escaping
         * @return string
         */
        public static function fieldVal(string $fieldName, string|int|float $value, ?mysqli $link = null): string {
            $v = is_string($value) ? static::escapeSqlParam($value, $link) : $value;

            return "`{$fieldName}` = \"{$v}\"";
        }

        /**
         * INTERNAL — see {@see self::escapeSqlParam()}. Not a general-purpose
         * sanitization helper. Prefer `?`/`:name` placeholders with bound
         * args instead of interpolating this fragment into a query string.
         *
         * @param string $fieldName
         * @param array $array
         * @param mysqli|null $link Optional connection for charset-aware escaping
         * @return string
         */
        public static function fieldValIn(string $fieldName, array $array, ?mysqli $link = null): string {
            $res = join(
                ', ',
                array_map(
                    fn ($v) => '"' . (is_string($v) ? static::escapeSqlParam($v, $link) : $v) . '"',
                    $array
                )
            );

            return "`{$fieldName}` IN ({$res})";
        }
    }
}

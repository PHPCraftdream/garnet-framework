<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Query\QueryEx;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbTableBuilderException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

    class TableBuilderMySQL implements ITableBuilderDriver {
        public const DEFAULT_TABLE_ENGINE = 'CREATE';

        public const DEFAULT_COLLATE = 'utf8mb4_unicode_ci';

        public const TYPE_CREATE = 'CREATE';

        public const TYPE_ALTER = 'ALTER';

        public const TYPE_DROP = 'DROP';

        protected string $type = '';

        protected ?string $engine = null;

        protected ?string $collate = null;

        protected ?int $autoIncrement = null;

        protected array $dropColumns = [];

        protected array $addColumns = [];

        protected array $changeColumns = [];

        protected array $dropIndexes = [];

        protected array $addIndexes = [];

        protected bool $checkExists = true;

        public function __construct(protected string $tableName) {
        }

        // ##############################################################################################################

        protected function getDefaultTableEngine(): string {
            $config = IniConfig::get(IniConfig::ENV_DB);

            return $config->paramString('defaultTableEngine', static::DEFAULT_TABLE_ENGINE);
        }

        protected function getDefaultCollate(): string {
            $config = IniConfig::get(IniConfig::ENV_DB);

            return $config->paramString('defaultCollate', static::DEFAULT_COLLATE);
        }

        // ##############################################################################################################

        public function ex(): void {
            $queries = $this->buildQueries();
            $queryEx = QueryEx::get();

            foreach ($queries as $query) {
                $queryEx->ex($query, []);
            }
        }

        public function buildQueries(): array {
            if ($this->type === static::TYPE_DROP) {
                $check = $this->checkExists ? ' IF EXISTS ' : '';

                return [
                    "DROP TABLE {$check} `{$this->tableName}`"
                ];
            }

            if ($this->type === static::TYPE_CREATE) {
                $sql = join(",\n", array_merge($this->addColumns, $this->addIndexes));
                $check = $this->checkExists ? 'IF NOT EXISTS' : '';

                return [
                    "CREATE TABLE {$check} `{$this->tableName}` ({$sql})" .
                    " ENGINE = {$this->engine} COLLATE={$this->collate}" .
                    (null === $this->autoIncrement ? '' : " AUTO_INCREMENT={$this->autoIncrement}")
                ];
            }

            if ($this->type === static::TYPE_ALTER) {
                $result = [];
                $add = array_merge($this->addColumns, $this->addIndexes);
                $drop = array_merge($this->dropColumns, $this->dropIndexes);
                $change = $this->changeColumns;

                $sqlAlter = "ALTER TABLE `{$this->tableName}`";

                if (!empty($drop)) {
                    $sqlDrop = join(",\n", $drop);
                    $result[] = "{$sqlAlter} {$sqlDrop};";
                }

                if (!empty($add)) {
                    $sqlAdd = join(",\n", $add);
                    $result[] = "{$sqlAlter} {$sqlAdd};";
                }

                if (!empty($change)) {
                    $sqlAdd = join(",\n", $change);
                    $result[] = "{$sqlAlter} {$sqlAdd};";
                }

                if (!empty($this->engine) || !empty($this->collate) || !empty($this->autoIncrement)) {
                    $sql = $sqlAlter;

                    if (!empty($this->engine)) {
                        $sql .= " ENGINE={$this->engine}";
                    }

                    if (!empty($this->collate)) {
                        $sql .= " COLLATE={$this->engine}";
                    }

                    if (!empty($this->autoIncrement)) {
                        $sql .= "AUTO_INCREMENT={$this->autoIncrement}";
                    }
                    $result[] = $sql;
                }

                return $result;
            }

            throw new DbTableBuilderException('wrong type. #000');
        }

        // ##############################################################################################################

        protected function reset(): void {
            $this->dropColumns = [];
            $this->addColumns = [];
            $this->changeColumns = [];
            $this->dropIndexes = [];
            $this->addIndexes = [];
            $this->checkExists = true;
            $this->engine = null;
            $this->collate = null;
        }

        public static function newCreate(string $tableName): static {
            $res = new static($tableName);

            return $res->create();
        }

        public static function newAlter(string $tableName): static {
            $res = new static($tableName);

            return $res->alter();
        }

        public static function newDrop(string $tableName): static {
            $res = new static($tableName);

            return $res->drop();
        }

        public function create(
            bool $checkExists = true,
            ?string $collate = null,
            ?string $engine = null,
            ?int $autoIncrement = null,
        ): static {
            $this->reset();
            $this->type = static::TYPE_CREATE;
            $this->checkExists = $checkExists;

            $this->engine = empty($engine) ? static::getDefaultTableEngine() : $engine;
            $this->collate = empty($collate) ? static::getDefaultCollate() : $collate;

            if (null !== $autoIncrement) {
                $this->autoIncrement = $autoIncrement;
            }

            return $this;
        }

        public function alter(
            ?string $collate = null,
            ?string $engine = null,
            ?int $autoIncrement = null
        ): static {
            $this->reset();
            $this->type = static::TYPE_ALTER;

            if (!empty($engine)) {
                $this->engine = $engine;
            }

            if (!empty($collate)) {
                $this->collate = $collate;
            }

            if (null !== $autoIncrement) {
                $this->autoIncrement = $autoIncrement;
            }

            return $this;
        }

        public function drop(bool $checkExists = true): static {
            $this->reset();
            $this->type = static::TYPE_DROP;
            $this->checkExists = $checkExists;

            return $this;
        }

        // ##############################################################################################################

        /**
         * Render a column DEFAULT clause for the schema builder. Handles
         * three calling styles seen across Tables/*::init():
         *
         *   default: '0'        — numeric, emits `DEFAULT 0`
         *   default: ''         — empty string, emits `DEFAULT ''`
         *   default: "'open'"   — pre-quoted string, emits `DEFAULT 'open'`
         *
         * The third style (caller pre-quotes) is the legacy convention;
         * the first two are accepted for ergonomics. Strict null check
         * means `DEFAULT '0'` survives — empty('0') would silently drop it.
         */
        protected static function renderDefault(?string $default): string {
            if ($default === null) {
                return '';
            }

            // Already pre-quoted by caller — `''` (empty literal),
            // `'open'`, etc. Two-char minimum so the check is unambiguous.
            if (strlen($default) >= 2 && str_starts_with($default, "'") && str_ends_with($default, "'")) {
                return " DEFAULT {$default}";
            }

            // Numeric literal (covers integers, decimals, signed values).
            if (is_numeric($default)) {
                return " DEFAULT {$default}";
            }
            // SQL keywords commonly used as defaults — pass through raw.
            $upper = strtoupper($default);

            if (in_array($upper, ['CURRENT_TIMESTAMP', 'NULL', 'TRUE', 'FALSE'], true)) {
                return " DEFAULT {$upper}";
            }
            // Anything else: treat as a string and quote, escaping
            // any embedded single quotes.
            $escaped = str_replace("'", "''", $default);

            return " DEFAULT '{$escaped}'";
        }

        protected static function column(
            string $column,
            string $type = 'INT',
            ?string $length = null,
            ?string $default = null,
            bool $null = true,
            bool $autoincrement = false,
            ?string $newName = null,
            ?string $after = null,
        ): string {
            $sql = $type;

            $sql .= empty($length) ? '' : "({$length})";
            $sql .= $null ? ' NULL' : ' NOT NULL';
            $sql .= self::renderDefault($default);
            $sql .= empty($after) ? '' : " AFTER `{$after}`";
            $sql .= $autoincrement ? ' AUTO_INCREMENT' : '';

            if (!empty($newName)) {
                $res = "`{$column}` `{$newName}` {$sql}";
            } else {
                $res = "`{$column}` {$sql}";
            }

            return $res;
        }

        // ##############################################################################################################

        public function addIdColumn(string $idFieldName = 'id'): static {
            $this->addColumn(
                column: $idFieldName,
                type: 'INT',
                length: '11',
                null: false,
                autoincrement: true,
            );

            $this->primaryKey($idFieldName);

            return $this;
        }

        // ##############################################################################################################

        public function addColumn(
            string $column,
            string $type = 'INT',
            ?string $length = null,
            ?string $default = null,
            bool $null = true,
            bool $autoincrement = false,
            ?string $after = null,
        ): static {
            $prefix = match ($this->type) {
                static::TYPE_ALTER => 'ADD COLUMN ',
                static::TYPE_CREATE => '',
                default => false,
            };

            if ($prefix === false) {
                throw new DbTableBuilderException('addColumn wrong type. #001');
            }

            $this->addColumns[] = $prefix . static::column(
                column: $column,
                type: $type,
                length: $length,
                default: $default,
                null: $null,
                autoincrement: $autoincrement,
                after: $after,
            );

            return $this;
        }

        public function changeColumn(
            string $column,
            string $type = 'INT',
            ?string $length = '10',
            ?string $default = null,
            bool $null = true,
            bool $autoincrement = false,
            ?string $newName = null,
            ?string $after = null,
        ): static {
            if ($this->type !== static::TYPE_ALTER) {
                throw new DbTableBuilderException('wrong type. #002');
            }

            $this->changeColumns[] = 'CHANGE COLUMN ' . static::column(
                column: $column,
                type: $type,
                length: $length,
                default: $default,
                null: $null,
                autoincrement: $autoincrement,
                newName: $newName,
                after: $after,
            );

            return $this;
        }

        public function dropColumn(string $column): static {
            if ($this->type !== static::TYPE_ALTER) {
                throw new DbTableBuilderException('wrong type. #003');
            }

            $this->dropColumns[] = "DROP COLUMN IF EXISTS `{$column}`";

            return $this;
        }

        // ##############################################################################################################

        public function primaryKey(array|string $indexes, string $using = ''): static {
            if ($this->type !== static::TYPE_CREATE) {
                throw new DbTableBuilderException('wrong type. #0022');
            }

            if (is_array($indexes)) {
                $arrIndexes = array_map(static fn (string $index) => "`{$index}`", $indexes);
                $index = join(', ', $arrIndexes);
            } else {
                $index = "`{$indexes}`";
            }

            $sql = "PRIMARY KEY ({$index})";

            if (!empty($using)) {
                $sql .= " USING {$using}";
            }

            $this->addIndexes[] = $sql;

            return $this;
        }

        public function addIndex(string $indexName, array|string $indexes, string $type = '', string $using = ''): static {
            $prefix = match ($this->type) {
                static::TYPE_ALTER => 'ADD',
                static::TYPE_CREATE => '',
                default => false,
            };

            if ($prefix === false) {
                throw new DbTableBuilderException('addIndex wrong type. #001: ' . $this->type);
            }

            if (is_array($indexes)) {
                $arrIndexes = array_map(static fn (string $index) => "`{$index}`", $indexes);
                $index = join(', ', $arrIndexes);
            } else {
                $index = "`{$indexes}`";
            }

            if (empty($index)) {
                throw new DbTableBuilderException('empty index. #005');
            }

            if (empty($type)) {
                $type = 'INDEX';
            }

            $sql = "{$prefix} {$type} `{$indexName}` ({$index})";

            // For UNIQUE type, include INDEX keyword for proper SQL syntax
            if ($type === 'UNIQUE') {
                $sql = "{$prefix} UNIQUE INDEX `{$indexName}` ({$index})";
            }

            if (!empty($using)) {
                $sql .= " USING {$using}";
            }

            $this->addIndexes[] = $sql;

            return $this;
        }

        public function dropIndex(string $indexName): static {
            if ($this->type !== static::TYPE_ALTER) {
                throw new DbTableBuilderException('wrong type. #006');
            }

            $this->dropIndexes[] = "DROP INDEX IF EXISTS `{$indexName}`";

            return $this;
        }
    }
}

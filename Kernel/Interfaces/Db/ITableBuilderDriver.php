<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Db {
    interface ITableBuilderDriver {
        public function ex(): void;

        public function create(
            bool $checkExists = true,
            ?string $collate = null,
            ?string $engine = null,
            ?int $autoIncrement = null,
        ): static;

        public function alter(): static;

        public function drop(bool $checkExists = true): static;

        public function addIdColumn(string $idFieldName = 'id'): static;

        public function addColumn(
            string $column,
            string $type = 'INT',
            ?string $length = null,
            ?string $default = null,
            bool $null = true,
            bool $autoincrement = false,
            ?string $after = null,
        ): static;

        public function changeColumn(
            string $column,
            string $type = 'INT',
            ?string $length = '10',
            ?string $default = null,
            bool $null = true,
            bool $autoincrement = false,
            ?string $newName = null,
            ?string $after = null,
        ): static;

        public function dropColumn(string $column): static;

        public function primaryKey(array|string $indexes, string $using = ''): static;

        public function addIndex(string $indexName, array|string $indexes, string $type = '', string $using = ''): static;

        public function dropIndex(string $indexName): static;
    }
}

<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Tables\Spec;

use PHPCraftdream\Garnet\Kernel\Db\Tables\TableBuilderMySQL;

describe('TableBuilderMySQL', function (): void {
    describe('newCreate()', function (): void {
        it('creates CREATE builder', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $queries = $builder->buildQueries();

            expect(count($queries))->toBe(1);
            expect($queries[0])->toContain('CREATE TABLE');
            expect($queries[0])->toContain('test_table');
        });

        it('includes IF NOT EXISTS by default', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('IF NOT EXISTS');
        });

        it('can disable IF NOT EXISTS', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $builder->create(checkExists: false);
            $queries = $builder->buildQueries();

            expect($queries[0])->not->toContain('IF NOT EXISTS');
        });

        it('includes ENGINE and COLLATE', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('ENGINE');
            expect($queries[0])->toContain('COLLATE');
        });
    });

    describe('newAlter()', function (): void {
        it('creates ALTER builder', function (): void {
            $builder = TableBuilderMySQL::newAlter('test_table');
            $queries = $builder->buildQueries();

            expect(is_array($queries))->toBe(true);
            expect(count($queries))->toBe(0); // Empty alter returns empty array
        });
    });

    describe('newDrop()', function (): void {
        it('creates DROP builder', function (): void {
            $builder = TableBuilderMySQL::newDrop('test_table');
            $queries = $builder->buildQueries();

            expect(count($queries))->toBe(1);
            expect($queries[0])->toContain('DROP TABLE');
            expect($queries[0])->toContain('test_table');
        });

        it('includes IF EXISTS by default', function (): void {
            $builder = TableBuilderMySQL::newDrop('test_table');
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('IF EXISTS');
        });

        it('can disable IF EXISTS', function (): void {
            $builder = TableBuilderMySQL::newDrop('test_table');
            $builder->drop(checkExists: false);
            $queries = $builder->buildQueries();

            expect($queries[0])->not->toContain('IF EXISTS');
        });
    });

    describe('addColumn()', function (): void {
        it('adds column to CREATE builder', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $builder->addColumn('name', 'VARCHAR', '255', null: false);
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('`name`');
            expect($queries[0])->toContain('VARCHAR(255)');
            expect($queries[0])->toContain('NOT NULL');
        });

        it('adds column with default value', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $builder->addColumn('status', 'INT', default: '1');
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('DEFAULT 1');
        });

        it('adds column with AUTO_INCREMENT', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $builder->addColumn('id', 'INT', autoincrement: true);
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('AUTO_INCREMENT');
        });

        it('adds column to ALTER builder', function (): void {
            $builder = TableBuilderMySQL::newAlter('test_table');
            $builder->addColumn('new_col', 'TEXT');
            $queries = $builder->buildQueries();

            expect(count($queries))->toBe(1);
            expect($queries[0])->toContain('ALTER TABLE');
            expect($queries[0])->toContain('ADD COLUMN');
            expect($queries[0])->toContain('`new_col`');
        });

        it('throws exception for wrong type in CREATE mode', function (): void {
            $builder = TableBuilderMySQL::newDrop('test_table');

            expect(function () use ($builder): void {
                $builder->addColumn('name', 'VARCHAR');
            })->toThrow();
        });
    });

    describe('addIdColumn()', function (): void {
        it('adds ID column with primary key', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $builder->addIdColumn();
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('`id`');
            expect($queries[0])->toContain('INT(11)');
            expect($queries[0])->toContain('NOT NULL');
            expect($queries[0])->toContain('AUTO_INCREMENT');
            expect($queries[0])->toContain('PRIMARY KEY');
        });

        it('allows custom ID field name', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $builder->addIdColumn('custom_id');
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('`custom_id`');
            expect($queries[0])->toContain('PRIMARY KEY (`custom_id`)');
        });
    });

    describe('primaryKey()', function (): void {
        it('adds primary key for single column', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $builder->primaryKey('id');
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('PRIMARY KEY (`id`)');
        });

        it('adds primary key for multiple columns', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $builder->primaryKey(['user_id', 'post_id']);
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('PRIMARY KEY (`user_id`, `post_id`)');
        });

        it('adds USING clause', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $builder->primaryKey('id', 'BTREE');
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('USING BTREE');
        });
    });

    describe('addIndex()', function (): void {
        it('adds index for single column', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $builder->addIndex('idx_email', 'email');
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('INDEX `idx_email` (`email`)');
        });

        it('adds index for multiple columns', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $builder->addIndex('idx_name', ['first_name', 'last_name']);
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('INDEX `idx_name` (`first_name`, `last_name`)');
        });

        it('adds unique index', function (): void {
            $builder = TableBuilderMySQL::newCreate('test_table');
            $builder->addIndex('uniq_email', 'email', 'UNIQUE');
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('UNIQUE INDEX `uniq_email` (`email`)');
        });

        it('adds index in ALTER mode', function (): void {
            $builder = TableBuilderMySQL::newAlter('test_table');
            $builder->addIndex('idx_name', 'name');
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('ALTER TABLE');
            expect($queries[0])->toContain('ADD INDEX');
        });
    });

    describe('changeColumn()', function (): void {
        it('changes column definition', function (): void {
            $builder = TableBuilderMySQL::newAlter('test_table');
            $builder->changeColumn('old_name', 'VARCHAR', '255');
            $queries = $builder->buildQueries();

            expect(count($queries))->toBe(1);
            expect($queries[0])->toContain('ALTER TABLE');
            expect($queries[0])->toContain('CHANGE COLUMN');
            expect($queries[0])->toContain('`old_name`');
        });

        it('renames column when newName is provided', function (): void {
            $builder = TableBuilderMySQL::newAlter('test_table');
            $builder->changeColumn('old_name', 'VARCHAR', '255', newName: 'new_name');
            $queries = $builder->buildQueries();

            expect($queries[0])->toContain('`old_name` `new_name`');
        });
    });

    describe('dropColumn()', function (): void {
        it('drops column from table', function (): void {
            $builder = TableBuilderMySQL::newAlter('test_table');
            $builder->dropColumn('unused_col');
            $queries = $builder->buildQueries();

            expect(count($queries))->toBe(1);
            expect($queries[0])->toContain('ALTER TABLE');
            expect($queries[0])->toContain('DROP COLUMN IF EXISTS `unused_col`');
        });
    });

    describe('dropIndex()', function (): void {
        it('drops index from table', function (): void {
            $builder = TableBuilderMySQL::newAlter('test_table');
            $builder->dropIndex('idx_old');
            $queries = $builder->buildQueries();

            expect(count($queries))->toBe(1);
            expect($queries[0])->toContain('ALTER TABLE');
            expect($queries[0])->toContain('DROP INDEX IF EXISTS `idx_old`');
        });
    });

    describe('Complex queries', function (): void {
        it('builds complete CREATE TABLE query', function (): void {
            $builder = TableBuilderMySQL::newCreate('users');
            $builder->create(checkExists: true, collate: 'utf8mb4_unicode_ci', engine: 'InnoDB');
            $builder->addIdColumn();
            $builder->addColumn('email', 'VARCHAR', '255', null: false);
            $builder->addColumn('name', 'VARCHAR', '100');
            $builder->addIndex('uniq_email', 'email', 'UNIQUE');
            $builder->addIndex('idx_name', 'name');

            $queries = $builder->buildQueries();
            $sql = $queries[0];

            expect($sql)->toContain('CREATE TABLE IF NOT EXISTS `users`');
            expect($sql)->toContain('ENGINE = InnoDB');
            expect($sql)->toContain('COLLATE=utf8mb4_unicode_ci');
            expect($sql)->toContain('`id` INT(11) NOT NULL AUTO_INCREMENT');
            expect($sql)->toContain('PRIMARY KEY (`id`)');
            expect($sql)->toContain('`email` VARCHAR(255) NOT NULL');
            expect($sql)->toContain('`name` VARCHAR(100)');
            expect($sql)->toContain('UNIQUE INDEX `uniq_email` (`email`)');
            expect($sql)->toContain('INDEX `idx_name` (`name`)');
        });

        it('builds ALTER TABLE with multiple operations', function (): void {
            $builder = TableBuilderMySQL::newAlter('users');
            $builder->addColumn('new_field', 'TEXT');
            $builder->changeColumn('old_field', 'VARCHAR', '255');
            $builder->dropColumn('unused_field');
            $builder->dropIndex('old_index');
            $builder->addIndex('new_index', 'new_field');

            $queries = $builder->buildQueries();

            expect(count($queries))->toBe(3); // DROP, ADD, CHANGE separate queries
            expect($queries[0])->toContain('DROP COLUMN');
            expect($queries[0])->toContain('DROP INDEX');
            expect($queries[1])->toContain('ADD COLUMN');
            expect($queries[1])->toContain('ADD INDEX');
            expect($queries[2])->toContain('CHANGE COLUMN');
        });
    });
});

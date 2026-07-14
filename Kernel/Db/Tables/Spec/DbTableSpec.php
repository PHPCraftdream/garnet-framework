<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Tables\Spec {
    use Kahlan\Plugin\Double;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbTable;
    use ReflectionClass;

    // Mock class extending DbTable for testing
    class MockDbTable extends DbTable {
        protected string $tableName = 'test_table';

        public static function init(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver {
            return Double::instance(['implements' => 'PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver']);
        }

        public function setTableName(string $name): void {
            $this->tableName = $name;
        }

        public function setPrimaryKey(string $key): void {
            $this->primaryKey = $key;
        }

        public function setPrefix(?string $prefix): void {
            $this->prefix = $prefix;
        }

        public function setDefaultPageSize(int $size): void {
            $this->defaultPageSize = $size;
        }
    }

    describe('DbTable', function (): void {
        describe('get()', function (): void {
            beforeEach(function (): void {
                // Reset static items
                $reflection = new ReflectionClass(DbTable::class);
                $itemsProp = $reflection->getProperty('items');
                $itemsProp->setValue(null, []);
            });

            it('returns singleton instance', function (): void {
                $table1 = MockDbTable::get();
                $table2 = MockDbTable::get();

                expect($table1)->toBe($table2);
            });

            it('returns same instance for same class', function (): void {
                $table = MockDbTable::get();

                expect($table)->toBeAnInstanceOf(MockDbTable::class);
                expect($table)->toBeAnInstanceOf(IDbTable::class);
            });

            it('stores instances in static items array', function (): void {
                $reflection = new ReflectionClass(DbTable::class);
                $itemsProp = $reflection->getProperty('items');

                MockDbTable::get();

                $items = $itemsProp->getValue();
                expect(count($items))->toBe(1);
                expect(array_key_exists(MockDbTable::class, $items))->toBe(true);
            });
        });

        describe('getPrimaryKey()', function (): void {
            it('returns default primary key', function (): void {
                $table = MockDbTable::get();
                $primaryKey = $table->getPrimaryKey();

                expect($primaryKey)->toBe('id');
            });

            it('returns custom primary key when set', function (): void {
                $table = MockDbTable::get();
                $table->setPrimaryKey('custom_id');

                $primaryKey = $table->getPrimaryKey();

                expect($primaryKey)->toBe('custom_id');
            });
        });

        describe('getEntityName()', function (): void {
            it('returns table name without prefix', function (): void {
                $table = MockDbTable::get();
                $entityName = $table->getEntityName();

                expect($entityName)->toBe('test_table');
            });

            it('returns configured table name', function (): void {
                $table = MockDbTable::get();
                $table->setTableName('custom_table');

                $entityName = $table->getEntityName();

                expect($entityName)->toBe('custom_table');
            });
        });

        describe('Constructor', function (): void {
            it('is protected', function (): void {
                $reflection = new ReflectionClass(DbTable::class);
                $constructor = $reflection->getConstructor();

                expect($constructor->isProtected())->toBe(true);
            });
        });

        describe('Initial properties', function (): void {
            it('has default prefix as null', function (): void {
                $table = MockDbTable::get();
                $reflection = new ReflectionClass($table);
                $prefixProp = $reflection->getProperty('prefix');

                expect($prefixProp->getValue($table))->toBeNull();
            });

            it('has default defaultSelect as ["*"]', function (): void {
                $table = MockDbTable::get();
                $reflection = new ReflectionClass($table);
                $defaultSelectProp = $reflection->getProperty('defaultSelect');

                expect($defaultSelectProp->getValue($table))->toBe(['*']);
            });

            it('has default defaultPageSize as 10', function (): void {
                $table = MockDbTable::get();
                $reflection = new ReflectionClass($table);
                $defaultPageSizeProp = $reflection->getProperty('defaultPageSize');

                expect($defaultPageSizeProp->getValue($table))->toBe(10);
            });
        });

        describe('setPrimaryKey()', function (): void {
            it('allows setting primary key via reflection', function (): void {
                $table = MockDbTable::get();
                $reflection = new ReflectionClass($table);
                $primaryKeyProp = $reflection->getProperty('primaryKey');
                $primaryKeyProp->setValue($table, 'new_id');

                expect($table->getPrimaryKey())->toBe('new_id');
            });
        });

        describe('setPrefix()', function (): void {
            it('allows setting prefix', function (): void {
                $table = MockDbTable::get();
                $table->setPrefix('custom');

                $reflection = new ReflectionClass($table);
                $prefixProp = $reflection->getProperty('prefix');

                expect($prefixProp->getValue($table))->toBe('custom');
            });

            it('allows setting prefix to null', function (): void {
                $table = MockDbTable::get();
                $table->setPrefix(null);

                $reflection = new ReflectionClass($table);
                $prefixProp = $reflection->getProperty('prefix');

                expect($prefixProp->getValue($table))->toBeNull();
            });
        });

        describe('setQueryEx()', function (): void {
            it('returns QueryEx instance', function (): void {
                $table = MockDbTable::get();
                $queryEx = $table->getQueryEx();

                expect($queryEx)->toBeAnInstanceOf('PHPCraftdream\Garnet\Kernel\Db\Query\QueryEx');
            });
        });

        describe('Interface implementation', function (): void {
            it('implements IDbTable', function (): void {
                $table = MockDbTable::get();

                expect($table)->toBeAnInstanceOf(IDbTable::class);
            });
        });

        describe('Abstract method init()', function (): void {
            it('is declared as abstract public static', function (): void {
                $reflection = new ReflectionClass(DbTable::class);
                $method = $reflection->getMethod('init');

                expect($method->isAbstract())->toBe(true);
                expect($method->isPublic())->toBe(true);
                expect($method->isStatic())->toBe(true);
            });
        });
    });
}

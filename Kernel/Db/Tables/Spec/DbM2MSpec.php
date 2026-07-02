<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\TablesSpec;

use PHPCraftdream\Garnet\Kernel\Db\Tables\DbM2M;
use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
use ReflectionClass;

if (!class_exists('TestTable1', false)) {
    class TestTable1 extends \PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable {
        protected string $tableName = 'table1';

        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return new class() implements ITableBuilderDriver {
                public function build(): void {
                }
            };
        }
    };
}

if (!class_exists('TestTable2', false)) {
    class TestTable2 extends \PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable {
        protected string $tableName = 'table2';

        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return new class() implements ITableBuilderDriver {
                public function build(): void {
                }
            };
        }
    };
}

if (!class_exists('TestM2M', false)) {
    class TestM2M extends DbM2M {
        protected string $DbTableClass1 = TestTable1::class;

        protected string $DbTableClass2 = TestTable2::class;
    }
}

describe('DbM2M', function (): void {
    beforeEach(function (): void {
        // Reset IniConfig static state
        $reflection = new ReflectionClass(\PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig::class);
        $itemsProp = $reflection->getProperty('items');
        $itemsProp->setAccessible(true);
        $itemsProp->setValue([]);

        $initParamsProp = $reflection->getProperty('initParams');
        $initParamsProp->setAccessible(true);
        $initParamsProp->setValue([]);

        // Set up IniConfig to avoid prefix
        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'db_test_' . uniqid() . '.ini';
        file_put_contents($tempFile, 'prefix=');
        \PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig::defineDbIni($tempFile);
    });

    describe('Table and entity names', function (): void {
        it('returns table1 from getTable1', function (): void {
            $m2m = TestM2M::get();
            expect($m2m->getTable1())->toBe('table1');
        });

        it('returns table2 from getTable2', function (): void {
            $m2m = TestM2M::get();
            expect($m2m->getTable2())->toBe('table2');
        });

        it('returns table1_table2 from getTableName', function (): void {
            $m2m = TestM2M::get();
            expect($m2m->getTableName())->toBe('table1_table2');
        });

        it('returns table1 from getEntityName1', function (): void {
            $m2m = TestM2M::get();
            expect($m2m->getEntityName1())->toBe('table1');
        });

        it('returns table2 from getEntityName2', function (): void {
            $m2m = TestM2M::get();
            expect($m2m->getEntityName2())->toBe('table2');
        });
    });

    describe('Foreign keys', function (): void {
        it('returns table1_id from getKey1', function (): void {
            $m2m = TestM2M::get();
            expect($m2m->getKey1())->toBe('table1_id');
        });

        it('returns table2_id from getKey2', function (): void {
            $m2m = TestM2M::get();
            expect($m2m->getKey2())->toBe('table2_id');
        });
    });

    describe('Primary key', function (): void {
        it('returns id as primary key', function (): void {
            $m2m = TestM2M::get();
            expect($m2m->getPrimaryKey())->toBe('id');
        });
    });

    describe('Singleton behavior', function (): void {
        it('returns same instance for same call', function (): void {
            $m2m1 = TestM2M::get();
            $m2m2 = TestM2M::get();

            expect($m2m1)->toBe($m2m2);
        });
    });
});

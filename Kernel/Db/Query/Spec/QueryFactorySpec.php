<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query\Spec;

use Aura\Sql\Exception;
use PHPCraftdream\Garnet\Kernel\Db\Query\QueryFactory;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use ReflectionClass;

describe('QueryFactory', function (): void {
    beforeEach(function (): void {
        // Reset the static instance between tests
        $reflection = new ReflectionClass(QueryFactory::class);
        $property = $reflection->getProperty('instance');
        $property->setValue(null, null);

        // Reset IniConfig between tests
        $reflection = new ReflectionClass(IniConfig::class);
        $property = $reflection->getProperty('initParams');
        $property->setValue(null, []);

        $property = $reflection->getProperty('items');
        $property->setValue(null, []);
    });

    describe('get()', function (): void {
        it('returns singleton instance', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'db_test');
            file_put_contents($iniFile, 'type=mysql');

            IniConfig::defineDbIni($iniFile);

            $factory1 = QueryFactory::get();
            $factory2 = QueryFactory::get();

            expect($factory1)->toBe($factory2);

            unlink($iniFile);
        });

        it('creates factory with MySQL type', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'db_test');
            file_put_contents($iniFile, 'type=mysql');

            IniConfig::defineDbIni($iniFile);

            $factory = QueryFactory::get();

            expect($factory)->toBeAnInstanceOf(QueryFactory::class);

            unlink($iniFile);
        });

        it('creates factory with PostgreSQL type', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'db_test');
            file_put_contents($iniFile, 'type=pgsql');

            IniConfig::defineDbIni($iniFile);

            $factory = QueryFactory::get();

            expect($factory)->toBeAnInstanceOf(QueryFactory::class);

            unlink($iniFile);
        });

        it('creates factory with SQLite type', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'db_test');
            file_put_contents($iniFile, 'type=sqlite');

            IniConfig::defineDbIni($iniFile);

            $factory = QueryFactory::get();

            expect($factory)->toBeAnInstanceOf(QueryFactory::class);

            unlink($iniFile);
        });

        it('creates factory with SQLServer type', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'db_test');
            file_put_contents($iniFile, 'type=sqlsrv');

            IniConfig::defineDbIni($iniFile);

            $factory = QueryFactory::get();

            expect($factory)->toBeAnInstanceOf(QueryFactory::class);

            unlink($iniFile);
        });

        it('throws exception when db type is not configured', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'db_test');
            file_put_contents($iniFile, '');

            IniConfig::defineDbIni($iniFile);

            expect(function (): void {
                QueryFactory::get();
            })->toThrow(new Exception('Empty db type from config'));

            unlink($iniFile);
        });

        it('throws exception when db type is empty', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'db_test');
            file_put_contents($iniFile, 'type=');

            IniConfig::defineDbIni($iniFile);

            expect(function (): void {
                QueryFactory::get();
            })->toThrow(new Exception('Empty db type from config'));

            unlink($iniFile);
        });

        it('throws exception when db type config file does not exist', function (): void {
            IniConfig::defineDbIni('/nonexistent/path/to/db.ini');

            expect(function (): void {
                QueryFactory::get();
            })->toThrow();
        });
    });

    describe('Aura SqlQuery integration', function (): void {
        it('can create newSelect query', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'db_test');
            file_put_contents($iniFile, 'type=mysql');

            IniConfig::defineDbIni($iniFile);

            $factory = QueryFactory::get();
            $select = $factory->newSelect();

            expect($select)->toBeAnInstanceOf(\Aura\SqlQuery\Common\SelectInterface::class);

            unlink($iniFile);
        });

        it('can create newInsert query', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'db_test');
            file_put_contents($iniFile, 'type=mysql');

            IniConfig::defineDbIni($iniFile);

            $factory = QueryFactory::get();
            $insert = $factory->newInsert();

            expect($insert)->toBeAnInstanceOf(\Aura\SqlQuery\Common\InsertInterface::class);

            unlink($iniFile);
        });

        it('can create newUpdate query', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'db_test');
            file_put_contents($iniFile, 'type=mysql');

            IniConfig::defineDbIni($iniFile);

            $factory = QueryFactory::get();
            $update = $factory->newUpdate();

            expect($update)->toBeAnInstanceOf(\Aura\SqlQuery\Common\UpdateInterface::class);

            unlink($iniFile);
        });

        it('can create newDelete query', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'db_test');
            file_put_contents($iniFile, 'type=mysql');

            IniConfig::defineDbIni($iniFile);

            $factory = QueryFactory::get();
            $delete = $factory->newDelete();

            expect($delete)->toBeAnInstanceOf(\Aura\SqlQuery\Common\DeleteInterface::class);

            unlink($iniFile);
        });
    });
});

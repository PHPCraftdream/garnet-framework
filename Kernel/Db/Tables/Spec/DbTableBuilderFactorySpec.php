<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Tables\Spec;

use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
use PHPCraftdream\Garnet\Kernel\Db\Tables\TableBuilderMySQL;
use PHPCraftdream\Garnet\Kernel\Exceptions\DbTableBuilderException;
use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use ReflectionClass;

describe('DbTableBuilderFactory', function (): void {
    $tempFile = '';
    $tempDir = '';

    beforeAll(function () use (&$tempFile, &$tempDir): void {
        // Create temp directory for tests
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_dbfactory_' . uniqid();

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0o777, true);
        }

        // Create test INI file with MySQL config
        $tempFile = $tempDir . DIRECTORY_SEPARATOR . 'db.ini';
        file_put_contents($tempFile, '
type = mysql
host = localhost
db_name = test_db
user = root
password = pass
');
    });

    afterAll(function () use (&$tempFile, &$tempDir): void {
        // Cleanup
        if (is_file($tempFile)) {
            unlink($tempFile);
        }

        if (is_dir($tempDir)) {
            rmdir($tempDir);
        }
    });

    beforeEach(function () use (&$tempFile): void {
        // Reset IniConfig static properties
        $reflection = new ReflectionClass(IniConfig::class);
        $initParamsProp = $reflection->getProperty('initParams');
        $initParamsProp->setValue(null, []);

        $itemsProp = $reflection->getProperty('items');
        $itemsProp->setValue(null, []);

        // Define DB config
        IniConfig::define($tempFile, 'ENV_DB');
    });

    describe('get()', function (): void {
        it('returns TableBuilderMySQL for mysql type', function (): void {
            $builder = DbTableBuilderFactory::get('test_table');

            expect($builder)->toBeAnInstanceOf(TableBuilderMySQL::class);
            expect($builder)->toBeAnInstanceOf(ITableBuilderDriver::class);
        });

        it('throws exception for empty type', function (): void {
            // Create config without type
            // First call to initialize the config instance
            DbTableBuilderFactory::get('test_table');

            $reflection = new ReflectionClass(IniConfig::class);
            $itemsProp = $reflection->getProperty('items');
            $items = $itemsProp->getValue();

            $config = $items['ENV_DB'];
            $config->set('type', '');

            expect(function (): void {
                DbTableBuilderFactory::get('test_table');
            })->toThrow(new DbTableBuilderException('Empty type'));
        });

        it('throws exception for unknown type', function (): void {
            // Create config with unknown type
            // First call to initialize the config instance
            DbTableBuilderFactory::get('test_table');

            $reflection = new ReflectionClass(IniConfig::class);
            $itemsProp = $reflection->getProperty('items');
            $items = $itemsProp->getValue();

            $config = $items['ENV_DB'];
            $config->set('type', 'unknown');

            expect(function (): void {
                DbTableBuilderFactory::get('test_table');
            })->toThrow(new DbTableBuilderException('Unknown type: unknown'));
        });

        it('uses table name from parameter', function (): void {
            $builder1 = DbTableBuilderFactory::get('table1');
            $builder2 = DbTableBuilderFactory::get('table2');

            expect($builder1)->toBeAnInstanceOf(TableBuilderMySQL::class);
            expect($builder2)->toBeAnInstanceOf(TableBuilderMySQL::class);
        });
    });
});

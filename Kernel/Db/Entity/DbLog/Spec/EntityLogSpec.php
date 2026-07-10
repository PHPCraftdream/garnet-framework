<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\DbLog\Spec;

use PHPCraftdream\Garnet\Kernel\Db\Entity\DbLog\EntityLog;
use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
use ReflectionClass;

describe('EntityLog', function (): void {
    beforeEach(function (): void {
        // Reset static items
        $reflection = new ReflectionClass(EntityLog::class);
        $property = $reflection->getProperty('items');
        $property->setAccessible(true);
        $property->setValue(null, []);
    });

    describe('static properties', function (): void {
        it('has correct tableName', function (): void {
            $entityLog = EntityLog::get();

            expect($entityLog->tableName)->toBe('entity_log');
        });

        it('has correct primaryKey', function (): void {
            $entityLog = EntityLog::get();

            expect($entityLog->primaryKey)->toBe('id');
        });

        it('has correct idForVersion', function (): void {
            $entityLog = EntityLog::get();

            expect($entityLog->idForVersion)->toBe('1000');
        });
    });

    describe('get()', function (): void {
        it('returns singleton instance', function (): void {
            $instance1 = EntityLog::get();
            $instance2 = EntityLog::get();

            expect($instance1)->toBe($instance2);
        });

        it('returns instance of EntityLog', function (): void {
            $entityLog = EntityLog::get();

            expect($entityLog)->toBeAnInstanceOf(EntityLog::class);
        });
    });

    describe('DbTable inheritance', function (): void {
        it('extends DbTable', function (): void {
            $entityLog = EntityLog::get();

            expect($entityLog)->toBeAnInstanceOf(\PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable::class);
        });

        it('implements IDbTable', function (): void {
            $entityLog = EntityLog::get();

            expect($entityLog)->toBeAnInstanceOf(\PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbTable::class);
        });

        it('has getPrimaryKey method', function (): void {
            $entityLog = EntityLog::get();

            expect(method_exists($entityLog, 'getPrimaryKey'))->toBe(true);
            expect($entityLog->getPrimaryKey())->toBe('id');
        });
    });

    describe('writeLog() method signature', function (): void {
        it('has writeLog method', function (): void {
            $entityLog = EntityLog::get();

            expect(method_exists($entityLog, 'writeLog'))->toBe(true);
        });

        it('returns IDbMySQLiLink type', function (): void {
            $entityLog = EntityLog::get();
            $reflection = new ReflectionClass($entityLog);
            $method = $reflection->getMethod('writeLog');

            expect($method->hasReturnType())->toBe(true);
            $returnType = $method->getReturnType();
            expect($returnType->getName())->toBe(IDbMySQLiLink::class);
        });

        it('has correct parameters', function (): void {
            $entityLog = EntityLog::get();
            $reflection = new ReflectionClass($entityLog);
            $method = $reflection->getMethod('writeLog');
            $parameters = $method->getParameters();

            expect(count($parameters))->toBe(5);

            expect($parameters[0]->getName())->toBe('entity');
            expect($parameters[1]->getName())->toBe('entityId');
            expect($parameters[2]->getName())->toBe('action');
            expect($parameters[3]->getName())->toBe('data');
            expect($parameters[4]->getName())->toBe('isDiff');
        });

        it('isDiff parameter has default value', function (): void {
            $entityLog = EntityLog::get();
            $reflection = new ReflectionClass($entityLog);
            $method = $reflection->getMethod('writeLog');
            $parameters = $method->getParameters();

            expect($parameters[4]->isDefaultValueAvailable())->toBe(true);
            expect($parameters[4]->getDefaultValue())->toBe(false);
        });

        it('entityId parameter type is string|int', function (): void {
            $entityLog = EntityLog::get();
            $reflection = new ReflectionClass($entityLog);
            $method = $reflection->getMethod('writeLog');
            $parameters = $method->getParameters();

            // entityId is at index 1
            $type = $parameters[1]->getType();
            expect($type)->not->toBeNull();
        });

        it('action parameter type is string', function (): void {
            $entityLog = EntityLog::get();
            $reflection = new ReflectionClass($entityLog);
            $method = $reflection->getMethod('writeLog');
            $parameters = $method->getParameters();

            expect($parameters[2]->getType()->getName())->toBe('string');
        });

        it('data parameter type is array', function (): void {
            $entityLog = EntityLog::get();
            $reflection = new ReflectionClass($entityLog);
            $method = $reflection->getMethod('writeLog');
            $parameters = $method->getParameters();

            expect($parameters[3]->getType()->getName())->toBe('array');
        });

        it('isDiff parameter type is bool', function (): void {
            $entityLog = EntityLog::get();
            $reflection = new ReflectionClass($entityLog);
            $method = $reflection->getMethod('writeLog');
            $parameters = $method->getParameters();

            expect($parameters[4]->getType()->getName())->toBe('bool');
        });
    });
});

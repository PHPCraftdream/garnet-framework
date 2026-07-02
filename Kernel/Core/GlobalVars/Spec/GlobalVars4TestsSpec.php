<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\GlobalVars\GlobalVars4Tests;

describe('GlobalVars4Tests', function (): void {
    beforeEach(function (): void {
        // Reset static state before each test
        GlobalVars4Tests::reset();
    });

    describe('set() and get()', function (): void {
        it('stores and retrieves values', function (): void {
            GlobalVars4Tests::set('test_key', 'test_value');
            expect(GlobalVars4Tests::get('test_key'))->toBe('test_value');
        });

        it('returns default value for non-existent keys', function (): void {
            expect(GlobalVars4Tests::get('nonexistent', 'default'))->toBe('default');
        });

        it('returns null when no default specified', function (): void {
            expect(GlobalVars4Tests::get('nonexistent'))->toBeNull();
        });

        it('stores different types of values', function (): void {
            GlobalVars4Tests::set('string', 'hello');
            GlobalVars4Tests::set('int', 123);
            GlobalVars4Tests::set('float', 3.14);
            GlobalVars4Tests::set('bool', true);
            GlobalVars4Tests::set('array', [1, 2, 3]);
            GlobalVars4Tests::set('null', null);

            expect(GlobalVars4Tests::get('string'))->toBe('hello');
            expect(GlobalVars4Tests::get('int'))->toBe(123);
            expect(GlobalVars4Tests::get('float'))->toBe(3.14);
            expect(GlobalVars4Tests::get('bool'))->toBe(true);
            expect(GlobalVars4Tests::get('array'))->toBe([1, 2, 3]);
            expect(GlobalVars4Tests::get('null'))->toBeNull();
        });

        it('overwrites existing values', function (): void {
            GlobalVars4Tests::set('key', 'value1');
            GlobalVars4Tests::set('key', 'value2');
            expect(GlobalVars4Tests::get('key'))->toBe('value2');
        });
    });

    describe('setNotNull()', function (): void {
        it('stores value when not null', function (): void {
            GlobalVars4Tests::setNotNull('key', 'value');
            expect(GlobalVars4Tests::get('key'))->toBe('value');
        });

        it('does not store value when null', function (): void {
            GlobalVars4Tests::set('key', 'original');
            GlobalVars4Tests::setNotNull('key', null);
            expect(GlobalVars4Tests::get('key'))->toBe('original');
        });

        it('does not set new key when value is null', function (): void {
            GlobalVars4Tests::setNotNull('new_key', null);
            expect(GlobalVars4Tests::get('new_key', 'default'))->toBe('default');
        });

        it('stores false value', function (): void {
            GlobalVars4Tests::setNotNull('key', false);
            expect(GlobalVars4Tests::get('key'))->toBe(false);
        });

        it('stores empty string', function (): void {
            GlobalVars4Tests::setNotNull('key', '');
            expect(GlobalVars4Tests::get('key'))->toBe('');
        });

        it('stores zero', function (): void {
            GlobalVars4Tests::setNotNull('key', 0);
            expect(GlobalVars4Tests::get('key'))->toBe(0);
        });

        it('stores empty array', function (): void {
            GlobalVars4Tests::setNotNull('key', []);
            expect(GlobalVars4Tests::get('key'))->toBe([]);
        });
    });

    describe('getString()', function (): void {
        it('returns existing string value', function (): void {
            GlobalVars4Tests::set('key', 'string_value');
            expect(GlobalVars4Tests::getString('key', 'default'))->toBe('string_value');
        });

        it('returns default when key does not exist', function (): void {
            expect(GlobalVars4Tests::getString('nonexistent', 'default'))->toBe('default');
        });

        it('returns default for non-string values', function (): void {
            // GlobalVars4Tests returns default when stored value is not a string
            GlobalVars4Tests::set('key', 123);
            expect(GlobalVars4Tests::getString('key', 'default'))->toBe('default');

            GlobalVars4Tests::set('key', ['array']);
            expect(GlobalVars4Tests::getString('key', 'default'))->toBe('default');
        });
    });

    describe('getAll()', function (): void {
        it('returns empty array when no values set', function (): void {
            expect(GlobalVars4Tests::getAll())->toBe([]);
        });

        it('returns all stored values', function (): void {
            GlobalVars4Tests::set('key1', 'value1');
            GlobalVars4Tests::set('key2', 'value2');
            GlobalVars4Tests::set('key3', 'value3');

            $all = GlobalVars4Tests::getAll();
            expect(count($all))->toBe(3);
            expect($all['key1'])->toBe('value1');
            expect($all['key2'])->toBe('value2');
            expect($all['key3'])->toBe('value3');
        });
    });

    describe('reset()', function (): void {
        it('clears all stored values', function (): void {
            GlobalVars4Tests::set('key1', 'value1');
            GlobalVars4Tests::set('key2', 'value2');

            expect(count(GlobalVars4Tests::getAll()))->toBe(2);

            GlobalVars4Tests::reset();

            expect(GlobalVars4Tests::getAll())->toBe([]);
            expect(GlobalVars4Tests::get('key1', 'default'))->toBe('default');
        });

        it('allows fresh start after reset', function (): void {
            GlobalVars4Tests::set('key', 'value');
            GlobalVars4Tests::reset();

            GlobalVars4Tests::set('key', 'new_value');
            expect(GlobalVars4Tests::get('key'))->toBe('new_value');
        });
    });

    describe('isolation', function (): void {
        it('maintains separate state from GlobalVars', function (): void {
            // This test documents that GlobalVars4Tests has its own storage
            GlobalVars4Tests::set('test', 'value4tests');
            $all = GlobalVars4Tests::getAll();

            expect(count($all))->toBe(1);
            expect($all['test'])->toBe('value4tests');
        });
    });
});

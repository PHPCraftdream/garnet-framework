<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\GlobalVars\GlobalVars;

describe('GlobalVars', function (): void {
    beforeEach(function (): void {
        // Reset static state before each test
        GlobalVars::reset();
    });

    describe('set() and get()', function (): void {
        it('stores and retrieves values', function (): void {
            GlobalVars::set('test_key', 'test_value');
            expect(GlobalVars::get('test_key'))->toBe('test_value');
        });

        it('returns default value for non-existent keys', function (): void {
            expect(GlobalVars::get('nonexistent', 'default'))->toBe('default');
        });

        it('returns null when no default specified', function (): void {
            expect(GlobalVars::get('nonexistent'))->toBeNull();
        });

        it('stores different types of values', function (): void {
            GlobalVars::set('string', 'hello');
            GlobalVars::set('int', 123);
            GlobalVars::set('float', 3.14);
            GlobalVars::set('bool', true);
            GlobalVars::set('array', [1, 2, 3]);
            GlobalVars::set('null', null);

            expect(GlobalVars::get('string'))->toBe('hello');
            expect(GlobalVars::get('int'))->toBe(123);
            expect(GlobalVars::get('float'))->toBe(3.14);
            expect(GlobalVars::get('bool'))->toBe(true);
            expect(GlobalVars::get('array'))->toBe([1, 2, 3]);
            expect(GlobalVars::get('null'))->toBeNull();
        });

        it('overwrites existing values', function (): void {
            GlobalVars::set('key', 'value1');
            GlobalVars::set('key', 'value2');
            expect(GlobalVars::get('key'))->toBe('value2');
        });
    });

    describe('setNotNull()', function (): void {
        it('stores value when not null', function (): void {
            GlobalVars::setNotNull('key', 'value');
            expect(GlobalVars::get('key'))->toBe('value');
        });

        it('does not store value when null', function (): void {
            GlobalVars::set('key', 'original');
            GlobalVars::setNotNull('key', null);
            expect(GlobalVars::get('key'))->toBe('original');
        });

        it('does not set new key when value is null', function (): void {
            GlobalVars::setNotNull('new_key', null);
            expect(GlobalVars::get('new_key', 'default'))->toBe('default');
        });

        it('stores false value', function (): void {
            GlobalVars::setNotNull('key', false);
            expect(GlobalVars::get('key'))->toBe(false);
        });

        it('stores empty string', function (): void {
            GlobalVars::setNotNull('key', '');
            expect(GlobalVars::get('key'))->toBe('');
        });

        it('stores zero', function (): void {
            GlobalVars::setNotNull('key', 0);
            expect(GlobalVars::get('key'))->toBe(0);
        });
    });

    describe('getString()', function (): void {
        it('returns existing string value', function (): void {
            GlobalVars::set('key', 'string_value');
            expect(GlobalVars::getString('key', 'default'))->toBe('string_value');
        });

        it('returns default when key does not exist', function (): void {
            expect(GlobalVars::getString('nonexistent', 'default'))->toBe('default');
        });

        it('returns default when value is not a string', function (): void {
            GlobalVars::set('key', 123);
            expect(GlobalVars::getString('key', 'default'))->toBe('default');
        });
    });

    describe('getAll()', function (): void {
        it('returns empty array when no values set', function (): void {
            expect(GlobalVars::getAll())->toBe([]);
        });

        it('returns all stored values', function (): void {
            GlobalVars::set('key1', 'value1');
            GlobalVars::set('key2', 'value2');
            GlobalVars::set('key3', 'value3');

            $all = GlobalVars::getAll();
            expect(count($all))->toBe(3);
            expect($all['key1'])->toBe('value1');
            expect($all['key2'])->toBe('value2');
            expect($all['key3'])->toBe('value3');
        });
    });

    describe('reset()', function (): void {
        it('clears all stored values', function (): void {
            GlobalVars::set('key1', 'value1');
            GlobalVars::set('key2', 'value2');

            expect(count(GlobalVars::getAll()))->toBe(2);

            GlobalVars::reset();

            expect(GlobalVars::getAll())->toBe([]);
            expect(GlobalVars::get('key1', 'default'))->toBe('default');
        });
    });
});

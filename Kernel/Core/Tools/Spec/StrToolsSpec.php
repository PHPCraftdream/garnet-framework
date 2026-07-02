<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\Tools\StrTools;

describe('StrTools', function (): void {
    describe('pad()', function (): void {
        it('pads strings with various options', function (): void {
            expect(StrTools::pad('test', 8, '-'))->toBe('test----');
            expect(StrTools::pad('test', 8, '-', false))->toBe('----test');
            expect(StrTools::pad('test', 8))->toBe('test    ');
            expect(StrTools::pad('test', 3))->toBe('test');
        });
    });

    describe('maxKeyLen()', function (): void {
        it('returns 0 for empty array', function (): void {
            expect(StrTools::maxKeyLen([]))->toBe(0);
        });

        it('returns max key length for associative array', function (): void {
            expect(StrTools::maxKeyLen(['a' => 1, 'abc' => 2, 'ab' => 3]))->toBe(3);
        });

        it('handles multibyte characters', function (): void {
            expect(StrTools::maxKeyLen(['tëst' => 1, 'lóngÿ-kèyñämé' => 2]))->toBe(13);
        });
    });

    describe('intToBase55()', function (): void {
        it('converts 0 to first symbol', function (): void {
            expect(StrTools::intToBase55(0))->toBe('Q');
        });

        it('converts small numbers', function (): void {
            expect(StrTools::intToBase55(1))->toBe('W');
            expect(StrTools::intToBase55(54))->toBe('9');
        });

        it('converts larger numbers', function (): void {
            $result = StrTools::intToBase55(12345);
            expect($result)->toBe('TTe');
        });

        it('converts timestamp to base55 string', function (): void {
            $result = StrTools::intToBase55(1704067200);
            expect($result)->toBe('RNGXQQ');
        });
    });

    describe('randomString()', function (): void {
        it('generates string of default length 32', function (): void {
            $result = StrTools::randomString();
            expect(strlen($result))->toBe(32);
        });

        it('generates string of custom length', function (): void {
            $result = StrTools::randomString(16);
            expect(strlen($result))->toBe(16);
        });

        it('generates different strings on subsequent calls', function (): void {
            $result1 = StrTools::randomString(32);
            $result2 = StrTools::randomString(32);
            expect($result1)->not->toBe($result2);
        });

        it('uses only allowed symbols', function (): void {
            $result = StrTools::randomString(100);

            // Collect all allowed symbols from the actual class
            $symbolsN = ['Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'P', 'A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L',
                'Z', 'X', 'V', 'B', 'N', 'M', 'q', 'w', 'e', 'r', 't', 'y', 'u', 'p', 'a', 's', 'd',
                'f', 'g', 'h', 'i', 'j', 'k', 'z', 'x', 'v', 'b', 'n', 'm', '1', '2', '3', '4', '5',
                '6', '7', '8', '9'];
            $symbols = ['Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'P', 'A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L',
                'Z', 'X', 'V', 'B', 'N', 'M', 'q', 'w', 'e', 'r', 't', 'y', 'u', 'p', 'a', 's', 'd',
                'f', 'g', 'h', 'i', 'j', 'k', 'z', 'x', 'v', 'b', 'n', 'm'];

            $allowedFirst = implode('', $symbols);
            $allowedRest = implode('', $symbolsN);

            $chars = str_split($result);

            foreach ($chars as $index => $char) {
                $allowed = $index === 0 ? $allowedFirst : $allowedRest;
                $found = strpos($allowed, $char);
                expect($found !== false)->toBe(true);
            }
        });
    });

    describe('randomUtString()', function (): void {
        it('generates string with timestamp prefix', function (): void {
            $result = StrTools::randomUtString(32);
            expect(strlen($result))->toBe(32);
            expect($result)->toMatch('/^[A-Za-z0-9]+$/');
        });

        it('throws exception for invalid length', function (): void {
            expect(function (): void {
                StrTools::randomUtString(2);
            })->toThrow();
        });

        it('generates different strings on subsequent calls', function (): void {
            $result1 = StrTools::randomUtString(32);
            usleep(1000); // Small delay to ensure different timestamp
            $result2 = StrTools::randomUtString(32);
            expect($result1)->not->toBe($result2);
        });
    });

    describe('removePrefix()', function (): void {
        it('removes prefix and handles edge cases', function (): void {
            expect(StrTools::removePrefix('prefix_value', 'prefix_'))->toBe('value');
            expect(StrTools::removePrefix('value', 'prefix_'))->toBe('value');
            expect(StrTools::removePrefix('', 'prefix_'))->toBe('');
            expect(StrTools::removePrefix('value', ''))->toBe('value');
        });
    });

    describe('jsonRead()', function (): void {
        it('decodes valid JSON and returns null for invalid', function (): void {
            expect(StrTools::jsonRead('{"key":"value"}'))->toBe(['key' => 'value']);
            expect(StrTools::jsonRead('[1,2,3]'))->toBe([1, 2, 3]);
            expect(StrTools::jsonRead('{invalid json}'))->toBeNull();
            expect(StrTools::jsonRead(''))->toBeNull();
            expect(StrTools::jsonRead(null))->toBeNull();
        });
    });

    describe('utToDate() and utToDateF()', function (): void {
        it('converts unix timestamp to DateTime with timezone', function (): void {
            $result = StrTools::utToDate(1704067200, 'Europe/Moscow');
            expect($result->format('Y'))->toBe('2024');

            $result = StrTools::utToDate('1704067200', 'UTC');
            expect($result->format('Y'))->toBe('2024');
        });

        it('formats unix timestamp to various formats', function (): void {
            expect(StrTools::utToDateF(1704067200, 'UTC'))->toBe('2024-01-01 00:00');
            expect(StrTools::utToDateF(1704067200, 'UTC', 'Y-m-d'))->toBe('2024-01-01');
        });
    });

    describe('isIntStr()', function (): void {
        it('returns true for integer values', function (): void {
            expect(StrTools::isIntStr('123'))->toBe(true);
            expect(StrTools::isIntStr(123))->toBe(true);
            expect(StrTools::isIntStr('0'))->toBe(true);
            expect(StrTools::isIntStr(0))->toBe(true);
            expect(StrTools::isIntStr('-123'))->toBe(true);
            expect(StrTools::isIntStr(true))->toBe(true);
        });

        it('returns false for non-integer values', function (): void {
            expect(StrTools::isIntStr('123.45'))->toBe(false);
            expect(StrTools::isIntStr('abc'))->toBe(false);
            expect(StrTools::isIntStr(false))->toBe(false);
            expect(StrTools::isIntStr(null))->toBe(false);
        });
    });

    describe('convertToSnakeCase()', function (): void {
        it('converts CamelCase and PascalCase to snake_case', function (): void {
            expect(StrTools::convertToSnakeCase('CamelCase'))->toBe('camel_case');
            expect(StrTools::convertToSnakeCase('PascalCase'))->toBe('pascal_case');
            expect(StrTools::convertToSnakeCase('Word'))->toBe('word');
            expect(StrTools::convertToSnakeCase('myValue'))->toBe('my_value');
        });

        it('handles edge cases and implementation limitations', function (): void {
            expect(StrTools::convertToSnakeCase('snake_case'))->toBe('snake_case');
            expect(StrTools::convertToSnakeCase('getJSONData'))->toBe('get_jsondata');
            expect(StrTools::convertToSnakeCase('Test1Value'))->toBe('test1value');
        });
    });
});

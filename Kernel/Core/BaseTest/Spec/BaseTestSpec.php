<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\BaseTest\BaseTest;

describe('BaseTest', function (): void {
    describe('createMock()', function (): void {
        it('creates mock object from class name', function (): void {
            $mock = BaseTest::createMock(stdClass::class);
            expect($mock)->toBeAnInstanceOf(stdClass::class);
        });

        it('creates multiple mock objects', function (): void {
            $mock1 = BaseTest::createMock(stdClass::class);
            $mock2 = BaseTest::createMock(stdClass::class);

            // Both should be different instances
            expect($mock1)->not->toBe($mock2);
        });

        it('mock can have properties set', function (): void {
            $mock = BaseTest::createMock(stdClass::class);
            $mock->testProp = 'test value';

            expect($mock->testProp)->toBe('test value');
        });
    });

    describe('getPropertyValue()', function (): void {
        it('returns value of public property', function (): void {
            $obj = new class() {
                public string $publicProp = 'public value';
            };

            $result = BaseTest::getPropertyValue($obj, 'publicProp');

            expect($result)->toBe('public value');
        });

        it('returns value of protected property', function (): void {
            $obj = new class() {
                protected string $protectedProp = 'protected value';
            };

            $result = BaseTest::getPropertyValue($obj, 'protectedProp');

            expect($result)->toBe('protected value');
        });

        it('returns value of private property', function (): void {
            $obj = new class() {
                private string $privateProp = 'private value';
            };

            $result = BaseTest::getPropertyValue($obj, 'privateProp');

            expect($result)->toBe('private value');
        });

        it('returns null for null property', function (): void {
            $obj = new class() {
                public string|null $nullableProp = null;
            };

            $result = BaseTest::getPropertyValue($obj, 'nullableProp');

            expect($result)->toBe(null);
        });

        it('returns array property', function (): void {
            $obj = new class() {
                protected array $arrayProp = ['a', 'b', 'c'];
            };

            $result = BaseTest::getPropertyValue($obj, 'arrayProp');

            expect($result)->toBe(['a', 'b', 'c']);
        });

        it('returns integer property', function (): void {
            $obj = new class() {
                protected int $intProp = 42;
            };

            $result = BaseTest::getPropertyValue($obj, 'intProp');

            expect($result)->toBe(42);
        });

        it('returns boolean property', function (): void {
            $obj = new class() {
                protected bool $boolProp = true;
            };

            $result = BaseTest::getPropertyValue($obj, 'boolProp');

            expect($result)->toBe(true);
        });
    });

    describe('getMethod()', function (): void {
        it('returns ReflectionMethod for public method', function (): void {
            $obj = new class() {
                public function publicMethod(): void {
                }
            };

            $method = BaseTest::getMethod($obj::class, 'publicMethod');
            expect($method->getName())->toBe('publicMethod');
        });

        it('returns ReflectionMethod for protected method', function (): void {
            $obj = new class() {
                protected function protectedMethod(): void {
                }
            };

            $method = BaseTest::getMethod($obj::class, 'protectedMethod');
            expect($method->getName())->toBe('protectedMethod');
        });

        it('returns ReflectionMethod for private method', function (): void {
            $obj = new class() {
                private function privateMethod(): void {
                }
            };

            $method = BaseTest::getMethod($obj::class, 'privateMethod');
            expect($method->getName())->toBe('privateMethod');
        });

        it('ReflectionMethod is accessible from base', function (): void {
            $obj = new class() {
                public function testMethod(): string {
                    return 'test result';
                }
            };

            $method = BaseTest::getMethod($obj::class, 'testMethod');

            expect($method->isPublic())->toBe(true);
        });
    });

    describe('invoke()', function (): void {
        it('invokes public method on object', function (): void {
            $obj = new class() {
                public function testMethod(int $a, int $b): int {
                    return $a + $b;
                }
            };

            $result = BaseTest::invoke($obj, 'testMethod', [5, 3]);

            expect($result)->toBe(8);
        });

        it('invokes protected method on object', function (): void {
            $obj = new class() {
                protected function protectedMethod(string $str): string {
                    return 'prefix_' . $str;
                }
            };

            $result = BaseTest::invoke($obj, 'protectedMethod', ['test']);

            expect($result)->toBe('prefix_test');
        });

        it('invokes private method on object', function (): void {
            $obj = new class() {
                private function privateMethod(int $val): int {
                    return $val * 2;
                }
            };

            $result = BaseTest::invoke($obj, 'privateMethod', [21]);

            expect($result)->toBe(42);
        });

        it('invokes static public method by class name', function (): void {
            $className = TestStaticClass::class;

            $result = BaseTest::invoke($className, 'staticMethod');

            expect($result)->toBe('static result');
        });

        it('passes multiple arguments correctly', function (): void {
            $obj = new class() {
                protected function concatenate(string $a, string $b, string $c): string {
                    return $a . $b . $c;
                }
            };

            $result = BaseTest::invoke($obj, 'concatenate', ['hello', ' ', 'world']);

            expect($result)->toBe('hello world');
        });

        it('handles method with no arguments', function (): void {
            $obj = new class() {
                protected function noArgs(): string {
                    return 'no args';
                }
            };

            $result = BaseTest::invoke($obj, 'noArgs', []);

            expect($result)->toBe('no args');
        });

        it('handles method that returns array', function (): void {
            $obj = new class() {
                protected function getArray(): array {
                    return [1, 2, 3];
                }
            };

            $result = BaseTest::invoke($obj, 'getArray', []);

            expect($result)->toBe([1, 2, 3]);
        });

        it('handles method that returns object', function (): void {
            $obj = new class() {
                protected function getObject(): stdClass {
                    return new stdClass();
                }
            };

            $result = BaseTest::invoke($obj, 'getObject', []);
            expect($result)->toBeAnInstanceOf(stdClass::class);
        });
    });

    describe('integration', function (): void {
        it('works together: get method and invoke', function (): void {
            $obj = new class() {
                protected function calculate(): int {
                    return 42;
                }
            };

            $method = BaseTest::getMethod($obj::class, 'calculate');
            $result = BaseTest::invoke($obj, 'calculate', []);

            expect($method->getName())->toBe('calculate');
            expect($result)->toBe(42);
        });

        it('works together: get property and invoke', function (): void {
            $obj = new class() {
                protected int $counter = 0;

                protected function increment(): int {
                    $this->counter++;

                    return $this->counter;
                }
            };

            // Get initial counter value
            $initialValue = BaseTest::getPropertyValue($obj, 'counter');

            // Increment using invoke
            $result1 = BaseTest::invoke($obj, 'increment', []);
            $result2 = BaseTest::invoke($obj, 'increment', []);

            // Get final counter value
            $finalValue = BaseTest::getPropertyValue($obj, 'counter');

            expect($initialValue)->toBe(0);
            expect($result1)->toBe(1);
            expect($result2)->toBe(2);
            expect($finalValue)->toBe(2);
        });
    });
});

class TestStaticClass {
    public static function staticMethod(): string {
        return 'static result';
    }
}

<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\Env\Env;

describe('Env', function (): void {
    describe('isCmd()', function (): void {
        it('returns true when running from CLI', function (): void {
            // Tests are run from CLI
            expect(Env::isCmd())->toBe(true);
        });
    });

    describe('getClassReflection()', function (): void {
        it('returns cached ReflectionClass for same class', function (): void {
            $ref1 = Env::getClassReflection(Env::class);
            $ref2 = Env::getClassReflection(Env::class);
            expect($ref1)->toBe($ref2);
        });

        it('returns different ReflectionClass for different classes', function (): void {
            $ref1 = Env::getClassReflection(stdClass::class);
            $ref2 = Env::getClassReflection(Exception::class);
            expect($ref1)->not->toBe($ref2);
        });
    });

    describe('classImplements()', function (): void {
        it('returns true when class implements interface', function (): void {
            $result = Env::classImplements(ArrayIterator::class, Iterator::class);
            expect($result)->toBe(true);
        });

        it('returns false when class does not implement interface', function (): void {
            $result = Env::classImplements(stdClass::class, Iterator::class);
            expect($result)->toBe(false);
        });

        it('returns true for class implementing multiple interfaces', function (): void {
            // ArrayIterator implements Iterator, ArrayAccess, SeekableIterator, etc.
            expect(Env::classImplements(ArrayIterator::class, Iterator::class))->toBe(true);
            expect(Env::classImplements(ArrayIterator::class, ArrayAccess::class))->toBe(true);
        });

        it('returns true for interface extending interface', function (): void {
            // IteratorAggregate extends Traversable
            expect(Env::classImplements(IteratorAggregate::class, Traversable::class))->toBe(true);
        });

        it('handles case-sensitive interface names', function (): void {
            $result = Env::classImplements(ArrayIterator::class, 'Iterator');
            expect($result)->toBe(true);
        });

        it('throws exception for non-existing interface', function (): void {
            expect(function (): void {
                Env::classImplements(stdClass::class, 'NonExistingInterface');
            })->toThrow();
        });

        it('throws exception for non-existing class', function (): void {
            expect(function (): void {
                Env::classImplements('NonExistingClass', Iterator::class);
            })->toThrow();
        });

        it('returns true when parent class implements interface', function (): void {
            // Exception implements Throwable
            expect(Env::classImplements(Exception::class, Throwable::class))->toBe(true);
        });

        it('returns false for built-in classes without interface', function (): void {
            expect(Env::classImplements(stdClass::class, Countable::class))->toBe(false);
        });
    });

    describe('edge cases', function (): void {
        it('handles reflection of self', function (): void {
            $result = Env::getClassReflection(Env::class);
            expect($result->getName())->toBe(Env::class);
        });

        it('caches reflections separately for each class', function (): void {
            $refStd = Env::getClassReflection(stdClass::class);
            $refEx = Env::getClassReflection(Exception::class);
            $refStd2 = Env::getClassReflection(stdClass::class);

            expect($refStd)->toBe($refStd2);
            expect($refStd)->not->toBe($refEx);
        });
    });
});

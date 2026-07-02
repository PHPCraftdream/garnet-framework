<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Exceptions\CacheException;
use PHPCraftdream\Garnet\Kernel\Exceptions\CommandException;
use PHPCraftdream\Garnet\Kernel\Exceptions\CommonException;
use PHPCraftdream\Garnet\Kernel\Exceptions\I18nException;
use PHPCraftdream\Garnet\Kernel\Exceptions\IoException;
use PHPCraftdream\Garnet\Kernel\Exceptions\LoggerException;
use PHPCraftdream\Garnet\Kernel\Exceptions\RouterException;

describe('Framework Exceptions', function (): void {
    describe('CommonException', function (): void {
        it('can be created with message', function (): void {
            $exc = new CommonException('Test message');

            expect($exc->getMessage())->toBe('Test message');
        });

        it('can be created with message and code', function (): void {
            $exc = new CommonException('Test message', 404);

            expect($exc->getMessage())->toBe('Test message');
            expect($exc->getCode())->toBe(404);
        });

        it('can be thrown and caught', function (): void {
            $caught = false;

            try {
                throw new CommonException('Thrown exception');
            } catch (CommonException $e) {
                $caught = true;
                expect($e->getMessage())->toBe('Thrown exception');
            }

            expect($caught)->toBe(true);
        });
    });

    describe('CacheException', function (): void {
        it('can be created with message', function (): void {
            $exc = new CacheException('Cache error');

            expect($exc->getMessage())->toBe('Cache error');
        });

        it('can be thrown and caught as specific type', function (): void {
            $caught = false;

            try {
                throw new CacheException('Cache failed');
            } catch (CacheException $e) {
                $caught = true;
            }

            expect($caught)->toBe(true);
        });
    });

    describe('CommandException', function (): void {
        it('can be created with message', function (): void {
            $exc = new CommandException('Command not found');

            expect($exc->getMessage())->toBe('Command not found');
        });
    });

    describe('I18nException', function (): void {
        it('can be created with message', function (): void {
            $exc = new I18nException('Translation missing');

            expect($exc->getMessage())->toBe('Translation missing');
        });
    });

    describe('IoException', function (): void {
        it('can be created with message and code', function (): void {
            $exc = new IoException('File not found', 404);

            expect($exc->getMessage())->toBe('File not found');
            expect($exc->getCode())->toBe(404);
        });
    });

    describe('LoggerException', function (): void {
        it('can be created with message', function (): void {
            $exc = new LoggerException('Log write failed');

            expect($exc->getMessage())->toBe('Log write failed');
        });
    });

    describe('RouterException', function (): void {
        it('can be created with message', function (): void {
            $exc = new RouterException('Route not found');

            expect($exc->getMessage())->toBe('Route not found');
        });
    });

    describe('different exception types can be distinguished', function (): void {
        it('distinguishes between exception types', function (): void {
            $types = [];

            try {
                throw new CacheException('cache');
            } catch (CacheException $e) {
                $types[] = 'cache';
            }

            try {
                throw new IoException('io');
            } catch (IoException $e) {
                $types[] = 'io';
            }

            expect($types)->toBe(['cache', 'io']);
        });
    });

    describe('exception messages', function (): void {
        it('preserves message content', function (): void {
            $message = 'Error: something went wrong';
            $exc = new CommonException($message);

            expect($exc->getMessage())->toBe($message);
        });

        it('handles empty message', function (): void {
            $exc = new CommonException('');

            expect($exc->getMessage())->toBe('');
        });

        it('handles special characters in message', function (): void {
            $message = 'Error: "quoted" and \'apostrophes\'';
            $exc = new CommonException($message);

            expect($exc->getMessage())->toBe($message);
        });

        it('handles multibyte characters in message', function (): void {
            $message = 'Fehler: Testnachricht mit Ümlauten';
            $exc = new CommonException($message);

            expect($exc->getMessage())->toBe($message);
        });
    });

    describe('exception codes', function (): void {
        it('accepts zero as error code', function (): void {
            $exc = new CommonException('Error', 0);

            expect($exc->getCode())->toBe(0);
        });

        it('accepts positive error codes', function (): void {
            $exc = new CommonException('Error', 404);

            expect($exc->getCode())->toBe(404);
        });

        it('accepts large error codes', function (): void {
            $exc = new CommonException('Error', 500);

            expect($exc->getCode())->toBe(500);
        });
    });
});

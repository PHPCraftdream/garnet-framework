<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Io\Cookies\Cookie;

describe('Cookie', function (): void {
    describe('constructor and default values', function (): void {
        it('creates cookie with name and value', function (): void {
            $cookie = new Cookie('test_name', 'test_value');
            expect($cookie->getName())->toBe('test_name');
            expect($cookie->getValue())->toBe('test_value');
            expect($cookie->getExpires())->toBe(0);
            expect($cookie->getMaxAge())->toBe(0);
            expect($cookie->getPath())->toBeNull();
            expect($cookie->getDomain())->toBeNull();
            expect($cookie->getSecure())->toBe(false);
            expect($cookie->getHttpOnly())->toBe(false);
            expect($cookie->getSameSite())->toBe('Strict');
            expect($cookie->isNew())->toBe(true);
        });

        it('creates empty cookie with null values', function (): void {
            $cookie = new Cookie();
            expect($cookie->getName())->toBeNull();
            expect($cookie->getValue())->toBeNull();
        });
    });

    describe('property setters and getters', function (): void {
        it('sets and gets all properties', function (): void {
            $cookie = new Cookie();
            $cookie->setName('new_name');
            expect($cookie->getName())->toBe('new_name');

            $cookie->setValue('new_value');
            expect($cookie->getValue())->toBe('new_value');

            $cookie = new Cookie('name', 'value');
            $cookie->setDomain('example.com');
            expect($cookie->getDomain())->toBe('example.com');
            $cookie->setDomain(null);
            expect($cookie->getDomain())->toBeNull();

            $cookie->setPath('/app');
            expect($cookie->getPath())->toBe('/app');
            $cookie->setPath(null);
            expect($cookie->getPath())->toBeNull();

            $cookie->setSecure(true);
            expect($cookie->getSecure())->toBe(true);
            $cookie->setSecure(false);
            expect($cookie->getSecure())->toBe(false);
            $cookie->setSecure(null);
            expect($cookie->getSecure())->toBe(false);

            $cookie->setHttpOnly(true);
            expect($cookie->getHttpOnly())->toBe(true);
            $cookie->setHttpOnly(false);
            expect($cookie->getHttpOnly())->toBe(false);

            $cookie->setMaxAge(3600);
            expect($cookie->getMaxAge())->toBe(3600);
            $cookie->setMaxAge(null);
            expect($cookie->getMaxAge())->toBeNull();
        });
    });

    describe('expires time management', function (): void {
        it('sets expires with various input types', function (): void {
            $cookie = new Cookie('name', 'value');

            $timestamp = time() + 3600;
            $cookie->setExpires($timestamp);
            expect($cookie->getExpires())->toBe($timestamp);

            $date = new DateTime('+1 day');
            $cookie->setExpires($date);
            expect($cookie->getExpires())->toBe($date->getTimestamp());

            $cookie->setExpires('+1 week');
            expect($cookie->getExpires())->toBeGreaterThan(time());

            $cookie->setExpires(null);
            expect($cookie->getExpires())->toBe(0);
        });

        it('provides convenience methods for common expiration scenarios', function (): void {
            $cookie = new Cookie('name', 'value');
            $cookie->rememberForever();
            expect($cookie->getExpires())->toBeGreaterThan(time() + (5 * 365 * 24 * 3600) - 10);

            $cookie->expire();
            expect($cookie->getExpires())->toBeLessThan(time());
        });
    });

    describe('SameSite attribute', function (): void {
        it('sets SameSite to Strict, Lax, and None', function (): void {
            $cookie = new Cookie('name', 'value');
            $cookie->setSameSiteStrict();
            expect($cookie->getSameSite())->toBe('Strict');

            $cookie->setSameSiteLax();
            expect($cookie->getSameSite())->toBe('Lax');

            $cookie->setSameSiteNone();
            expect($cookie->getSameSite())->toBe('None');
        });
    });

    describe('state tracking', function (): void {
        it('tracks new and old state', function (): void {
            $cookie = new Cookie('name', 'value');
            expect($cookie->isNew())->toBe(true);

            $cookie->setOld();
            expect($cookie->isNew())->toBe(false);

            $cookie->setItNew();
            expect($cookie->isNew())->toBe(true);
        });

        it('does not detect changes before observation starts', function (): void {
            $cookie = new Cookie('name', 'value');
            $cookie->setValue('new_value');
            expect($cookie->isChanged())->toBe(false);
        });

        it('detects changes after observation starts', function (): void {
            $cookie = new Cookie('name', 'value');
            $cookie->startObserveChanges();
            expect($cookie->isChanged())->toBe(false);

            $cookie->setValue('new_value');
            expect($cookie->isChanged())->toBe(true);
        });

        it('resets changed flag', function (): void {
            $cookie = new Cookie('name', 'value');
            $cookie->startObserveChanges();
            $cookie->setValue('new');
            expect($cookie->isChanged())->toBe(true);

            $cookie->resetChanged();
            expect($cookie->isChanged())->toBe(false);
        });
    });

    describe('__toString', function (): void {
        it('returns empty string for invalid cookies', function (): void {
            $cookie = new Cookie();
            expect((string)$cookie)->toBe('');

            $cookie = new Cookie('name');
            expect((string)$cookie)->toBe('');

            $cookie = new Cookie('name', 'value');
            $cookie->setOld()->startObserveChanges();
            expect((string)$cookie)->toBe('');
        });

        it('formats complete cookie with all attributes', function (): void {
            $cookie = new Cookie('session', 'abc123');
            $cookie->setDomain('.example.com');
            $cookie->setPath('/');
            $cookie->setSecure(true);
            $cookie->setHttpOnly(true);
            $cookie->setMaxAge(3600);
            $cookie->setExpires(time() + 3600);

            $str = (string)$cookie;
            expect($str)->toContain('session=abc123');
            expect($str)->toContain('Domain=.example.com');
            expect($str)->toContain('Path=/');
            expect($str)->toContain('Secure');
            expect($str)->toContain('HttpOnly');
            expect($str)->toContain('Max-Age=3600');
            expect($str)->toContain('Expires=');
            expect($str)->toContain('SameSite=Strict');
        });

        it('URL encodes name and value', function (): void {
            $cookie = new Cookie('na me', 'val ue');
            expect((string)$cookie)->toContain('na+me=val+ue');
        });
    });

    describe('parse', function (): void {
        it('parses simple and complex cookie strings', function (): void {
            $cookie = new Cookie();
            $cookie->parse('name=value');
            expect($cookie->getName())->toBe('name');
            expect($cookie->getValue())->toBe('value');

            $cookie = new Cookie();
            $cookie->parse('session=abc123; Domain=.example.com; Path=/; Secure; HttpOnly; SameSite=Lax');
            expect($cookie->getName())->toBe('session');
            expect($cookie->getValue())->toBe('abc123');
            expect($cookie->getDomain())->toBe('.example.com');
            expect($cookie->getPath())->toBe('/');
            expect($cookie->getSecure())->toBe(true);
            expect($cookie->getHttpOnly())->toBe(true);
            expect($cookie->getSameSite())->toBe('Lax');
        });

        it('parses cookies with time-related attributes', function (): void {
            $cookie = new Cookie();
            $cookie->parse('name=value; Max-Age=3600');
            expect($cookie->getMaxAge())->toBe(3600);

            $cookie = new Cookie();
            $cookie->parse('name=value; Expires=Wed, 21 Oct 2015 07:28:00 GMT');
            expect($cookie->getExpires())->toBeGreaterThan(0);
        });

        it('parses URL-encoded and empty values', function (): void {
            $cookie = new Cookie();
            $cookie->parse('na+me=val+ue');
            expect($cookie->getName())->toBe('na me');
            expect($cookie->getValue())->toBe('val ue');

            $cookie = new Cookie();
            $cookie->parse('name=');
            expect($cookie->getName())->toBe('name');
            expect($cookie->getValue())->toBe('');
        });
    });
});

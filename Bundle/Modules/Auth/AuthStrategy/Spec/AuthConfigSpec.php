<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Auth\AuthStrategy\Spec;

use PHPCraftdream\Garnet\Bundle\Modules\Auth\AuthStrategy\AuthConfig;
use ReflectionClass;

/**
 * Builds an AuthConfig from a given config array, bypassing IniConfig/the
 * filesystem. Sets the instance as the singleton.
 */
function makeAuthConfig(array $config): AuthConfig {
    $ref = new ReflectionClass(AuthConfig::class);

    // Build the object without calling the constructor (no .ini file read)
    $instance = $ref->newInstanceWithoutConstructor();

    $configProp = $ref->getProperty('config');
    $configProp->setAccessible(true);
    $configProp->setValue($instance, $config);

    $instanceProp = $ref->getProperty('instance');
    $instanceProp->setAccessible(true);
    $instanceProp->setValue(null, $instance);

    return $instance;
}

describe('AuthConfig', function (): void {
    beforeEach(function (): void {
        $ref = new ReflectionClass(AuthConfig::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    });

    // -----------------------------------------------------------------------
    describe('get() — singleton', function (): void {
        it('get() returns the same object twice', function (): void {
            makeAuthConfig([]);
            expect(AuthConfig::get())->toBe(AuthConfig::get());
        });
    });

    // -----------------------------------------------------------------------
    describe('allowedOrigins()', function (): void {
        it('returns an empty array when origins are not set', function (): void {
            makeAuthConfig([]);
            expect(AuthConfig::get()->allowedOrigins())->toBe([]);
        });

        it('returns an array when origins is set as an array', function (): void {
            makeAuthConfig(['allowed_origins' => ['http://example.com', 'https://app.example.com']]);
            $origins = AuthConfig::get()->allowedOrigins();
            expect($origins)->toBe(['http://example.com', 'https://app.example.com']);
        });

        it('wraps a single string into an array', function (): void {
            makeAuthConfig(['allowed_origins' => 'http://example.com']);
            $origins = AuthConfig::get()->allowedOrigins();
            expect($origins)->toBe(['http://example.com']);
        });
    });

    // -----------------------------------------------------------------------
    describe('isOriginAllowed()', function (): void {
        it('returns false for any origin when the list is empty', function (): void {
            makeAuthConfig([]);
            expect(AuthConfig::get()->isOriginAllowed('http://example.com'))->toBe(false);
        });

        it('returns true for an origin from the whitelist', function (): void {
            makeAuthConfig(['allowed_origins' => ['http://example.com', 'https://app.example.com']]);
            $config = AuthConfig::get();
            expect($config->isOriginAllowed('http://example.com'))->toBe(true);
            expect($config->isOriginAllowed('https://app.example.com'))->toBe(true);
        });

        it('returns false for an origin that is not in the list', function (): void {
            makeAuthConfig(['allowed_origins' => ['http://example.com']]);
            expect(AuthConfig::get()->isOriginAllowed('http://evil.com'))->toBe(false);
        });

        it('check is case-sensitive', function (): void {
            makeAuthConfig(['allowed_origins' => ['http://example.com']]);
            expect(AuthConfig::get()->isOriginAllowed('http://EXAMPLE.COM'))->toBe(false);
            expect(AuthConfig::get()->isOriginAllowed('HTTP://example.com'))->toBe(false);
        });

        it('works for a single string origin', function (): void {
            makeAuthConfig(['allowed_origins' => 'http://only-origin.com']);
            $config = AuthConfig::get();
            expect($config->isOriginAllowed('http://only-origin.com'))->toBe(true);
            expect($config->isOriginAllowed('http://other.com'))->toBe(false);
        });

        it('strict comparison — similar URLs do not pass', function (): void {
            makeAuthConfig(['allowed_origins' => ['https://example.com']]);
            $config = AuthConfig::get();
            // http vs https
            expect($config->isOriginAllowed('http://example.com'))->toBe(false);
            // with a port
            expect($config->isOriginAllowed('https://example.com:443'))->toBe(false);
        });
    });
});

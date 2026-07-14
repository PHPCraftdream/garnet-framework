<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\IniConfig {
    use ReflectionClass;

    describe('AppConfig', function (): void {
        describe('::baseUrl()', function (): void {
            it('throws exception when base_url is empty string', function (): void {
                $reflection = new ReflectionClass(AppConfig::class);
                $constructor = $reflection->getConstructor();

                $mock = $reflection->newInstanceWithoutConstructor();
                $reflection->getProperty('ready')->setValue($mock, true);
                $reflection->getProperty('data')->setValue($mock, ['base_url' => '']);

                $expect = expect(function () use ($mock): void {
                    $mock->baseUrl();
                });

                $expect->toThrow(new \PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException());
            });

            it('throws exception when base_url is not set', function (): void {
                $reflection = new ReflectionClass(AppConfig::class);
                $constructor = $reflection->getConstructor();

                $mock = $reflection->newInstanceWithoutConstructor();
                $reflection->getProperty('ready')->setValue($mock, true);
                $reflection->getProperty('data')->setValue($mock, []);

                $expect = expect(function () use ($mock): void {
                    $mock->baseUrl();
                });

                $expect->toThrow(new \PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException());
            });

            it('removes trailing slash from base_url', function (): void {
                $reflection = new ReflectionClass(AppConfig::class);

                $mock = $reflection->newInstanceWithoutConstructor();
                $reflection->getProperty('ready')->setValue($mock, true);
                $reflection->getProperty('data')->setValue($mock, ['base_url' => 'http://example.com/']);

                $url = $mock->baseUrl();
                expect($url)->toBe('http://example.com');
            });

            it('removes multiple trailing slashes', function (): void {
                $reflection = new ReflectionClass(AppConfig::class);

                $mock = $reflection->newInstanceWithoutConstructor();
                $reflection->getProperty('ready')->setValue($mock, true);
                $reflection->getProperty('data')->setValue($mock, ['base_url' => 'http://example.com///']);

                $url = $mock->baseUrl();
                expect($url)->toBe('http://example.com');
            });

            it('does not modify URL without trailing slash', function (): void {
                $reflection = new ReflectionClass(AppConfig::class);

                $mock = $reflection->newInstanceWithoutConstructor();
                $reflection->getProperty('ready')->setValue($mock, true);
                $reflection->getProperty('data')->setValue($mock, ['base_url' => 'http://example.com']);

                $url = $mock->baseUrl();
                expect($url)->toBe('http://example.com');
            });

            it('handles complex URLs with paths', function (): void {
                $reflection = new ReflectionClass(AppConfig::class);

                $mock = $reflection->newInstanceWithoutConstructor();
                $reflection->getProperty('ready')->setValue($mock, true);
                $reflection->getProperty('data')->setValue($mock, ['base_url' => 'http://example.com/app/']);

                $url = $mock->baseUrl();
                expect($url)->toBe('http://example.com/app');
            });

            it('handles URLs with ports', function (): void {
                $reflection = new ReflectionClass(AppConfig::class);

                $mock = $reflection->newInstanceWithoutConstructor();
                $reflection->getProperty('ready')->setValue($mock, true);
                $reflection->getProperty('data')->setValue($mock, ['base_url' => 'http://localhost:8080/']);

                $url = $mock->baseUrl();
                expect($url)->toBe('http://localhost:8080');
            });
        });
    });
}

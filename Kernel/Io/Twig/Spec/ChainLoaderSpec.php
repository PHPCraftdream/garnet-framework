<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Twig {
    class MockTwigLoader implements \Twig\Loader\LoaderInterface {
        private array $templates = [];

        private bool $shouldThrow = false;

        public function __construct(array $templates = []) {
            $this->templates = $templates;
        }

        public function setShouldThrow(bool $shouldThrow): void {
            $this->shouldThrow = $shouldThrow;
        }

        public function setTemplates(array $templates): void {
            $this->templates = $templates;
        }

        public function getSourceContext(string $name): \Twig\Source {
            if ($this->shouldThrow) {
                throw new \Twig\Error\LoaderError("Error loading template: {$name}");
            }

            if (!isset($this->templates[$name])) {
                throw new \Twig\Error\LoaderError("Template not found: {$name}");
            }

            return new \Twig\Source($this->templates[$name], $name);
        }

        public function exists(string $name): bool {
            return isset($this->templates[$name]);
        }

        public function getCacheKey(string $name): string {
            if (!isset($this->templates[$name])) {
                throw new \Twig\Error\LoaderError("Template not found: {$name}");
            }

            return "cache_key_{$name}";
        }

        public function isFresh(string $name, int $time): bool {
            if (!isset($this->templates[$name])) {
                throw new \Twig\Error\LoaderError("Template not found: {$name}");
            }

            return $time < time();
        }
    }

    describe('ChainLoader', function (): void {
        describe('constructor and loader management', function (): void {
            it('creates empty loader when no loaders provided', function (): void {
                $loader = new ChainLoader([]);

                expect($loader->getLoaders())->toBe([]);
            });

            it('adds loaders from constructor', function (): void {
                $mock1 = new MockTwigLoader(['test.html' => 'content']);
                $mock2 = new MockTwigLoader(['other.html' => 'content']);

                $chain = new ChainLoader([$mock1, $mock2]);

                expect(count($chain->getLoaders()))->toBe(2);
            });

            it('adds loaders with addLoader', function (): void {
                $chain = new ChainLoader([]);
                $mock = new MockTwigLoader(['test.html' => 'content']);

                $chain->addLoader($mock);

                expect(count($chain->getLoaders()))->toBe(1);
            });

            it('prepends loaders to the beginning', function (): void {
                $mock1 = new MockTwigLoader(['test1.html' => 'content1']);
                $mock2 = new MockTwigLoader(['test2.html' => 'content2']);

                $chain = new ChainLoader([$mock1]);
                $chain->prependLoader($mock2);

                $loaders = $chain->getLoaders();
                expect($loaders[0])->toBe($mock2);
                expect($loaders[1])->toBe($mock1);
            });
        });

        describe('::exists() with caching', function (): void {
            it('returns true when template exists in any loader', function (): void {
                $mock1 = new MockTwigLoader([]);
                $mock2 = new MockTwigLoader(['test.html' => 'content']);

                $chain = new ChainLoader([$mock1, $mock2]);

                expect($chain->exists('test.html'))->toBe(true);
            });

            it('returns false when template does not exist', function (): void {
                $mock1 = new MockTwigLoader([]);
                $mock2 = new MockTwigLoader([]);

                $chain = new ChainLoader([$mock1, $mock2]);

                expect($chain->exists('test.html'))->toBe(false);
            });

            it('uses cached result on subsequent calls', function (): void {
                $mock = new MockTwigLoader(['test.html' => 'content']);
                $chain = new ChainLoader([$mock]);

                $chain->exists('test.html');
                $mock->setTemplates([]);

                $result = $chain->exists('test.html');

                expect($result)->toBe(true);
            });

            it('caches both true and false results', function (): void {
                $mock1 = new MockTwigLoader(['exists.html' => 'content']);
                $mock2 = new MockTwigLoader([]);

                $chain = new ChainLoader([$mock1, $mock2]);

                $result1 = $chain->exists('exists.html');
                $result2 = $chain->exists('not_exists.html');

                expect($result1)->toBe(true);
                expect($result2)->toBe(false);
            });
        });

        describe('::getSourceContext()', function (): void {
            it('returns source from first loader that has template', function (): void {
                $mock1 = new MockTwigLoader(['test.html' => 'content1']);
                $mock2 = new MockTwigLoader(['test.html' => 'content2']);

                $chain = new ChainLoader([$mock1, $mock2]);

                $source = $chain->getSourceContext('test.html');

                expect($source->getCode())->toBe('content1');
            });

            it('returns source from second loader when first does not have template', function (): void {
                $mock1 = new MockTwigLoader([]);
                $mock2 = new MockTwigLoader(['test.html' => 'content2']);

                $chain = new ChainLoader([$mock1, $mock2]);

                $source = $chain->getSourceContext('test.html');

                expect($source->getCode())->toBe('content2');
            });

            it('throws exception when template not found in any loader', function (): void {
                $mock1 = new MockTwigLoader([]);
                $mock2 = new MockTwigLoader([]);

                $chain = new ChainLoader([$mock1, $mock2]);

                $expect = expect(function () use ($chain): void {
                    $chain->getSourceContext('test.html');
                });

                $expect->toThrow();
            });
        });

        describe('::getCacheKey()', function (): void {
            it('returns cache key from first loader that has template', function (): void {
                $mock1 = new MockTwigLoader(['test.html' => 'content']);
                $mock2 = new MockTwigLoader(['test.html' => 'content']);

                $chain = new ChainLoader([$mock1, $mock2]);

                $key = $chain->getCacheKey('test.html');

                expect($key)->toBe('cache_key_test.html');
            });

            it('throws exception when template not found', function (): void {
                $mock1 = new MockTwigLoader([]);
                $mock2 = new MockTwigLoader([]);

                $chain = new ChainLoader([$mock1, $mock2]);

                $expect = expect(function () use ($chain): void {
                    $chain->getCacheKey('test.html');
                });

                $expect->toThrow();
            });
        });

        describe('::isFresh()', function (): void {
            it('returns freshness from first loader that has template', function (): void {
                $mock1 = new MockTwigLoader(['test.html' => 'content']);
                $mock2 = new MockTwigLoader(['test.html' => 'content']);

                $chain = new ChainLoader([$mock1, $mock2]);

                $result = $chain->isFresh('test.html', 1000);

                expect($result)->toBeA('boolean');
            });

            it('throws exception when template not found', function (): void {
                $mock1 = new MockTwigLoader([]);
                $mock2 = new MockTwigLoader([]);

                $chain = new ChainLoader([$mock1, $mock2]);

                $expect = expect(function () use ($chain): void {
                    $chain->isFresh('test.html', 1000);
                });

                $expect->toThrow();
            });
        });
    });
}

<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Link\Spec {
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use ReflectionClass;

    // Simple test stub for IDbMySQLiLink
    class MockDbLink {
        private static int $idCounter = 0;

        private int $id;

        private bool $busy = false;

        private int $pollCount = 0;

        public function __construct() {
            $this->id = self::$idCounter++;
        }

        public function getId(): int {
            return $this->id;
        }

        public function isBusy(): bool {
            return $this->busy;
        }

        public function setBusy(bool $busy): void {
            $this->busy = $busy;
        }

        public function poll(): void {
            $this->pollCount++;
        }

        public function getPollCount(): int {
            return $this->pollCount;
        }
    }

    describe('DbPool', function (): void {
        beforeEach(function (): void {
            // Reset static instance
            $reflection = new ReflectionClass(DbPool::class);
            $prop = $reflection->getProperty('instance');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        });

        afterEach(function (): void {
            // Clean up
        });

        describe('get()', function (): void {
            it('returns singleton instance', function (): void {
                $instance1 = DbPool::get();
                $instance2 = DbPool::get();

                expect($instance1)->toBeAnInstanceOf(DbPool::class);
                expect($instance1)->toBe($instance2);
            });
        });

        describe('getLinksCount()', function (): void {
            it('returns 0 when no links created', function (): void {
                $pool = DbPool::get();

                expect($pool->getLinksCount())->toBe(0);
            });

            it('tracks number of links', function (): void {
                $pool = DbPool::get();

                // Inject mock links via reflection
                $reflection = new ReflectionClass($pool);
                $linksProp = $reflection->getProperty('links');
                $linksProp->setAccessible(true);

                $linksProp->setValue($pool, [new MockDbLink(), new MockDbLink()]);

                expect($pool->getLinksCount())->toBe(2);
            });
        });

        describe('poll()', function (): void {
            it('polls all links', function (): void {
                $pool = DbPool::get();

                $link1 = new MockDbLink();
                $link2 = new MockDbLink();
                $link3 = new MockDbLink();

                // Inject mock links
                $reflection = new ReflectionClass($pool);
                $linksProp = $reflection->getProperty('links');
                $linksProp->setAccessible(true);
                $linksProp->setValue($pool, [$link1, $link2, $link3]);

                $pool->poll();

                expect($link1->getPollCount())->toBe(1);
                expect($link2->getPollCount())->toBe(1);
                expect($link3->getPollCount())->toBe(1);
            });

            it('handles empty links array', function (): void {
                $pool = DbPool::get();

                expect(function () use ($pool): void {
                    $pool->poll();
                })->not->toThrow();
            });
        });

        describe('pollFinishAll()', function (): void {
            it('completes immediately when no busy links', function (): void {
                $pool = DbPool::get();

                $link1 = new MockDbLink();
                $link1->setBusy(false);

                $link2 = new MockDbLink();
                $link2->setBusy(false);

                // Inject mock links
                $reflection = new ReflectionClass($pool);
                $linksProp = $reflection->getProperty('links');
                $linksProp->setAccessible(true);
                $linksProp->setValue($pool, [$link1, $link2]);

                expect(function () use ($pool): void {
                    $pool->pollFinishAll();
                })->not->toThrow();

                // pollFinishAll only polls busy links, not all links
                expect($link1->getPollCount())->toBe(0);
                expect($link2->getPollCount())->toBe(0);
            });

            // PENDING: DbPool::pollFinishAll() calls $link->getMysqli() and
            // hands it to the static \mysqli::poll(), which can't be unit-tested
            // without a live MySQL connection. Needs an injectable poll
            // mechanism on DbPool to be testable.
            xit('polls busy links multiple times', function (): void {
                $pool = DbPool::get();

                $link = new class() extends MockDbLink {
                    private int $pollCalls = 0;

                    public function isBusy(): bool {
                        $this->pollCalls++;

                        return $this->pollCalls < 3; // Busy for first 2 polls
                    }

                    public function getPollCalls(): int {
                        return $this->pollCalls;
                    }
                };

                // Inject mock link
                $reflection = new ReflectionClass($pool);
                $linksProp = $reflection->getProperty('links');
                $linksProp->setAccessible(true);
                $linksProp->setValue($pool, [$link]);

                $pool->pollFinishAll();

                // Should have been polled until isBusy returns false
                expect($link->getPollCalls())->toBeGreaterThan(2);
            });
        });

        describe('pollLinks()', function (): void {
            // PENDING: pollLinks() with busy=true reaches \mysqli::poll() — needs live mysqli.
            xit('polls all provided links', function (): void {
                $link1 = new MockDbLink();
                $link1->setBusy(true);
                $link2 = new MockDbLink();
                $link2->setBusy(true);
                $link3 = new MockDbLink();
                $link3->setBusy(true);

                $links = [$link1, $link2, $link3];

                DbPool::pollLinks($links, finishAll: false);

                expect($link1->getPollCount())->toBe(1);
                expect($link2->getPollCount())->toBe(1);
                expect($link3->getPollCount())->toBe(1);
            });

            // PENDING: pollLinks() reaches \mysqli::poll() for busy links.
            xit('removes non-busy links from array', function (): void {
                $link1 = new MockDbLink();
                $link1->setBusy(false);

                $link2 = new MockDbLink();
                $link2->setBusy(true);

                $link3 = new MockDbLink();
                $link3->setBusy(false);

                $links = [$link1, $link2, $link3];

                DbPool::pollLinks($links, finishAll: false);

                // Only link2 should remain (it's still busy)
                $links = array_values($links); // Re-index array
                expect(count($links))->toBe(1);
                expect($links[0]->getId())->toBe($link2->getId());
            });

            // PENDING: pollLinks() reaches \mysqli::poll() for busy links.
            xit('continues polling when finishAll is true', function (): void {
                $link1 = new class() extends MockDbLink {
                    private int $pollCalls = 0;

                    public function isBusy(): bool {
                        $this->pollCalls++;

                        return $this->pollCalls < 3;
                    }

                    public function getPollCalls(): int {
                        return $this->pollCalls;
                    }
                };

                $link2 = new MockDbLink();
                $link2->setBusy(false);

                $links = [$link1, $link2];

                DbPool::pollLinks($links, finishAll: true);

                // All links should be cleared from array
                expect(count($links))->toBe(0);

                // Link1 should be polled multiple times
                expect($link1->getPollCalls())->toBeGreaterThan(2);
            });

            // PENDING: pollLinks() reaches \mysqli::poll() for busy links.
            xit('stops early when finishAll is false', function (): void {
                $link1 = new MockDbLink();
                $link1->setBusy(true);

                $link2 = new MockDbLink();
                $link2->setBusy(true);

                $links = [$link1, $link2];

                DbPool::pollLinks($links, finishAll: false);

                // Links should only be polled once
                expect($link1->getPollCount())->toBe(1);
                expect($link2->getPollCount())->toBe(1);

                // Both links should still be in array (still busy)
                expect(count($links))->toBe(2);
            });

            it('handles empty links array', function (): void {
                $links = [];

                DbPool::pollLinks($links, finishAll: true);

                expect(count($links))->toBe(0);
            });
        });

        describe('Method existence', function (): void {
            it('has query method', function (): void {
                $pool = DbPool::get();
                expect(method_exists($pool, 'query'))->toBe(true);
            });

            it('has queryAsync method', function (): void {
                $pool = DbPool::get();
                expect(method_exists($pool, 'queryAsync'))->toBe(true);
            });

            it('has getDbConfig method', function (): void {
                $pool = DbPool::get();
                expect(method_exists($pool, 'getDbConfig'))->toBe(true);
            });

            it('has newLink method', function (): void {
                $pool = DbPool::get();
                expect(method_exists($pool, 'newLink'))->toBe(true);
            });
        });
    });
}

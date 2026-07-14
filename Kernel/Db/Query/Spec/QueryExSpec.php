<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query\Spec {
    use Aura\SqlQuery\Common\DeleteInterface;
    use Aura\SqlQuery\Common\InsertInterface;
    use Aura\SqlQuery\Common\SelectInterface;
    use Aura\SqlQuery\Common\UpdateInterface;
    use Kahlan\Plugin\Double;
    use mysqli;
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Db\Query\QueryEx;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbPool;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use ReflectionClass;
    use RuntimeException;

    // Mock for IDbMySQLiLink
    class MockDbLink implements IDbMySQLiLink {
        public bool $busy = false;

        public array $asyncCalls = [];

        public array $syncCalls = [];

        public function isBusy(): bool {
            return $this->busy;
        }

        public function queryAsync(string $sql, ?callable $callBack = null): IDbMySQLiLink {
            $this->asyncCalls[] = [$sql, $callBack];

            return $this;
        }

        public function query(string $sql, array $params = []): array|int|string|bool {
            $this->syncCalls[] = [$sql, $params];

            return [['result' => 'data']];
        }

        public function poll(): array|int|string|bool|null {
            return null;
        }

        public function getMysqli(): mysqli {
            throw new RuntimeException('Not implemented in mock');
        }

        public function getLastAffectedRows(): int {
            return 0;
        }
    }

    // Mock for IDbPool
    class MockDbPool implements IDbPool {
        public array $queryCalls = [];

        public ?MockDbLink $currentLink = null;

        public function newLink(): IDbMySQLiLink {
            throw new RuntimeException('Not implemented in mock');
        }

        public function getDbConfig(): IniConfig {
            throw new RuntimeException('Not implemented in mock');
        }

        public function queryAsync(string $sql, array $args = [], ?callable $callBack = null): IDbMySQLiLink {
            $this->queryCalls[] = ['async', $sql, $args, $callBack];
            $this->currentLink = new MockDbLink();
            $this->currentLink->queryAsync($sql, $callBack);

            return $this->currentLink;
        }

        public function query(string $sql, array $args = []): array|int|string|bool {
            $this->queryCalls[] = ['sync', $sql, $args];

            return [['result' => 'data']];
        }

        public function poll(): void {
        }

        public function pollFinishAll(): void {
        }

        public function getLinksCount(): int {
            return 0;
        }
    }

    describe('QueryEx', function (): void {
        beforeEach(function (): void {
            // Reset static instance
            $reflection = new ReflectionClass(QueryEx::class);
            $prop = $reflection->getProperty('instance');
            $prop->setValue(null, null);

            // Reset DbPool static
            $reflectionPool = new ReflectionClass(DbPool::class);
            $propPool = $reflectionPool->getProperty('instance');
            $propPool->setValue(null, null);
        });

        afterEach(function (): void {
            // Clean up
        });

        describe('get()', function (): void {
            it('returns singleton instance', function (): void {
                $instance1 = QueryEx::get();
                $instance2 = QueryEx::get();

                expect($instance1)->toBeAnInstanceOf(QueryEx::class);
                expect($instance1)->toBe($instance2);
            });
        });

        describe('exSelect() and exSelectAsync()', function (): void {
            it('executes select query synchronously', function (): void {
                $mockSelect = Double::instance(['implements' => SelectInterface::class]);
                $mockPool = new MockDbPool();

                allow($mockSelect)->toReceive('getBindValues')->andReturn(['id' => 1]);
                allow($mockSelect)->toReceive('getStatement')->andReturn('SELECT * FROM users WHERE id = ?');

                $queryEx = new QueryEx($mockPool);
                $result = $queryEx->exSelect($mockSelect);

                expect(count($mockPool->queryCalls))->toBe(1);
                expect($mockPool->queryCalls[0][0])->toBe('sync');
            });

            it('executes select query asynchronously with callback', function (): void {
                $mockSelect = Double::instance(['implements' => SelectInterface::class]);
                $mockPool = new MockDbPool();

                allow($mockSelect)->toReceive('getBindValues')->andReturn(['id' => 1]);
                allow($mockSelect)->toReceive('getStatement')->andReturn('SELECT * FROM users WHERE id = ?');

                $callback = function ($result): void {
                };

                $queryEx = new QueryEx($mockPool);
                $result = $queryEx->exSelectAsync($mockSelect, $callback);

                expect(count($mockPool->queryCalls))->toBe(1);
                expect($mockPool->queryCalls[0][0])->toBe('async');
                expect($mockPool->queryCalls[0][3])->toBe($callback);
            });
        });

        describe('selectCount() and selectCountAsync()', function (): void {
            it('executes count query synchronously', function (): void {
                $mockSelect = Double::instance(['implements' => SelectInterface::class]);
                $mockPool = new MockDbPool();

                allow($mockSelect)->toReceive('getBindValues')->andReturn([]);
                allow($mockSelect)->toReceive('getStatement')->andReturn('SELECT * FROM users');
                allow($mockSelect)->toReceive('cols')->andReturn($mockSelect);

                $queryEx = new QueryEx($mockPool);
                $result = $queryEx->selectCount($mockSelect);

                expect($result)->toBeA('integer');
            });

            it('wraps count query in SELECT with COUNT(*)', function (): void {
                $mockSelect = Double::instance(['implements' => SelectInterface::class]);
                $mockPool = new MockDbPool();

                allow($mockSelect)->toReceive('getBindValues')->andReturn([]);
                allow($mockSelect)->toReceive('getStatement')->andReturn('SELECT * FROM users');
                allow($mockSelect)->toReceive('cols')->andReturn($mockSelect);

                $queryEx = new QueryEx($mockPool);
                $queryEx->selectCount($mockSelect);

                expect($mockSelect)->toReceive('cols')->with(["count(*) as '__cnt__'"])->once();
            });

            it('executes count query asynchronously with callback', function (): void {
                $mockSelect = Double::instance(['implements' => SelectInterface::class]);
                $mockPool = new MockDbPool();

                allow($mockSelect)->toReceive('getBindValues')->andReturn([]);
                allow($mockSelect)->toReceive('getStatement')->andReturn('SELECT * FROM users');
                allow($mockSelect)->toReceive('cols')->andReturn($mockSelect);

                $callback = function ($count): void {
                };

                $queryEx = new QueryEx($mockPool);
                $result = $queryEx->selectCountAsync($mockSelect, $callback);

                expect($mockPool->queryCalls[0][0])->toBe('async');
                expect($mockSelect)->toReceive('cols')->once();
            });
        });

        describe('exInsert() and exInsertAsync()', function (): void {
            it('executes insert query synchronously and returns insert id', function (): void {
                $mockInsert = Double::instance(['implements' => InsertInterface::class]);
                $mockPool = new class() extends MockDbPool {
                    public function query(string $sql, array $args = []): array|int|string|bool {
                        $this->queryCalls[] = ['sync', $sql, $args];

                        return 123; // Return insert ID
                    }
                };

                allow($mockInsert)->toReceive('getBindValues')->andReturn(['name' => 'John']);
                allow($mockInsert)->toReceive('getStatement')->andReturn('INSERT INTO users (name) VALUES (?)');

                $queryEx = new QueryEx($mockPool);
                $result = $queryEx->exInsert($mockInsert);

                expect($result)->toBe('123');
            });

            it('returns false when insert does not return int', function (): void {
                $mockInsert = Double::instance(['implements' => InsertInterface::class]);
                $mockPool = new MockDbPool();

                allow($mockInsert)->toReceive('getBindValues')->andReturn(['name' => 'John']);
                allow($mockInsert)->toReceive('getStatement')->andReturn('INSERT INTO users (name) VALUES (?)');

                $queryEx = new QueryEx($mockPool);
                $result = $queryEx->exInsert($mockInsert);

                expect($result)->toBe(false);
            });

            it('executes insert query asynchronously', function (): void {
                $mockInsert = Double::instance(['implements' => InsertInterface::class]);
                $mockPool = new MockDbPool();

                allow($mockInsert)->toReceive('getBindValues')->andReturn(['name' => 'John']);
                allow($mockInsert)->toReceive('getStatement')->andReturn('INSERT INTO users (name) VALUES (?)');

                $queryEx = new QueryEx($mockPool);
                $queryEx->exInsertAsync($mockInsert);

                expect($mockPool->queryCalls[0][0])->toBe('async');
            });
        });

        describe('exUpdate() and exUpdateAsync()', function (): void {
            it('executes update query synchronously', function (): void {
                $mockUpdate = Double::instance(['implements' => UpdateInterface::class]);
                $mockPool = new class() extends MockDbPool {
                    public function query(string $sql, array $args = []): array|int|string|bool {
                        $this->queryCalls[] = ['sync', $sql, $args];

                        return true; // Update returns bool
                    }
                };

                allow($mockUpdate)->toReceive('getBindValues')->andReturn(['name' => 'Jane', 'id' => 1]);
                allow($mockUpdate)->toReceive('getStatement')->andReturn('UPDATE users SET name = ? WHERE id = ?');

                $queryEx = new QueryEx($mockPool);
                $result = $queryEx->exUpdate($mockUpdate);

                expect(count($mockPool->queryCalls))->toBe(1);
                expect($mockPool->queryCalls[0][0])->toBe('sync');
            });

            it('executes update query asynchronously', function (): void {
                $mockUpdate = Double::instance(['implements' => UpdateInterface::class]);
                $mockPool = new MockDbPool();

                allow($mockUpdate)->toReceive('getBindValues')->andReturn(['name' => 'Jane', 'id' => 1]);
                allow($mockUpdate)->toReceive('getStatement')->andReturn('UPDATE users SET name = ? WHERE id = ?');

                $queryEx = new QueryEx($mockPool);
                $queryEx->exUpdateAsync($mockUpdate);

                expect($mockPool->queryCalls[0][0])->toBe('async');
            });
        });

        describe('exDelete() and exDeleteAsync()', function (): void {
            it('executes delete query synchronously', function (): void {
                $mockDelete = Double::instance(['implements' => DeleteInterface::class]);
                $mockPool = new class() extends MockDbPool {
                    public function query(string $sql, array $args = []): array|int|string|bool {
                        $this->queryCalls[] = ['sync', $sql, $args];

                        return true; // Delete returns bool
                    }
                };

                allow($mockDelete)->toReceive('getBindValues')->andReturn(['id' => 1]);
                allow($mockDelete)->toReceive('getStatement')->andReturn('DELETE FROM users WHERE id = ?');

                $queryEx = new QueryEx($mockPool);
                $result = $queryEx->exDelete($mockDelete);

                expect(count($mockPool->queryCalls))->toBe(1);
                expect($mockPool->queryCalls[0][0])->toBe('sync');
            });

            it('executes delete query asynchronously', function (): void {
                $mockDelete = Double::instance(['implements' => DeleteInterface::class]);
                $mockPool = new MockDbPool();

                allow($mockDelete)->toReceive('getBindValues')->andReturn(['id' => 1]);
                allow($mockDelete)->toReceive('getStatement')->andReturn('DELETE FROM users WHERE id = ?');

                $queryEx = new QueryEx($mockPool);
                $queryEx->exDeleteAsync($mockDelete);

                expect($mockPool->queryCalls[0][0])->toBe('async');
            });
        });

        describe('exInsertIgnore() and exInsertIgnoreAsync()', function (): void {
            it('adds INSERT IGNORE to query synchronously', function (): void {
                $mockInsert = Double::instance(['implements' => InsertInterface::class]);
                $mockPool = new MockDbPool();

                allow($mockInsert)->toReceive('getBindValues')->andReturn(['email' => 'test@example.com']);
                allow($mockInsert)->toReceive('getStatement')->andReturn('INSERT INTO users (email) VALUES (?)');

                $queryEx = new QueryEx($mockPool);
                $queryEx->exInsertIgnore($mockInsert);

                expect($mockPool->queryCalls[0][0])->toBe('sync');
                expect($mockPool->queryCalls[0][1])->toContain('INSERT IGNORE');
            });

            it('adds INSERT IGNORE to query asynchronously', function (): void {
                $mockInsert = Double::instance(['implements' => InsertInterface::class]);
                $mockPool = new MockDbPool();

                allow($mockInsert)->toReceive('getBindValues')->andReturn(['email' => 'test@example.com']);
                allow($mockInsert)->toReceive('getStatement')->andReturn('INSERT INTO users (email) VALUES (?)');

                $queryEx = new QueryEx($mockPool);
                $queryEx->exInsertIgnoreAsync($mockInsert);

                expect($mockPool->queryCalls[0][0])->toBe('async');
                expect($mockPool->queryCalls[0][1])->toContain('INSERT IGNORE');
            });
        });

        describe('ex() and exAsync()', function (): void {
            it('executes raw SQL synchronously', function (): void {
                $mockPool = new MockDbPool();

                $queryEx = new QueryEx($mockPool);
                $result = $queryEx->ex('SELECT 1');

                expect(count($mockPool->queryCalls))->toBe(1);
                expect($mockPool->queryCalls[0][0])->toBe('sync');
                expect($mockPool->queryCalls[0][1])->toBe('SELECT 1');
            });

            it('executes raw SQL with parameters', function (): void {
                $mockPool = new MockDbPool();

                $queryEx = new QueryEx($mockPool);
                $result = $queryEx->ex('SELECT * FROM users WHERE id = ?', [123]);

                expect($mockPool->queryCalls[0][1])->toBe('SELECT * FROM users WHERE id = ?');
                expect($mockPool->queryCalls[0][2])->toBe([123]);
            });

            it('executes raw SQL asynchronously', function (): void {
                $mockPool = new MockDbPool();

                $callback = function ($result): void {
                };

                $queryEx = new QueryEx($mockPool);
                $result = $queryEx->exAsync('SELECT 1', [], $callback);

                expect($mockPool->queryCalls[0][0])->toBe('async');
                expect($mockPool->queryCalls[0][3])->toBe($callback);
            });
        });

        describe('exFetch() and exFetchAsync()', function (): void {
            it('executes fetch query synchronously', function (): void {
                $mockPool = new MockDbPool();

                $queryEx = new QueryEx($mockPool);
                $result = $queryEx->exFetch('SELECT * FROM users');

                expect(count($mockPool->queryCalls))->toBe(1);
                expect($mockPool->queryCalls[0][0])->toBe('sync');
            });

            it('executes fetch query with parameters', function (): void {
                $mockPool = new MockDbPool();

                $queryEx = new QueryEx($mockPool);
                $result = $queryEx->exFetch('SELECT * FROM users WHERE id = ?', [456]);

                expect($mockPool->queryCalls[0][2])->toBe([456]);
            });

            it('executes fetch query asynchronously', function (): void {
                $mockPool = new MockDbPool();

                $callback = function ($result): void {
                };

                $queryEx = new QueryEx($mockPool);
                $result = $queryEx->exFetchAsync('SELECT * FROM users', [], $callback);

                expect($mockPool->queryCalls[0][0])->toBe('async');
                expect($mockPool->queryCalls[0][3])->toBe($callback);
            });
        });
    });
}

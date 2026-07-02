<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query\Spec {
    use Aura\SqlQuery\Common\DeleteInterface;
    use Aura\SqlQuery\Common\InsertInterface;
    use Aura\SqlQuery\Common\SelectInterface;
    use Aura\SqlQuery\Common\UpdateInterface;
    use Generator;
    use Kahlan\Plugin\Double;
    use PDO;
    use PDOException;
    use PDOStatement;
    use PHPCraftdream\Garnet\Kernel\Db\Link\ExtPDO;
    use PHPCraftdream\Garnet\Kernel\Db\Query;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use ReflectionClass;

    // Test stub for ExtPDO
    class MockExtPDO extends ExtPDO {
        public $mockStatement = null;

        public function __construct() {
            // Don't call parent constructor to avoid DB connection
            $this->mockStatement = new class() extends PDOStatement {
                public $executeResult = true;

                public $fetchResults = [];

                public $executeException = null;

                private $fetchIndex = 0;

                public function execute($params = null): bool {
                    if ($this->executeException) {
                        throw $this->executeException;
                    }

                    return $this->executeResult;
                }

                public function fetch($mode = PDO::FETCH_ASSOC, ...$args): mixed {
                    if ($this->fetchIndex >= count($this->fetchResults)) {
                        return false;
                    }

                    return $this->fetchResults[$this->fetchIndex++];
                }

                public function bindValue($param, $value, $type = PDO::PARAM_STR): bool {
                    return true;
                }

                public function bindParam($param, &$var, $type = PDO::PARAM_STR, $maxLength = 0, $driverOptions = []): bool {
                    return true;
                }
            };
        }

        public function prepare($statement, $options = []): PDOStatement {
            return $this->mockStatement;
        }

        public $lastInsertIdValue = '123';

        public function lastInsertId($name = null): string|false {
            return $this->lastInsertIdValue;
        }
    }

    describe('QueryExPdo', function (): void {
        beforeEach(function (): void {
            // Reset static instance
            $reflection = new ReflectionClass(Query\QueryExPdo::class);
            $prop = $reflection->getProperty('instance');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        });

        afterEach(function (): void {
            // Clean up
        });

        describe('get()', function (): void {
            it('returns singleton instance', function (): void {
                $instance1 = Query\QueryExPdo::get();
                $instance2 = Query\QueryExPdo::get();

                expect($instance1)->toBeAnInstanceOf(Query\QueryExPdo::class);
                expect($instance1)->toBe($instance2);
            });
        });

        describe('setPDO() and getPDO()', function (): void {
            it('sets and returns PDO instance', function (): void {
                $mockPdo = new MockExtPDO();

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                expect($instance->getPDO())->toBe($mockPdo);
            });
        });

        describe('setFetch() and getFetch()', function (): void {
            it('sets and returns fetch mode', function (): void {
                $instance = Query\QueryExPdo::get();

                expect($instance->getFetch())->toBe(PDO::FETCH_ASSOC);

                $instance->setFetch(PDO::FETCH_OBJ);

                expect($instance->getFetch())->toBe(PDO::FETCH_OBJ);
            });
        });

        describe('getLastQuery()', function (): void {
            it('returns null initially', function (): void {
                $instance = Query\QueryExPdo::get();

                expect($instance->getLastQuery())->toBeNull();
            });

            it('returns LastQuery after select query', function (): void {
                $mockSelect = Double::instance(['implements' => SelectInterface::class]);
                $mockPdo = new MockExtPDO();

                allow($mockSelect)->toReceive('getBindValues')->andReturn(['id' => 1]);
                allow($mockSelect)->toReceive('getStatement')->andReturn('SELECT * FROM users WHERE id = ?');

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $instance->exSelect($mockSelect);

                $lastQuery = $instance->getLastQuery();
                expect($lastQuery)->toBeAnInstanceOf(Query\LastQuery::class);
                expect($lastQuery->getSql())->toBeA('string');
                expect($lastQuery->getParams())->toBeA('array');
            });
        });

        describe('exSelect()', function (): void {
            it('executes select query and returns results', function (): void {
                $mockSelect = Double::instance(['implements' => SelectInterface::class]);
                $mockPdo = new MockExtPDO();

                $results = [['id' => 1, 'name' => 'John'], ['id' => 2, 'name' => 'Jane']];

                allow($mockSelect)->toReceive('getBindValues')->andReturn(['id' => 1]);
                allow($mockSelect)->toReceive('getStatement')->andReturn('SELECT * FROM users WHERE id = ?');

                $mockPdo->mockStatement->fetchResults = $results;

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $result = $instance->exSelect($mockSelect);

                expect($result)->toBe($results);
            });

            it('returns empty array when no results', function (): void {
                $mockSelect = Double::instance(['implements' => SelectInterface::class]);
                $mockPdo = new MockExtPDO();

                allow($mockSelect)->toReceive('getBindValues')->andReturn([]);
                allow($mockSelect)->toReceive('getStatement')->andReturn('SELECT * FROM users');

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $result = $instance->exSelect($mockSelect);

                expect($result)->toBe([]);
            });

            it('uses configured fetch mode', function (): void {
                $mockSelect = Double::instance(['implements' => SelectInterface::class]);
                $mockPdo = new MockExtPDO();

                $resultObj = (object)['id' => 1, 'name' => 'John'];

                allow($mockSelect)->toReceive('getBindValues')->andReturn([]);
                allow($mockSelect)->toReceive('getStatement')->andReturn('SELECT * FROM users');

                $mockPdo->mockStatement->fetchResults = [$resultObj];

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);
                $instance->setFetch(PDO::FETCH_OBJ);

                $result = $instance->exSelect($mockSelect);

                expect($result)->toBe([$resultObj]);
            });
        });

        describe('selectCount()', function (): void {
            it('executes count query and returns integer', function (): void {
                $mockSelect = Double::instance(['implements' => SelectInterface::class]);
                $mockPdo = new MockExtPDO();

                $result = (object)['__cnt__' => '42'];

                allow($mockSelect)->toReceive('getBindValues')->andReturn([]);
                allow($mockSelect)->toReceive('getStatement')->andReturn('SELECT * FROM users');
                allow($mockSelect)->toReceive('cols')->andReturn($mockSelect);

                $mockPdo->mockStatement->fetchResults = [$result];

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $count = $instance->selectCount($mockSelect);

                expect($count)->toBe(42);
            });

            it('returns 0 when no results', function (): void {
                $mockSelect = Double::instance(['implements' => SelectInterface::class]);
                $mockPdo = new MockExtPDO();

                allow($mockSelect)->toReceive('getBindValues')->andReturn([]);
                allow($mockSelect)->toReceive('getStatement')->andReturn('SELECT * FROM users');
                allow($mockSelect)->toReceive('cols')->andReturn($mockSelect);

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $count = $instance->selectCount($mockSelect);

                expect($count)->toBe(0);
            });

            it('returns 0 when __cnt__ property missing', function (): void {
                $mockSelect = Double::instance(['implements' => SelectInterface::class]);
                $mockPdo = new MockExtPDO();

                $result = (object)['other' => 'value'];

                allow($mockSelect)->toReceive('getBindValues')->andReturn([]);
                allow($mockSelect)->toReceive('getStatement')->andReturn('SELECT * FROM users');
                allow($mockSelect)->toReceive('cols')->andReturn($mockSelect);

                $mockPdo->mockStatement->fetchResults = [$result];

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $count = $instance->selectCount($mockSelect);

                expect($count)->toBe(0);
            });
        });

        describe('exInsert()', function (): void {
            it('executes insert and returns last insert id', function (): void {
                $mockInsert = Double::instance(['implements' => InsertInterface::class]);
                $mockPdo = new MockExtPDO();

                allow($mockInsert)->toReceive('getBindValues')->andReturn(['name' => 'John']);
                allow($mockInsert)->toReceive('getStatement')->andReturn('INSERT INTO users (name) VALUES (?)');

                $mockPdo->lastInsertIdValue = '123';

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $insertId = $instance->exInsert($mockInsert);

                expect($insertId)->toBe('123');
            });

            it('executes insert with custom id field', function (): void {
                $mockInsert = Double::instance(['implements' => InsertInterface::class]);
                $mockPdo = new MockExtPDO();

                allow($mockInsert)->toReceive('getBindValues')->andReturn(['uuid' => 'abc-123']);
                allow($mockInsert)->toReceive('getStatement')->andReturn('INSERT INTO users (uuid) VALUES (?)');

                $mockPdo->lastInsertIdValue = 'abc-123';

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $insertId = $instance->exInsert($mockInsert, 'uuid');

                expect($insertId)->toBe('abc-123');
            });

            it('returns false when lastInsertId returns false', function (): void {
                $mockInsert = Double::instance(['implements' => InsertInterface::class]);
                $mockPdo = new MockExtPDO();

                allow($mockInsert)->toReceive('getBindValues')->andReturn(['name' => 'John']);
                allow($mockInsert)->toReceive('getStatement')->andReturn('INSERT INTO users (name) VALUES (?)');

                $mockPdo->lastInsertIdValue = false;

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $insertId = $instance->exInsert($mockInsert);

                expect($insertId)->toBe(false);
            });
        });

        describe('exUpdate()', function (): void {
            it('executes update query and returns true', function (): void {
                $mockUpdate = Double::instance(['implements' => UpdateInterface::class]);
                $mockPdo = new MockExtPDO();

                allow($mockUpdate)->toReceive('getBindValues')->andReturn(['name' => 'Jane', 'id' => 1]);
                allow($mockUpdate)->toReceive('getStatement')->andReturn('UPDATE users SET name = ? WHERE id = ?');

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $result = $instance->exUpdate($mockUpdate);

                expect($result)->toBe(true);
            });

            it('executes update query and returns false on failure', function (): void {
                $mockUpdate = Double::instance(['implements' => UpdateInterface::class]);
                $mockPdo = new MockExtPDO();

                allow($mockUpdate)->toReceive('getBindValues')->andReturn(['name' => 'Jane', 'id' => 1]);
                allow($mockUpdate)->toReceive('getStatement')->andReturn('UPDATE users SET name = ? WHERE id = ?');

                $mockPdo->mockStatement->executeResult = false;

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $result = $instance->exUpdate($mockUpdate);

                expect($result)->toBe(false);
            });
        });

        describe('exDelete()', function (): void {
            it('executes delete query and returns true', function (): void {
                $mockDelete = Double::instance(['implements' => DeleteInterface::class]);
                $mockPdo = new MockExtPDO();

                allow($mockDelete)->toReceive('getBindValues')->andReturn(['id' => 1]);
                allow($mockDelete)->toReceive('getStatement')->andReturn('DELETE FROM users WHERE id = ?');

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $result = $instance->exDelete($mockDelete);

                expect($result)->toBe(true);
            });

            it('executes delete query and returns false on failure', function (): void {
                $mockDelete = Double::instance(['implements' => DeleteInterface::class]);
                $mockPdo = new MockExtPDO();

                allow($mockDelete)->toReceive('getBindValues')->andReturn(['id' => 1]);
                allow($mockDelete)->toReceive('getStatement')->andReturn('DELETE FROM users WHERE id = ?');

                $mockPdo->mockStatement->executeResult = false;

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $result = $instance->exDelete($mockDelete);

                expect($result)->toBe(false);
            });
        });

        describe('ex()', function (): void {
            it('executes raw SQL and returns true', function (): void {
                $mockPdo = new MockExtPDO();

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $result = $instance->ex('INSERT INTO users SET name = ?', ['value']);

                expect($result)->toBe(true);
                expect($instance->getLastQuery())->not->toBeNull();
            });

            it('throws DbException on PDOException', function (): void {
                $mockPdo = new MockExtPDO();
                $mockPdo->mockStatement->executeException = new PDOException('SQL error');

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $closure = function () use ($instance): void {
                    $instance->ex('INSERT INTO users SET name = ?', ['value']);
                };

                expect($closure)->toThrow(new DbException('QueryEx error'));
            });

            it('DbException contains SQL and args', function (): void {
                $mockPdo = new MockExtPDO();
                $mockPdo->mockStatement->executeException = new PDOException('SQL error');

                $sql = 'INSERT INTO users SET name = ?';
                $args = ['value'];

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                try {
                    $instance->ex($sql, $args);
                } catch (DbException $e) {
                    expect($e->getSql())->toBe($sql);
                    expect($e->getArgs())->toBe($args);
                    expect($e->getPrevious())->toBeAnInstanceOf(PDOException::class);
                }
            });
        });

        describe('exSimpleInsert()', function (): void {
            it('executes simple insert and returns last insert id', function (): void {
                $mockPdo = new MockExtPDO();
                $mockPdo->lastInsertIdValue = '456';

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $insertId = $instance->exSimpleInsert('INSERT INTO users (name) VALUES (?)', ['John']);

                expect($insertId)->toBe('456');
            });

            it('executes simple insert with custom id field', function (): void {
                $mockPdo = new MockExtPDO();
                $mockPdo->lastInsertIdValue = 'def-456';

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $insertId = $instance->exSimpleInsert('INSERT INTO users (uuid) VALUES (?)', ['def-456'], 'uuid');

                expect($insertId)->toBe('def-456');
            });

            it('returns false when lastInsertId returns false', function (): void {
                $mockPdo = new MockExtPDO();
                $mockPdo->lastInsertIdValue = false;

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $insertId = $instance->exSimpleInsert('INSERT INTO users (name) VALUES (?)', ['John']);

                expect($insertId)->toBe(false);
            });
        });

        describe('exInsertIgnore()', function (): void {
            it('converts INSERT to INSERT IGNORE', function (): void {
                $mockInsert = Double::instance(['implements' => InsertInterface::class]);
                $mockPdo = new MockExtPDO();

                allow($mockInsert)->toReceive('getBindValues')->andReturn(['email' => 'test@example.com']);
                allow($mockInsert)->toReceive('getStatement')->andReturn('INSERT INTO users (email) VALUES (?)');

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $instance->exInsertIgnore($mockInsert);

                $lastQuery = $instance->getLastQuery();
                expect($lastQuery)->not->toBeNull();
                expect($lastQuery->getSql())->toContain('INSERT IGNORE');
            });
        });

        describe('exFetch()', function (): void {
            it('executes raw SQL and returns results', function (): void {
                $mockPdo = new MockExtPDO();

                $results = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];

                $mockPdo->mockStatement->fetchResults = $results;

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $result = $instance->exFetch('SELECT * FROM users');

                expect($result)->toBe($results);
            });

            it('returns empty array when no results', function (): void {
                $mockPdo = new MockExtPDO();

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $result = $instance->exFetch('SELECT * FROM users');

                expect($result)->toBe([]);
            });
        });

        describe('exFetchItr()', function (): void {
            it('yields results from generator', function (): void {
                $mockPdo = new MockExtPDO();

                $results = [['id' => 1], ['id' => 2], ['id' => 3]];

                $mockPdo->mockStatement->fetchResults = $results;

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $generator = $instance->exFetchItr('SELECT * FROM users');

                expect($generator)->toBeAnInstanceOf(Generator::class);

                $collected = iterator_to_array($generator);
                expect($collected)->toBe($results);
            });

            it('yields no results when query returns empty', function (): void {
                $mockPdo = new MockExtPDO();

                $instance = Query\QueryExPdo::get();
                $instance->setPDO($mockPdo);

                $generator = $instance->exFetchItr('SELECT * FROM users');

                $collected = iterator_to_array($generator);
                expect($collected)->toBe([]);
            });
        });
    });
}

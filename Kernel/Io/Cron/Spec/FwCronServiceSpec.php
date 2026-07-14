<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Cron\Spec {
    use function array_key_exists;

    use Aura\Cli\CliFactory;
    use Aura\Cli\Stdio;

    use function count;
    use function fclose;
    use function fopen;
    use function is_callable;
    use function is_resource;

    use PHPCraftdream\Garnet\Kernel\Io\Cron\FwCronService;
    use ReflectionClass;

    use function rewind;

    use RuntimeException;

    use function stream_get_contents;

    // Concrete subclass used by the tests — keeps an isolated $tasks store
    // (FwCronService::$tasks is private static; each subclass that uses
    // `static::` inherits the same array, so we wipe it in beforeEach).
    class TestCronService extends FwCronService {
        public static array $registered = [];

        public static function registerTasks(): void {
            foreach (static::$registered as $name => $cb) {
                static::registerTask($name, $cb);
            }
        }
    }

    describe('FwCronService', function (): void {
        beforeEach(function (): void {
            // Wipe the private static $tasks store via reflection so specs
            // don't leak into each other.
            $rc = new ReflectionClass(FwCronService::class);
            $prop = $rc->getProperty('tasks');
            $prop->setValue(null, []);

            TestCronService::$registered = [];

            // Build a real Stdio that writes to in-memory php://temp streams
            // so we can read its output back.
            $factory = new CliFactory();
            $this->out = fopen('php://temp', 'w+');
            $this->err = fopen('php://temp', 'w+');
            // Aura\Cli\Stdio takes Handle instances; the factory wraps them.
            $this->stdio = $factory->newStdio('php://stdin', 'php://temp', 'php://temp');
        });

        afterEach(function (): void {
            if (is_resource($this->out)) {
                fclose($this->out);
            }

            if (is_resource($this->err)) {
                fclose($this->err);
            }
        });

        // Helper: read what Stdio wrote
        $readStdout = function (Stdio $stdio): string {
            // Access the underlying Handle's resource via reflection
            $rc = new ReflectionClass($stdio);
            $stdoutHandle = $rc->getProperty('stdout');
            $handle = $stdoutHandle->getValue($stdio);

            $rh = new ReflectionClass($handle);
            $rp = $rh->getProperty('resource');
            $resource = $rp->getValue($handle);

            rewind($resource);

            return stream_get_contents($resource) ?: '';
        };
        $this->readStdout = $readStdout;

        describe('::registerTask + ::getTasks', function (): void {
            it('stores a task by name with its callback and description', function (): void {
                FwCronService::registerTask('hello', fn () => 'world', 'Greet the world');
                $tasks = FwCronService::getTasks();

                expect(array_key_exists('hello', $tasks))->toBe(true);
                expect($tasks['hello']['description'])->toBe('Greet the world');
                expect(is_callable($tasks['hello']['callback']))->toBe(true);
            });

            it('overwrites a task when registered with the same name twice', function (): void {
                FwCronService::registerTask('x', fn () => 1, 'first');
                FwCronService::registerTask('x', fn () => 2, 'second');

                $tasks = FwCronService::getTasks();
                expect(count($tasks))->toBe(1);
                expect($tasks['x']['description'])->toBe('second');
            });
        });

        describe('::runAll', function (): void {
            it('runs every registered task and reports success count', function (): void {
                TestCronService::$registered = [
                    'a' => fn () => 'ok-a',
                    'b' => fn () => 'ok-b',
                ];

                $rc = TestCronService::runAll($this->stdio);
                expect($rc)->toBe(0);

                $out = ($this->readStdout)($this->stdio);
                expect($out)->toContain('Running 2 cron task(s)');
                expect($out)->toContain('[a]');
                expect($out)->toContain('[b]');
                expect($out)->toContain('OK (ok-a)');
                expect($out)->toContain('OK (ok-b)');
                expect($out)->toContain('Done: 2/2');
            });

            it('counts a throwing task as a failure but keeps going', function (): void {
                TestCronService::$registered = [
                    'ok' => fn () => 'fine',
                    'broken' => fn () => throw new RuntimeException('boom'),
                    'also-ok' => fn () => 'fine',
                ];

                $rc = TestCronService::runAll($this->stdio);
                expect($rc)->toBe(1);   // total - success = 3 - 2

                $out = ($this->readStdout)($this->stdio);
                expect($out)->toContain('[broken]');
                expect($out)->toContain('ERROR: boom');
                expect($out)->toContain('Done: 2/3');
            });

            it('returns 0 when there are no tasks (vacuous success)', function (): void {
                TestCronService::$registered = [];

                $rc = TestCronService::runAll($this->stdio);
                expect($rc)->toBe(0);

                $out = ($this->readStdout)($this->stdio);
                expect($out)->toContain('Running 0 cron task(s)');
                expect($out)->toContain('Done: 0/0');
            });
        });

        describe('::runTask', function (): void {
            it('runs a single named task and returns 0 on success', function (): void {
                TestCronService::$registered = [
                    'one' => fn () => 'result-one',
                ];

                $rc = TestCronService::runTask('one', $this->stdio);
                expect($rc)->toBe(0);

                $out = ($this->readStdout)($this->stdio);
                expect($out)->toContain('[one]');
                expect($out)->toContain('OK (result-one)');
            });

            it('returns 1 and lists available tasks when the name is unknown', function (): void {
                TestCronService::$registered = ['known' => fn () => null];

                $rc = TestCronService::runTask('unknown', $this->stdio);
                expect($rc)->toBe(1);

                $out = ($this->readStdout)($this->stdio);
                expect($out)->toContain('Unknown task: unknown');
                expect($out)->toContain('Available tasks: known');
            });

            it('returns 1 when the task throws (error logged, not re-raised)', function (): void {
                TestCronService::$registered = [
                    'broken' => fn () => throw new RuntimeException('detail'),
                ];

                $rc = TestCronService::runTask('broken', $this->stdio);
                expect($rc)->toBe(1);

                $out = ($this->readStdout)($this->stdio);
                expect($out)->toContain('ERROR: detail');
            });
        });

        describe('::listTasks', function (): void {
            it('prints "No cron tasks registered." when empty', function (): void {
                TestCronService::$registered = [];

                TestCronService::listTasks($this->stdio);

                $out = ($this->readStdout)($this->stdio);
                expect($out)->toContain('No cron tasks registered.');
            });

            it('lists every task name with its description', function (): void {
                TestCronService::$registered = [
                    'email:flush' => fn () => null,
                    'gc:tokens' => fn () => null,
                ];

                TestCronService::listTasks($this->stdio);

                $out = ($this->readStdout)($this->stdio);
                expect($out)->toContain('Registered cron tasks');
                expect($out)->toContain('email:flush');
                expect($out)->toContain('gc:tokens');
            });
        });
    });
}

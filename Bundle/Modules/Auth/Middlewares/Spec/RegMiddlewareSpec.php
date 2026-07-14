<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares\Spec {
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares\RegMiddleware;
    use ReflectionClass;

    /**
     * Concrete subclass of the abstract RegMiddleware so we can call its
     * static methods without instantiating unresolvable dependencies.
     */
    class ConcreteRegMiddleware extends RegMiddleware {
        protected static function getEntityConfig(): \PHPCraftdream\Garnet\Kernel\Interfaces\Db\IEntityConfig {
            // Not exercised in these unit tests
            throw new LogicException('getEntityConfig() not available in unit tests');
        }

        protected static function publicDir(): string {
            return '/tmp';
        }
    }

    /**
     * Helper: invoke a protected/private static method via reflection.
     */
    function callProtected(string $class, string $method, array $args = []): mixed {
        $ref = new ReflectionClass($class);
        $m = $ref->getMethod($method);

        return $m->invoke(null, ...$args);
    }

    describe('RegMiddleware', function (): void {
        // -----------------------------------------------------------------------
        describe('wrapPageContent()', function (): void {
            it('returns the content unchanged (identity by default)', function (): void {
                $content = '<div>Hello</div>';
                $result = callProtected(ConcreteRegMiddleware::class, 'wrapPageContent', [$content]);
                expect($result)->toBe($content);
            });

            it('returns an empty string unchanged', function (): void {
                $result = callProtected(ConcreteRegMiddleware::class, 'wrapPageContent', ['']);
                expect($result)->toBe('');
            });

            it('preserves HTML entities and special characters', function (): void {
                $content = '<p>&amp; "quoted" <br></p>';
                $result = callProtected(ConcreteRegMiddleware::class, 'wrapPageContent', [$content]);
                expect($result)->toBe($content);
            });
        });

        // -----------------------------------------------------------------------
        describe('initialAccountParams()', function (): void {
            it('returns an array with token16 key', function (): void {
                $params = callProtected(ConcreteRegMiddleware::class, 'initialAccountParams', []);
                expect(isset($params['token16']))->toBe(true);
            });

            it('token16 has length 16', function (): void {
                $params = callProtected(ConcreteRegMiddleware::class, 'initialAccountParams', []);
                expect(mb_strlen($params['token16']))->toBe(16);
            });

            it('returns an array with token32 key', function (): void {
                $params = callProtected(ConcreteRegMiddleware::class, 'initialAccountParams', []);
                expect(isset($params['token32']))->toBe(true);
            });

            it('token32 has length 32', function (): void {
                $params = callProtected(ConcreteRegMiddleware::class, 'initialAccountParams', []);
                expect(mb_strlen($params['token32']))->toBe(32);
            });

            it('contains reg_time key that is a recent timestamp', function (): void {
                $before = time();
                $params = callProtected(ConcreteRegMiddleware::class, 'initialAccountParams', []);
                $after = time();
                expect($params['reg_time'] >= $before && $params['reg_time'] <= $after)->toBe(true);
            });

            it('contains last_auth_time key', function (): void {
                $before = time();
                $params = callProtected(ConcreteRegMiddleware::class, 'initialAccountParams', []);
                $after = time();
                expect($params['last_auth_time'] >= $before && $params['last_auth_time'] <= $after)->toBe(true);
            });

            it('contains last_online_time key', function (): void {
                $before = time();
                $params = callProtected(ConcreteRegMiddleware::class, 'initialAccountParams', []);
                $after = time();
                expect($params['last_online_time'] >= $before && $params['last_online_time'] <= $after)->toBe(true);
            });

            it('token16 and token32 are different on consecutive calls', function (): void {
                $a = callProtected(ConcreteRegMiddleware::class, 'initialAccountParams', []);
                $b = callProtected(ConcreteRegMiddleware::class, 'initialAccountParams', []);
                // Tokens are random; collision probability is negligible
                expect($a['token16'])->not->toBe($b['token16']);
                expect($a['token32'])->not->toBe($b['token32']);
            });
        });
    });
}

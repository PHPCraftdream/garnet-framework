<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\IoRun {
    use Closure;
    use PHPCraftdream\Garnet\Kernel\Core\Benchmark\BenchmarkLog;
    use PHPCraftdream\Garnet\Kernel\Core\Env\Env;
    use PHPCraftdream\Garnet\Kernel\Core\Env\TestScope;
    use PHPCraftdream\Garnet\Kernel\Core\Event\Event;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Settings\Settings;
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Exceptions\IoException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\LoggerException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\ILogger;
    use PHPCraftdream\Garnet\Kernel\Interfaces\ISession;
    use PHPCraftdream\Garnet\Kernel\Interfaces\ISettings;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Emitter\Emitter;
    use PHPCraftdream\Garnet\Kernel\Io\ErrorCatcher\ErrorCatcher;
    use PHPCraftdream\Garnet\Kernel\Io\Logs\Logger;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Router\RouterUriParams;
    use Psr\Http\Message\ResponseInterface;
    use Throwable;

    class IoRunWeb {
        /** Стоимость запроса в запросах к базе; только в тестовом контуре. */
        public const QUERY_COUNT_HEADER = 'X-Garnet-Db-Queries';

        protected static string $errorLogEnv = Logger::ERROR_LOGGER;

        /**
         * @return ISession
         */
        protected static function getSession(): ISession {
            return Session::get(false);
        }

        /**
         * @return ISettings
         */
        protected static function getSettings(): ISettings {
            return Settings::get();
        }

        /**
         * @return ILogger
         * @throws LoggerException
         */
        protected static function getLogger(): ILogger {
            return Logger::get(static::$errorLogEnv);
        }

        /**
         * @return void
         */
        protected static function flushAppData(): void {
            Event::get()->emit('flush_data');

            try {
                static::getSession()->flush();
            } catch (Throwable $e) {
                static::logExceptionAndGet($e, 'flush_session');
            }

            try {
                static::getSettings()->flush();
            } catch (Throwable $e) {
                static::logExceptionAndGet($e, 'flush_settings');
            }
        }

        /**
         * Checks that the output buffer is empty.
         *
         * @throws IoException
         */
        protected static function checkOutputBufferIsEmpty(): void {
            $output = '';

            if (ob_get_length()) {
                $output = ob_get_clean();
            }

            if (!empty($output)) {
                $message = 'Not empty output: ' . htmlspecialchars($output);

                throw new IoException($message);
            }
        }

        /**
         * Сколько запросов к базе стоил этот запрос — заголовком, и только
         * в авторизованном тестовом контуре.
         *
         * Зачем вообще: проверки стоимости запросов до этого мерили
         * глобальный счётчик MySQL `Questions` до и после HTTP-вызова.
         * Счётчик общий на сервер, а прогон идёт параллельными воркерами по
         * одной базе — в замер попадали запросы соседних проверок. На
         * боевом прогоне это дало +2483 запроса там, где эталон 94:
         * проверка отрапортовала об N+1-регрессии, которой не было.
         * Сложность замера должна лежать на том, кто знает правду о
         * запросе, — на самом запросе.
         *
         * Гейт — {@see TestScope::isActive()} (нужен и секретный файл на
         * сервере, и заголовок с тем же токеном) либо каталог разработки
         * ({@see Env::isDevDir()}). Второе — чтобы у локального прогона и
         * боевого был один и тот же замер, а не два разных. На боевом
         * трафике не выполняется ни то, ни другое, и заголовка нет.
         *
         * @param ResponseInterface $response
         * @param int $queriesAtStart
         * @return ResponseInterface
         */
        protected static function patchQueryCountHeader(ResponseInterface $response, int $queriesAtStart): ResponseInterface {
            if (!TestScope::isActive() && !Env::isDevDir()) {
                return $response;
            }

            $spent = DbPool::get()->getQueryCount() - $queriesAtStart;

            return $response->withHeader(self::QUERY_COUNT_HEADER, (string)$spent);
        }

        /**
         * Normalizes the response.
         *
         * @param ResponseInterface|string|null $response
         * @return ResponseInterface
         */
        protected static function normalizeResponse(ResponseInterface|string|null $response): ResponseInterface {
            return ($response instanceof ResponseInterface) ?
                $response :
                ControllerTools::ok(empty($response) ? '' : $response . '')
            ;
        }

        /**
         * @return void
         */
        protected static function clearBuffer(): void {
            if (ob_get_length()) {
                ob_get_clean();
            }
        }

        /**
         * @param Throwable $e
         * @return string
         */
        protected static function logExceptionAndGet(Throwable $e, string $logName): string {
            $message = ErrorCatcher::getExceptionStrResult($e);

            try {
                static::getLogger()->write($logName, $message);
            } catch (Throwable $e) {
            }

            return $message;
        }

        /**
         * @param IGlobalReqParams $globals
         * @param Closure(IGlobalReqParams $globals, IRouterUriParams $uriParams):(ResponseInterface|string|null) $init
         * @param Closure(IGlobalReqParams $globals, IRouterUriParams $params, string $error): ResponseInterface $errorCallBack
         * @return ResponseInterface
         */
        protected static function getResponse(
            IGlobalReqParams $globals,
            Closure $init,
            Closure $errorCallBack,
        ): ResponseInterface {
            ob_start();
            $uriParams = null;

            try {
                BenchmarkLog::log('parse_globals');
                $uriParams = RouterUriParams::fromGlobals($globals);
                DbPool::get()->poll();
                BenchmarkLog::log('before_init_app');
                $response = $init($globals, $uriParams);

                static::checkOutputBufferIsEmpty();
            } catch (Throwable $e) {
                static::clearBuffer();

                $uriParams ??= RouterUriParams::makeClear('GET');
                $message = static::logExceptionAndGet($e, 'run_controller');

                $response = $errorCallBack($globals, $uriParams, $message);
            }

            return static::normalizeResponse($response);
        }

        /**
         * @param IGlobalReqParams $globals
         * @param Closure(IGlobalReqParams $globals, IRouterUriParams $uriParams):(ResponseInterface|string|null) $init
         * @param Closure(IGlobalReqParams $globals, IRouterUriParams $params, string $error): ResponseInterface $errorCallBack
         * @return void
         */
        public static function run(
            IGlobalReqParams $globals,
            Closure $init,
            Closure $errorCallBack,
        ): void {
            $queriesAtStart = DbPool::get()->getQueryCount();

            $session = static::getSession();

            BenchmarkLog::log('read_session');

            if (!$session->isReadCookies()) {
                $session->readFromServer($globals->readServerAll());
                $session->readDataAsync();
                // Poll async session data before processing request
                $session->readDataAsyncPollFinishAll();
            }

            BenchmarkLog::log('response_start');

            $response = static::getResponse($globals, $init, $errorCallBack);
            $response = $session->patchResponse($response);

            // Dynamic responses must not be cached. HTML carries per-request
            // state (CSRF token, __GARNET_ACCOUNT_ID__, auth phase) — a stale
            // copy from disk/bfcache/shared cache is a correctness AND
            // security hazard. Static assets are served by nginx and never
            // reach IoRunWeb, so this header is safe to apply unconditionally
            // here. Individual handlers can still override before returning.
            if (!$response->hasHeader('Cache-Control')) {
                $response = $response
                    ->withHeader('Cache-Control', 'no-store, must-revalidate')
                    ->withHeader('Pragma', 'no-cache');
            }

            BenchmarkLog::log('response_ready');

            static::flushAppData();

            $response = static::patchQueryCountHeader($response, $queriesAtStart);

            // Drain async writes BEFORE releasing the response to the
            // client. Session/settings writes use exAsync (fire-and-forget)
            // and the worker pool is concurrent (nginx → 32 php-cgi):
            // if the client's next request lands on a different worker
            // before our writes commit, that worker reads stale session
            // rows. Observed as the magic-link auth race: POST /first-step
            // sets PHASE_SENT_CODE → response sent → client immediately
            // opens the magic-link in a new tab → GET on worker B reads
            // PHASE_NULL (the write hadn't flushed yet) → server renders
            // INPUT_EMAIL instead of INPUT_CODE and the auto-verify never
            // fires. The post-close poll below is now redundant for that
            // path but kept as a backstop for anything kicked off after
            // closeConnection (logs, deferred events).
            DbPool::get()->pollFinishAll();

            static::closeConnection($response);

            // Backstop: drain anything async kicked off after the response
            // emit (logs, deferred event handlers).
            DbPool::get()->pollFinishAll();

            BenchmarkLog::log('response_sent');
        }

        /**
         * @param ResponseInterface $response
         * @return void
         */
        public static function closeConnection(ResponseInterface $response): void {
            if (ob_get_length()) {
                ob_get_clean();
            }

            set_time_limit(0);
            ignore_user_abort(true);

            Emitter::emit($response);

            flush();
        }
    }
}

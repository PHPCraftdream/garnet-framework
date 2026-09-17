<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\IoRun\Spec {
    use PHPCraftdream\Garnet\Kernel\Core\Env\TestScope;
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Io\IoRun\IoRunWeb;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use ReflectionClass;

    /**
     * Стоимость запроса в запросах к базе — заголовком, и только в
     * авторизованном тестовом контуре.
     *
     * Заголовок появился из ложной тревоги: проверка стоимости
     * `~searchRecipients` мерила глобальный счётчик MySQL `Questions` до и
     * после HTTP-вызова, а счётчик общий на сервер. Параллельные воркеры
     * прогона считались тем же счётчиком, и на боевом прогоне вышло +2483
     * запроса против эталонных 94 — проверка отрапортовала об
     * N+1-регрессии, которой не было (повтор прошёл чисто).
     *
     * Гейт проверяется здесь же и в первую очередь: на боевом трафике
     * заголовка быть не должно ни при каких условиях.
     */
    describe('IoRunWeb query-count header', function (): void {
        beforeEach(function (): void {
            // Песочница вместо настоящего каталога приложения — TestScope
            // ищет файл с токеном именно там.
            $this->appDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_qcount_' . uniqid('', true);
            mkdir($this->appDir, 0o777, true);
            file_put_contents($this->appDir . DIRECTORY_SEPARATOR . '.env', "APP_NAME=MyApp\n");
            $this->tokenFile = $this->appDir . DIRECTORY_SEPARATOR . TestScope::TOKEN_FILE;

            $this->prevAppDirEnv = getenv('GARNET_APP_DIR');
            putenv('GARNET_APP_DIR=' . $this->appDir);

            $this->prevEnvToken = getenv(TestScope::ENV_TOKEN);
            putenv(TestScope::ENV_TOKEN);
            unset($_SERVER[TestScope::HEADER_KEY]);

            $this->token = 'qcount_token_0123456789';

            $reflection = new ReflectionClass(IoRunWeb::class);
            $this->patch = $reflection->getMethod('patchQueryCountHeader');
        });

        afterEach(function (): void {
            if ($this->prevAppDirEnv === false) {
                putenv('GARNET_APP_DIR');
            } else {
                putenv('GARNET_APP_DIR=' . $this->prevAppDirEnv);
            }

            if ($this->prevEnvToken === false) {
                putenv(TestScope::ENV_TOKEN);
            } else {
                putenv(TestScope::ENV_TOKEN . '=' . $this->prevEnvToken);
            }
            unset($_SERVER[TestScope::HEADER_KEY]);

            @unlink($this->tokenFile);
            @unlink($this->appDir . DIRECTORY_SEPARATOR . '.env');
            @rmdir($this->appDir);
        });

        it('не добавляет заголовок, когда тестовый контур не авторизован', function (): void {
            $response = ControllerTools::ok('body');

            $result = $this->patch->invoke(null, $response, 0);

            expect($result->hasHeader(IoRunWeb::QUERY_COUNT_HEADER))->toBe(false);
        });

        it('не добавляет заголовок, когда токен есть, а файла на сервере нет', function (): void {
            $_SERVER[TestScope::HEADER_KEY] = $this->token;

            $response = ControllerTools::ok('body');
            $result = $this->patch->invoke(null, $response, 0);

            expect($result->hasHeader(IoRunWeb::QUERY_COUNT_HEADER))->toBe(false);
        });

        it('отдаёт разницу счётчика, когда контур авторизован', function (): void {
            file_put_contents($this->tokenFile, $this->token);
            $_SERVER[TestScope::HEADER_KEY] = $this->token;

            // Начало окна отодвинуто назад на семь запросов — ровно столько
            // и должно оказаться в заголовке. Соединение при этом не
            // открывается: getQueryCount() ничего не спрашивает у базы.
            $start = DbPool::get()->getQueryCount() - 7;

            $response = ControllerTools::ok('body');
            $result = $this->patch->invoke(null, $response, $start);

            expect($result->getHeaderLine(IoRunWeb::QUERY_COUNT_HEADER))->toBe('7');
        });

        it('отдаёт ноль, когда запрос не трогал базу', function (): void {
            file_put_contents($this->tokenFile, $this->token);
            $_SERVER[TestScope::HEADER_KEY] = $this->token;

            $start = DbPool::get()->getQueryCount();

            $response = ControllerTools::ok('body');
            $result = $this->patch->invoke(null, $response, $start);

            expect($result->getHeaderLine(IoRunWeb::QUERY_COUNT_HEADER))->toBe('0');
        });

        it('не затирает остальные заголовки ответа', function (): void {
            file_put_contents($this->tokenFile, $this->token);
            $_SERVER[TestScope::HEADER_KEY] = $this->token;

            $response = ControllerTools::ok('body')->withHeader('Cache-Control', 'no-store');
            $result = $this->patch->invoke(null, $response, DbPool::get()->getQueryCount());

            expect($result->getHeaderLine('Cache-Control'))->toBe('no-store');
            expect($result->hasHeader(IoRunWeb::QUERY_COUNT_HEADER))->toBe(true);
        });
    });
}

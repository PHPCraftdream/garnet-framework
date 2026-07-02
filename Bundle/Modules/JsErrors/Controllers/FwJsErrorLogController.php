<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\JsErrors\Controllers {
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\JsErrors\Tables\FwJsErrors;
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;

    /**
     * Public endpoint for client-side JS error reports.
     *
     * Dedupes by sha256(message|file|line). Re-submits of the same
     * signature inside `ANTI_STORM_WINDOW_SEC` are silently throttled;
     * older re-submits bump `count` + refresh `last_seen_at`. Older
     * still — insert a new row.
     *
     * No CSRF: this can fire before page hydration completes.
     *
     * Wire-up (in App init):
     *   FwJsErrorLogController::setTableClass(MyApp\JsErrors::class);
     *
     * Then router uses `FwJsErrorLogController::class` directly.
     */
    class FwJsErrorLogController extends FrameworkController {
        public const URL = '/js-error/';

        private const MAX_MESSAGE_LEN = 1024;

        private const MAX_STACK_LEN = 8192;

        private const MAX_FILE_LEN = 512;

        private const MAX_URL_LEN = 1024;

        private const MAX_UA_LEN = 512;

        private const ANTI_STORM_WINDOW_SEC = 5;

        /** @var class-string<FwJsErrors>|null */
        protected static ?string $tableClass = null;

        /** @param class-string<FwJsErrors> $cls */
        public static function setTableClass(string $cls): void {
            static::$tableClass = $cls;
        }

        protected static function table(): FwJsErrors {
            if (static::$tableClass === null) {
                throw new LogicException('FwJsErrorLogController::setTableClass() must be called before use.');
            }

            return static::$tableClass::get();
        }

        public static function post__report(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $message = (string)$globals->readPostValue('message', '');
            $stack = (string)$globals->readPostValue('stack', '');
            $file = (string)$globals->readPostValue('file', '');
            $line = (int)$globals->readPostValue('line', 0);
            $col = (int)$globals->readPostValue('col', 0);
            $url = (string)$globals->readPostValue('url', '');

            $message = trim($message);

            if ($message === '' || strlen($message) > 4096) {
                return ControllerTools::JSON(['ok' => false, 'error' => 'invalid'], status: 400);
            }

            $message = mb_substr($message, 0, self::MAX_MESSAGE_LEN);
            $stack = $stack !== '' ? mb_substr($stack, 0, self::MAX_STACK_LEN) : null;
            $file = $file !== '' ? mb_substr($file, 0, self::MAX_FILE_LEN) : null;
            $url = $url !== '' ? mb_substr($url, 0, self::MAX_URL_LEN) : null;

            $ua = (string)$globals->readServerValue('HTTP_USER_AGENT', '');
            $ua = $ua !== '' ? mb_substr($ua, 0, self::MAX_UA_LEN) : null;

            $accountId = Account::fromSession()?->id();

            $hash = hash('sha256', $message . '|' . ($file ?? '') . '|' . $line);
            $now = time();

            $table = static::table();
            $existing = $table->selectOneByField('hash', $hash);

            if (is_array($existing) && isset($existing['id'])) {
                $lastSeen = (int)($existing['last_seen_at'] ?? 0);

                if ($lastSeen >= $now - self::ANTI_STORM_WINDOW_SEC) {
                    return ControllerTools::JSON(['ok' => true, 'throttled' => true]);
                }

                $table->updateById([
                    'last_seen_at' => $now,
                    'count' => (int)($existing['count'] ?? 0) + 1,
                    'account_id' => $accountId,
                ], (int)$existing['id']);

                return ControllerTools::JSON(['ok' => true, 'updated' => true]);
            }

            $table->insert([
                'hash' => $hash,
                'message' => $message,
                'stack' => $stack,
                'file' => $file,
                'line' => $line,
                'col' => $col,
                'url' => $url,
                'user_agent' => $ua,
                'account_id' => $accountId,
                'count' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);

            return ControllerTools::JSON(['ok' => true, 'inserted' => true]);
        }
    }
}

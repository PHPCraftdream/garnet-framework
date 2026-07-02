<?php
declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace PHPCraftdream\Garnet\Kernel\Io\Emitter {
    class Store {
        public static array $call = [];

        public static array $testRunHeaders = [];
    }

    function header(string $header, bool $replace = true, int $response_code = 0): void {
        Store::$testRunHeaders[] = [$header, $replace, $response_code];
    }
}

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace PHPCraftdream\Garnet\Kernel\Io\IoRun {
    use PHPCraftdream\Garnet\Kernel\Io\Emitter\Store;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;

    require_once __DIR__ . '/../../../../../vendor/autoload.php';

    function header(string $header, bool $replace = true, int $response_code = 0): void {
        Store::$testRunHeaders[] = [$header, $replace, $response_code];
    }

    function set_time_limit(int $value): void {
        Store::$call[] = "set_time_limit({$value});";
    }

    function ignore_user_abort(bool $value): void {
        $val = intval($value);

        Store::$call[] = "ignore_user_abort({$val});";
    }

    function ob_get_length(): int {
        Store::$call[] = 'ob_get_length();';

        return 100;
    }

    function ob_get_clean(): string|false {
        Store::$call[] = 'ob_get_clean();';

        return false;
    }

    function flush(): void {
        Store::$call[] = 'flush();';
    }

    echo 'Content: ';

    IoRunWeb::closeConnection(
        ControllerTools::ok('some_response! Hállö! Hí!')->withAddedHeader('header_ok', 'ok')
    );

    echo PHP_EOL, PHP_EOL;

    echo join(PHP_EOL, array_map(static fn ($a) => $a[0], Store::$testRunHeaders)), PHP_EOL, PHP_EOL;
    echo join(PHP_EOL, Store::$call);
}

<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\ErrorCatcher {
    use Closure;
    use ErrorException;
    use Throwable;

    class ErrorCatcher {
        /**
         * @var bool $disabledExceptionCatchForTest
         */
        protected static bool $disabledExceptionCatchForTest = false;

        /**
         * @var Closure(string, string):void|null
         */
        protected static ?Closure $errorCallBack = null;

        // ------------------------------------------------------------------------------------------------

        /**
         * @param string $type
         * @param string $error
         * @return void
         */
        protected static function processError(string $type, string $error): void {
            if (ob_get_length()) {
                ob_end_clean();
            }

            $type = ucfirst($type);

            if (static::$errorCallBack) {
                (static::$errorCallBack)($type, $error);

                exit;
            }

            $result = "{$type} --------------------------------------\n{$error}\n";
            echo $result;

            exit;
        }

        // ------------------------------------------------------------------------------------------------

        /**
         * @param Throwable $exception
         * @return string
         */
        public static function getExceptionStrResult(Throwable $exception): string {
            $exceptionName = $exception::class;
            $message = $exception->getMessage();
            $file = $exception->getFile();
            $line = $exception->getLine();
            $stack = static::getStackTrace($exception->getTrace(), 0);

            $lines = [
                "Exception name: {$exceptionName}.",
                "Exception message: {$message}.",
                "File: {$file}: {$line}",
            ];

            if (!empty($stack)) {
                $lines[] = 'Stack: ';
                $lines[] = '  ' . join("\n  ", $stack);
            }

            return join("\n", $lines);
        }

        // ------------------------------------------------------------------------------------------------

        /**
         * @param callable(string, string):void|null $errorCallBack
         * @return void
         * @throws ErrorException
         */
        public static function init(?callable $errorCallBack = null): void {
            if ($errorCallBack) {
                static::$errorCallBack = $errorCallBack(...);
            }

            ini_set('log_errors', 'Off');
            ini_set('display_errors', 'Off');

            set_error_handler(
                static function (int $errno, string $message, string $fileName, int $line): void {
                    throw new ErrorException($message, 0, $errno, $fileName, $line);
                },
                0xFFFFFFFF
            );

            if (!static::$disabledExceptionCatchForTest) {
                set_exception_handler(static function (Throwable $exception): void {
                    $resultMessage = static::getExceptionStrResult($exception);
                    static::processError('exception', $resultMessage);
                });
            }

            register_shutdown_function(static function () {
                /**
                 * @var array{'type': int, 'message': string, 'file': string, 'line': int} $error
                 */
                $error = error_get_last();

                if (empty($error['type']) || empty($error['message'])) {
                    return null;
                }

                static::processError('fatal', $error['message']);
            });
        }

        // ------------------------------------------------------------------------------------------------

        /**
         * @param array $stackTrace
         * @param int $clearFirstItems
         * @return array
         * @phpstan-return array<string>
         */
        protected static function getStackTrace(array $stackTrace, int $clearFirstItems): array {
            $result = [];
            $itemNames = ['file', 'line', 'class', 'function'];

            foreach ($stackTrace as $traceItem) {
                $addItem = [];

                foreach ($itemNames as $itemName) {
                    if (!empty($traceItem[$itemName])) {
                        $addItem[] = $itemName . ': ' . $traceItem[$itemName];
                    }
                }

                if (!empty($addItem)) {
                    $result[] = join(' | ', $addItem);
                }
            }

            return array_slice($result, $clearFirstItems);
        }
    }
}

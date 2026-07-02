<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Logs {
    use PHPCraftdream\Garnet\Kernel\Exceptions\LoggerException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\ILogger;

    class Logger implements ILogger {
        public const SYSTEM_LOGGER = 'SYSTEM_LOGGER';

        public const ROUTE_LOGGER = 'ROUTE_LOGGER';

        public const APP_LOGGER = 'APP_LOGGER';

        public const ADMIN_LOGGER = 'ADMIN_LOGGER';

        public const ERROR_LOGGER = 'ERROR_LOGGER';

        /**
         * @var array<string, string> $params .
         */
        protected static array $params = [];

        /**
         * @var array<string, ILogger> $loggers .
         */
        protected static array $loggers = [];

        /**
         * @param string $logsDir
         * @param string $name
         */
        protected function __construct(
            protected string $logsDir,
            protected string $name,
        ) {
            $this->logsDir = static::slashDir($logsDir);

            static::$loggers[$name] = $this;
        }

        /**
         * @param string $logsDir
         * @param string $name
         * @return void
         * @throws LoggerException
         */
        public static function define(string $logsDir, string $name): void {
            $logsDir = static::slashDir($logsDir);

            if (!is_dir($logsDir)) {
                throw new LoggerException('Directory not found: ' . $logsDir);
            }

            static::$params[$name] = $logsDir;
        }

        /**
         * @param string $name
         * @return ILogger|null
         */
        public static function silentGet(string $name): ILogger|null {
            if (empty(static::$loggers[$name])) {
                if (empty(static::$params[$name])) {
                    return null;
                }

                static::$loggers[$name] = new static(static::$params[$name], $name);
            }

            return static::$loggers[$name];
        }

        /**
         * @param string $name
         * @return ILogger
         * @throws LoggerException
         */
        public static function get(string $name): ILogger {
            if (empty(static::$loggers[$name])) {
                if (empty(static::$params[$name])) {
                    throw new LoggerException('Logger not found: ' . $name);
                }

                static::$loggers[$name] = new static(static::$params[$name], $name);
            }

            return static::$loggers[$name];
        }

        /**
         * @param string $dir
         * @return string
         */
        protected static function slashDir(string $dir): string {
            $lastSymbol = substr($dir, -1);
            $hasSlash = ($lastSymbol === '/') || ($lastSymbol === '\\');
            $ds = $hasSlash ? '' : DIRECTORY_SEPARATOR;

            return $hasSlash ? $dir : $dir . $ds;
        }

        /**
         * @param string $name
         * @param string $message
         * @return void
         */
        public function write(string $name, string $message): void {
            $dir = $this->logsDir;
            $dateStamp = date('Y-m-d');
            $hash = md5($name . $message);
            $logDir = $dir . $dateStamp . DIRECTORY_SEPARATOR;

            if (!is_dir($logDir)) {
                mkdir($logDir, 0o777);
            }

            $timeStamp = date('Y-m-d H:i:s');
            $writeMessage = $timeStamp . ": \n" . $message . PHP_EOL;
            $file = $logDir . $this->name . '-' . $name . '-' . $hash . '.log';

            if (is_file($file)) {
                return;
            }

            file_put_contents($file, $writeMessage);
        }

        /**
         * @param string $name
         * @param string $message
         * @return void
         */
        public function append(string $name, string $message): void {
            $dir = $this->logsDir;
            $dateStamp = date('Y-m-d');
            $logDir = $dir . $dateStamp . DIRECTORY_SEPARATOR;

            if (!is_dir($logDir)) {
                mkdir($logDir, 0o777);
            }

            $timeStamp = date('Y-m-d H:i:s');
            $writeMessage = $timeStamp . ": \n" . $message . PHP_EOL . PHP_EOL;
            $file = $logDir . $this->name . '-' . $name . '.log';

            file_put_contents($file, $writeMessage, FILE_APPEND);
        }
    }
}

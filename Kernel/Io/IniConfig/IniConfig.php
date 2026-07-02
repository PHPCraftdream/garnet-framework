<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\IniConfig {
    use PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IIniConfig;

    class IniConfig implements IIniConfig {
        public const ENV_APP = 'ENV_APP';

        public const ENV_DB = 'ENV_DB';

        public const ENV_EMAIL = 'ENV_EMAIL';

        public const ENV_SSH = 'ENV_SSH';

        public const ENV_DEPLOY = 'ENV_DEPLOY';

        /**
         * @var array<string, string> $initParams
         */
        protected static array $initParams = [];

        /**
         * @var array<string, static> $items.
         */
        protected static array $items = [];

        protected array $data = [];

        /**
         * Per-instance runtime overrides — checked BEFORE the parsed
         * ini data on every read. Used by request-scoped middleware
         * (e.g. WorkerScopeMiddleware) to swap config values for the
         * lifetime of a request without touching the on-disk file.
         *
         * Caller responsibility: clear (or re-set) at the start of
         * each request, since IniConfig is a long-lived singleton in
         * single-process servers (php -S, php-fpm worker).
         *
         * @var array<string, mixed>
         */
        protected array $runtimeOverrides = [];

        protected bool $ready = false;

        /**
         * @param string $filePath
         * @param string $name
         */
        protected function __construct(protected string $filePath, protected string $name) {
            static::$items[$name] = $this;
        }

        /**
         * @return void
         * @throws IniConfigException
         */
        protected function init(): void {
            if ($this->ready) {
                return;
            }

            $filePath = $this->filePath;

            if (!is_file($filePath)) {
                throw new IniConfigException('Wrong file: ' . $filePath);
            }

            $ini = parse_ini_file($filePath);

            if ($ini === false) {
                throw new IniConfigException('Error on read file: ' . $filePath);
            }

            /* @phpstan-ignore-next-line */
            if (!is_array($ini)) {
                throw new IniConfigException('Wrong data format: ' . $filePath);
            }

            $this->ready = true;
            $this->data = $ini;
        }

        /**
         * @return array
         * @throws IniConfigException
         */
        public function all(): array {
            $this->init();

            return $this->data;
        }

        /**
         * @return string
         */
        public function getFilePath(): string {
            return $this->filePath;
        }

        /**
         * @param string $name
         * @param mixed $value
         * @return void
         * @throws IniConfigException
         */
        public function set(string $name, mixed $value): void {
            $this->init();
            $this->data[$name] = $value;
        }

        /**
         * Set a request-scoped override for `$name`. Reads via param/paramXxx
         * return this value until cleared. Does NOT mutate the on-disk ini
         * data — only the in-memory override map.
         */
        public function setRuntimeOverride(string $name, mixed $value): void {
            $this->runtimeOverrides[$name] = $value;
        }

        /**
         * Drop a single runtime override (subsequent reads fall back to
         * the parsed ini value).
         */
        public function clearRuntimeOverride(string $name): void {
            unset($this->runtimeOverrides[$name]);
        }

        /**
         * Drop ALL runtime overrides on this IniConfig instance. Call at
         * request boundaries when state must not leak between requests in
         * a long-lived PHP process.
         */
        public function clearAllRuntimeOverrides(): void {
            $this->runtimeOverrides = [];
        }

        /**
         * Resolve an effective value for `$name`: runtime override (if
         * present) wins, then parsed ini data, then default. All public
         * paramXxx() readers route through this.
         */
        protected function effectiveValue(string $name, mixed $default): mixed {
            $this->init();

            if (array_key_exists($name, $this->runtimeOverrides)) {
                return $this->runtimeOverrides[$name];
            }

            return $this->data[$name] ?? $default;
        }

        /**
         * @param string $name
         * @param mixed|null $default
         * @return mixed
         * @throws IniConfigException
         */
        public function param(string $name, mixed $default = null): mixed {
            return $this->effectiveValue($name, $default);
        }

        /**
         * @param string $name
         * @param ?string $default
         * @return string
         * @throws IniConfigException
         */
        public function paramString(string $name, ?string $default = null): string {
            $result = $this->effectiveValue($name, $default);

            if (!is_string($result)) {
                throw new IniConfigException("Param '{$name}' is not string");
            }

            return $result;
        }

        /**
         * @param string $name
         * @param array $default
         * @return array
         * @throws IniConfigException
         */
        public function paramArray(string $name, array $default = []): array {
            $result = $this->effectiveValue($name, $default);

            return is_array($result) ? $result : [$result];
        }

        /**
         * @param string $name
         * @param int|null $default
         * @return int
         * @throws IniConfigException
         */
        public function paramInt(string $name, ?int $default = 0): int {
            $result = $this->effectiveValue($name, $default);
            $resultInt = (int)$result;

            $strCheck = $resultInt . '';
            $resultCheck = $result . '';

            if ($strCheck !== $resultCheck) {
                throw new IniConfigException("Param '{$name}' is not int");
            }

            return $resultInt;
        }

        /**
         * @param mixed $val
         * @return array{bool, int}
         */
        protected static function isInt(mixed $val): array {
            $resultInt = intval($val);

            $strCheck = $resultInt . '';
            $resultCheck = $val . '';

            return [$strCheck === $resultCheck, $resultInt];
        }

        /**
         * @param string $name
         * @param bool $default
         * @return bool
         * @throws IniConfigException
         */
        public function paramBool(string $name, bool $default = false): bool {
            $this->init();

            $hasOverride = array_key_exists($name, $this->runtimeOverrides);
            $hasIni = array_key_exists($name, $this->data);

            if (!$hasOverride && !$hasIni) {
                return $default;
            }

            $raw = $hasOverride ? $this->runtimeOverrides[$name] : $this->data[$name];
            $value = $raw . '';
            [$isInt, $valueInt] = self::isInt($value);

            if ($isInt) {
                return $valueInt > 0;
            }

            $value = mb_strtolower($value);

            return $value === 'true';
        }

        /**
         * @param string $name
         * @param mixed|null $default
         * @return array
         * @throws IniConfigException
         */
        public function paramWithFlag(string $name, mixed $default = null): array {
            $this->init();
            $hasOverride = array_key_exists($name, $this->runtimeOverrides);

            if ($hasOverride) {
                return [$this->runtimeOverrides[$name], true];
            }
            $value = $this->data[$name] ?? $default;
            $hasValue = array_key_exists($name, $this->data);

            return [$value, $hasValue];
        }

        /**
         * @param string $name
         * @return static
         * @throws IniConfigException
         */
        public static function get(string $name): static {
            if (empty(static::$items[$name])) {
                if (empty(static::$initParams[$name])) {
                    throw new IniConfigException('Env not found: ' . $name);
                }

                static::$items[$name] = new static(static::$initParams[$name], $name);
            }

            $that = static::$items[$name];
            $that->init();

            return $that;
        }

        /**
         * @param string $filePath
         * @param string $name
         */
        public static function define(string $filePath, string $name): void {
            static::$initParams[$name] = $filePath;
        }

        // -----------------------------------------------------------------------------------------

        /**
         * @param string $filePath
         */
        public static function defineAppIni(string $filePath): void {
            /* @phpstan-ignore-next-line */
            static::$initParams[static::ENV_APP] = $filePath;
        }

        /**
         * @return IIniConfig
         * @throws IniConfigException
         */
        public static function app(): IIniConfig {
            return static::get(static::ENV_APP);
        }

        // -----------------------------------------------------------------------------------------

        /**
         * @param string $filePath
         */
        public static function defineDbIni(string $filePath): void {
            /* @phpstan-ignore-next-line */
            static::$initParams[static::ENV_DB] = $filePath;
        }

        /**
         * @return IIniConfig
         * @throws IniConfigException
         */
        public static function db(): IIniConfig {
            return static::get(static::ENV_DB);
        }

        // -----------------------------------------------------------------------------------------

        /**
         * @param string $filePath
         */
        public static function defineEmailIni(string $filePath): void {
            /* @phpstan-ignore-next-line */
            static::$initParams[static::ENV_EMAIL] = $filePath;
        }

        /**
         * @return IIniConfig
         * @throws IniConfigException
         */
        public static function email(): IIniConfig {
            return static::get(static::ENV_EMAIL);
        }

        // -----------------------------------------------------------------------------------------

        /**
         * SSH connection parameters for CLI deploy/admin commands.
         * Loaded lazily on first `IniConfig::ssh()` call — never touched
         * by the web stack, so absent ssh.ini on prod is harmless.
         *
         * @param string $filePath
         */
        public static function defineSshIni(string $filePath): void {
            /* @phpstan-ignore-next-line */
            static::$initParams[static::ENV_SSH] = $filePath;
        }

        /**
         * @return IIniConfig
         * @throws IniConfigException
         */
        public static function ssh(): IIniConfig {
            return static::get(static::ENV_SSH);
        }

        /**
         * Resolve the SSH identity_file path.
         *
         * - empty string  → returns ''
         * - starts with ~ → expands ~ to $HOME / %USERPROFILE%
         * - absolute path → returned as-is
         * - relative path → joined with the directory of the ssh.ini file
         *
         * @throws IniConfigException
         */
        public static function sshIdentityFile(): string {
            $v = static::ssh()->paramString('identity_file', '');

            if ($v === '') {
                return '';
            }

            if (str_starts_with($v, '~')) {
                $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';

                return $home . substr($v, 1);
            }

            // Windows absolute: C:\ or C:/
            if (str_starts_with($v, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $v)) {
                return $v;
            }
            // Relative — resolve from ssh.ini location
            $iniDir = dirname(static::$initParams[static::ENV_SSH] ?? '');

            return $iniDir . DIRECTORY_SEPARATOR . $v;
        }

        // -----------------------------------------------------------------------------------------

        /**
         * Deployment-layout parameters (public_dir / framework_dir / app_dir /
         * runtime_dir / public_name) used by `php garnet bundle` and
         * `php garnet deploy:diff`. Separated from ssh.ini because these are
         * not connection params.
         */
        public static function defineDeployIni(string $filePath): void {
            /* @phpstan-ignore-next-line */
            static::$initParams[static::ENV_DEPLOY] = $filePath;
        }

        /**
         * @return IIniConfig
         * @throws IniConfigException
         */
        public static function deploy(): IIniConfig {
            return static::get(static::ENV_DEPLOY);
        }

        // -----------------------------------------------------------------------------------------
    }
}

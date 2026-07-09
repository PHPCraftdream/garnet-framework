<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\AppInit {
    use PHPCraftdream\Garnet\Kernel\Core\Env\TestScope;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Migration\CMDMigration;
    use PHPCraftdream\Garnet\Kernel\Exceptions\BundleException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\CacheException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommandException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommonException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\LoggerException;
    use PHPCraftdream\Garnet\Kernel\Io\Cache\FsCache;
    use PHPCraftdream\Garnet\Kernel\Io\Command\CMDHelp;
    use PHPCraftdream\Garnet\Kernel\Io\Command\CMDNoop;
    use PHPCraftdream\Garnet\Kernel\Io\Command\CommandClasses;
    use PHPCraftdream\Garnet\Kernel\Io\Cron\CMDCron;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use PHPCraftdream\Garnet\Kernel\Io\Logs\Logger;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;
    use ReflectionException;
    use Twig\Error\LoaderError;

    if (!defined('DS')) {
        define('DS', DIRECTORY_SEPARATOR);
    }

    abstract class BaseAppInit {
        protected static string $lang = 'RU';

        protected static ?BaseAppInit $instance = null;

        protected static ?string $publicDirInit = null;

        /**
         * @var BaseBundleInit
         */
        protected array $bundles = [];

        public readonly string $namespace;

        public readonly string $appDir;

        public readonly string $appDirName;

        public readonly string $assetsDirName;

        public readonly string $publicDir;

        public readonly string $assetsDir;

        public readonly string $publicUploadDir;

        public readonly string $assetsDirFw;

        public readonly string $assetsDirFwJs;

        public readonly string $assetsDirFwCss;

        public readonly string $assetsWebPath;

        public readonly string $publicUploadWebPath;

        public readonly string $assetsGenDir;

        public readonly string $assetsGenCssDir;

        public readonly string $assetsGenJsDir;

        public readonly string $workDir;

        public readonly string $configProdDir;

        public readonly string $configDevDir;

        public readonly string $fileCacheDir;

        public readonly string $logErrorDir;

        public readonly string $logSystemDir;

        public readonly string $logRouteDir;

        public readonly string $uploadDir;

        public readonly string $twigCacheDir;

        public readonly string $nodeModules;

        abstract public function getAppDir(): string;

        abstract public function getFrontDir(): string;

        abstract protected function defineBundles(): void;

        abstract protected function defineMigrationClass(): void;

        abstract protected function defineTwigParams(): void;

        /**
         * @param string $publicDirInit
         */
        public static function setPublicDirInit(string $publicDirInit): void {
            self::$publicDirInit = $publicDirInit;
        }

        public function getPublicDir(): string {
            // Resolution order:
            //   1. explicit setPublicDirInit() — used by `Apps/<App>/run_cmd.php`
            //      and local dev (where public/ sits inside the app dir).
            //   2. GARNET_PUBLIC_DIR env var — set by `_shared_index.php` from
            //      the runtime's .env on prod-bundle deployments.
            //   3. CLI fallback — public/ is irrelevant for `db:wipe`,
            //      `migration`, etc., so return any existing dir to satisfy
            //      the is_dir() check without forcing every CLI op to set one.
            $dir = static::$publicDirInit;

            if ($dir === null) {
                $envDir = getenv('GARNET_PUBLIC_DIR');

                if (is_string($envDir) && $envDir !== '' && is_dir($envDir)) {
                    $dir = rtrim($envDir, '/\\') . DS;
                }
            }

            if ($dir === null) {
                if (PHP_SAPI === 'cli') {
                    // CLI doesn't serve web requests — any dir works as a stub.
                    return sys_get_temp_dir() . DS;
                }

                throw new CommonException('$publicDirInit === null and GARNET_PUBLIC_DIR not set');
            }

            if (!is_dir($dir)) {
                throw new CommonException('$publicDirInit is not dir: ' . $dir);
            }

            return $dir;
        }

        /**
         * @return BaseAppInit|null
         */
        public static function getInstance(): ?BaseAppInit {
            return self::$instance;
        }

        /**
         * @param bool $isDev
         */
        public function __construct(public readonly bool $isDev) {
            $this->publicDir = $this->getPublicDir();

            // dirname() only splits on `/` on Linux (Windows also accepts
            // `\`, which is why this silently worked there) — a PHP FQCN
            // always uses `\` regardless of OS, so use string ops instead.
            // Same bug and fix as BaseBundleInit::__construct().
            $fqcn = static::class;
            $lastSep = strrpos($fqcn, '\\');
            $this->namespace = $lastSep !== false ? substr($fqcn, 0, $lastSep) : '';
            $this->appDir = static::getAppDir() . DS;
            $this->appDirName = basename($this->appDir);

            $assetsDirName = $this->appDirName;
            $this->assetsDirName = $assetsDirName;

            // Allow the deployment overlay (runtime folder) to relocate WorkDir
            // outside the app bundle by setting GARNET_WORKDIR_DIR. Falls back to
            // the in-app default for local dev.
            $envWorkDir = getenv('GARNET_WORKDIR_DIR');
            $this->workDir = $envWorkDir !== false && $envWorkDir !== ''
                ? rtrim($envWorkDir, '/\\') . DS
                : $this->appDir . 'WorkDir' . DS;
            $this->assetsWebPath = "/assets/{$assetsDirName}/";
            $this->publicUploadWebPath = "/upload/{$assetsDirName}/";
            $this->assetsDir = $this->publicDir . 'assets' . DS . $assetsDirName . DS;
            $this->publicUploadDir = $this->publicDir . 'upload' . DS . $assetsDirName . DS;
            $this->assetsDirFw = $this->publicDir . 'assets' . DS . 'framework' . DS . 'gen' . DS;
            $this->assetsDirFwJs = $this->assetsDirFw . 'js' . DS;
            $this->assetsDirFwCss = $this->assetsDirFw . 'css' . DS;
            $this->assetsGenDir = $this->assetsDir . 'gen' . DS;
            $this->assetsGenJsDir = $this->assetsGenDir . 'js' . DS;
            $this->assetsGenCssDir = $this->assetsGenDir . 'css' . DS;
            $this->configProdDir = $this->workDir . 'Config' . DS;
            $this->configDevDir = $this->workDir . 'ConfigDev' . DS;
            $this->fileCacheDir = $this->workDir . 'FileCache' . DS;
            $this->logErrorDir = $this->workDir . 'LogJournal' . DS . 'Errors' . DS;
            $this->logSystemDir = $this->workDir . 'LogJournal' . DS . 'System' . DS;
            $this->logRouteDir = $this->workDir . 'LogJournal' . DS . 'Routes' . DS;
            // Upload root swaps to an isolated sub-dir (`UploadTest`) when an
            // authorized TestScope run is active (token file + matching header
            // / GARNET_TEST_TOKEN). Resolved once at construction — the app is
            // re-instantiated per request/command, and TestScope::uploadSubDir()
            // recomputes the verdict each time, so a leaked verdict can't carry
            // between requests. Live traffic always lands on `Upload`.
            $this->uploadDir = $this->workDir . TestScope::uploadSubDir() . DS;
            $this->twigCacheDir = $this->workDir . 'TwigCache' . DS;
            // FrontBuilder ships inside the framework package, so node_modules
            // is anchored to the framework root (this file lives in
            // <framework>/Kernel/Core/AppInit), not to the app dir — the app
            // can sit anywhere relative to the framework (vendor/, sibling…).
            $this->nodeModules = dirname(__DIR__, 3) . DS . 'FrontBuilder' . DS . 'node_modules' . DS;

            static::$instance = $this;

            $this->defineBundles();
            $this->defineLangData();
        }

        /**
         * @return string
         */
        public function getLang(): string {
            return static::$lang;
        }

        /**
         * @return void
         * @throws CacheException
         * @throws LoggerException
         * @throws LoaderError
         */
        public function webInit(): void {
            $this->defineConfigs();
            $this->defineLogs();
            $this->defineCache();
            $this->initTwig();
            $this->defineTwigParams();
        }

        /**
         * @return void
         * @throws CacheException
         * @throws CommandException
         * @throws LoggerException
         * @throws ReflectionException
         */
        public function consoleInit(): void {
            $this->defineConfigs();
            $this->defineLogs();
            $this->defineCache();
            $this->initCommands();
            $this->defineMigrationClass();
        }

        protected function defineLangData(): void {
            foreach ($this->bundles as $bundle) {
                $bundle->initLang();
            }
        }

        /**
         * @return void
         */
        protected function defineConfigs(): void {
            $configDir = $this->isDev ? $this->configDevDir : $this->configProdDir;
            IniConfig::defineAppIni($configDir . 'app.ini');
            IniConfig::defineDbIni($configDir . 'db.ini');
            IniConfig::defineEmailIni($configDir . 'email.ini');
            IniConfig::defineSshIni($configDir . 'ssh.ini');
            IniConfig::defineDeployIni($configDir . 'deploy.ini');
        }

        /**
         * @return void
         * @throws LoaderError
         */
        protected function initTwig(): void {
            $systemTwigDir = dirname(__DIR__, 3) . '/Bundle/TwigTemplates/';
            $systemTwigCacheDir = $this->twigCacheDir . DS;
            $twig = Twig::get();
            $twig->defineCachePath($systemTwigCacheDir);
            $twig->addFsPath($systemTwigDir);
        }

        /**
         * @return void
         * @throws CommandException
         * @throws ReflectionException
         */
        protected static function initCommands(): void {
            CommandClasses::set('help', CMDHelp::class);
            CommandClasses::set('noop', CMDNoop::class);
            CommandClasses::set('migration', CMDMigration::class);
            CommandClasses::set('cron', CMDCron::class);
        }

        /**
         * @return void
         * @throws LoggerException
         */
        protected function defineLogs(): void {
            // These dirs are also created by touchDirs() (only called from
            // `php garnet prepare`), but every boot needs Logger::define()
            // to succeed regardless of whether `prepare` has ever run — a
            // fresh checkout that goes straight to `migration` (per the
            // documented quickstart order: config:init -> migration ->
            // build) would otherwise crash with LoggerException before
            // `build` (which triggers `prepare`) ever gets a chance to run.
            !is_dir($this->logErrorDir) && mkdir($this->logErrorDir, 0o755, true);
            !is_dir($this->logSystemDir) && mkdir($this->logSystemDir, 0o755, true);
            !is_dir($this->logRouteDir) && mkdir($this->logRouteDir, 0o755, true);

            Logger::define($this->logErrorDir, Logger::ERROR_LOGGER);
            Logger::define($this->logSystemDir, Logger::SYSTEM_LOGGER);
            Logger::define($this->logRouteDir, Logger::ROUTE_LOGGER);
        }

        /**
         * @return void
         * @throws CacheException
         */
        protected function defineCache(): void {
            // Same self-heal as defineLogs(): fileCacheDir is also created by
            // touchDirs() (only called from `php garnet prepare`), but every
            // boot calls defineCache(), which throws CacheException if the
            // dir isn't already there.
            !is_dir($this->fileCacheDir) && mkdir($this->fileCacheDir, 0o755, true);

            FsCache::defineCache($this->fileCacheDir);
        }

        /**
         * @return void
         * @throws BundleException
         * @throws CommonException
         */
        public function copyAssets(): void {
            foreach ($this->bundles as $bundle) {
                $bundle->copyAssets();
            }
        }

        /**
         * @return void
         */
        public function dumpFrontLang(): void {
            foreach ($this->bundles as $bundle) {
                $bundle->dumpFrontLang();
            }
        }

        /**
         * @return void
         */
        public function touchDirs(): void {
            !is_dir($this->publicDir) && mkdir($this->publicDir, 0o755, true);
            !is_dir($this->workDir) && mkdir($this->workDir, 0o755, true);
            !is_dir($this->assetsDir) && mkdir($this->assetsDir, 0o755, true);
            !is_dir($this->publicUploadDir) && mkdir($this->publicUploadDir, 0o755, true);
            !is_dir($this->assetsGenDir) && mkdir($this->assetsGenDir, 0o755, true);
            !is_dir($this->configProdDir) && mkdir($this->configProdDir, 0o755, true);
            !is_dir($this->configDevDir) && mkdir($this->configDevDir, 0o755, true);
            !is_dir($this->fileCacheDir) && mkdir($this->fileCacheDir, 0o755, true);
            !is_dir($this->logErrorDir) && mkdir($this->logErrorDir, 0o755, true);
            !is_dir($this->logSystemDir) && mkdir($this->logSystemDir, 0o755, true);
            !is_dir($this->logRouteDir) && mkdir($this->logRouteDir, 0o755, true);
            !is_dir($this->uploadDir) && mkdir($this->uploadDir, 0o755, true);
            !is_dir($this->twigCacheDir) && mkdir($this->twigCacheDir, 0o755, true);
            !is_dir($this->nodeModules) && mkdir($this->nodeModules, 0o755, true);
            !is_dir($this->assetsGenJsDir) && mkdir($this->assetsGenJsDir, 0o755, true);
            !is_dir($this->assetsGenCssDir) && mkdir($this->assetsGenCssDir, 0o755, true);

            !is_dir($this->assetsDirFw) && mkdir($this->assetsDirFw, 0o755, true);
            !is_dir($this->assetsDirFwJs) && mkdir($this->assetsDirFwJs, 0o755, true);
            !is_dir($this->assetsDirFwCss) && mkdir($this->assetsDirFwCss, 0o755, true);

            foreach ($this->bundles as $bundle) {
                $bundle->touchDirs();
            }

            $this->appTouchDirs();
        }

        protected function appTouchDirs(): void {
        }

        /**
         * @param BaseBundleInit $bundleInfo
         * @return void
         */
        public function addBundle(BaseBundleInit $bundleInfo): void {
            $this->bundles[$bundleInfo->bundleName] = $bundleInfo;
        }

        /**
         * @return array
         */
        public function toArray(): array {
            $result = [];

            foreach ((array)$this as $key => $value) {
                $newKey = preg_replace('#[^A-Za-z0-9]#', '', $key);

                if ($newKey === $key) {
                    $result[$key] = $value;
                }
            }

            $result['bundles'] = array_map(fn (BaseBundleInit $b) => $b->toArray(), $this->bundles);

            return $result;
        }
    }
}

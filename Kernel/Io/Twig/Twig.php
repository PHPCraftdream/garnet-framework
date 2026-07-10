<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Twig {
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use Throwable;
    use Twig\Environment;
    use Twig\Error\LoaderError;
    use Twig\Error\RuntimeError;
    use Twig\Error\SyntaxError;
    use Twig\Loader\FilesystemLoader;
    use Twig\Loader\LoaderInterface;
    use Twig\TemplateWrapper;

    class Twig {
        public const TWIG_MAIN = 'TWIG_MAIN';

        /**
         * @var Array<Twig>
         */
        protected static array $instances = [];

        /**
         * @var ?FilesystemLoader
         */
        protected ?FilesystemLoader $fwLoader;

        /**
         * @var ?ChainLoader
         */
        protected ?ChainLoader $chainLoader;

        /**
         * @var Environment|null
         */
        protected ?Environment $twig;

        protected function __construct(protected string $instanceName) {
        }

        public static function get(string $instanceName = Twig::TWIG_MAIN): Twig {
            if (empty(static::$instances[$instanceName])) {
                static::$instances[$instanceName] = new static($instanceName);
            }

            return static::$instances[$instanceName];
        }

        /**
         * @param string|TemplateWrapper $name
         * @param array $params
         * @return string
         * @throws LoaderError
         * @throws RuntimeError
         * @throws SyntaxError
         */
        public function render(string|TemplateWrapper $name, array $params = []): string {
            $env = $this->twig();

            // Per-request flip: file-stat checks (auto_reload) make sense only
            // for someone editing templates and refreshing in a browser. In
            // tests, templates are never edited during a run; in production
            // they only change on deploy (where the cache is force-cleared —
            // via an admin panel button / deploy hook). So we enable
            // auto_reload only for an actual dev-browser request.
            //
            // `php_w0` under our nginx serves both :8001 (tests) and :8002
            // (dev browser) at the same time, so the decision is made on
            // every render based on request flags, not once in the constructor.
            if (self::shouldAutoReload()) {
                $env->enableAutoReload();
            } else {
                $env->disableAutoReload();
            }

            return $env->render($name, $params);
        }

        /**
         * Reads the same signals as `GlobalReqParams::isDev()` +
         * `WorkerScopeMiddleware::HEADER_KEY`: GARNET_DEV / SERVER_SOFTWARE
         * for dev mode, HTTP_X_TEST_WORKER — for a test request.
         * Uses $_SERVER directly: Twig is a standalone singleton without DI,
         * threading IGlobalReqParams through here would be unnecessary coupling.
         */
        private static function shouldAutoReload(): bool {
            if (!empty($_SERVER['HTTP_X_TEST_WORKER'])) {
                return false;
            }

            if ((string)($_SERVER['GARNET_DEV'] ?? '') === '1') {
                return true;
            }

            $serverName = (string)($_SERVER['SERVER_NAME'] ?? '');
            $serverSoft = (string)($_SERVER['SERVER_SOFTWARE'] ?? '');
            $isLocalhost = $serverName === 'localhost' || $serverName === '127.0.0.1' || $serverName === '0.0.0.0';
            // PHP's built-in server reports "PHP/8.3.32 (Development Server)"
            // (slash-separated) in practice, not "PHP 8.3.32 ..." — accept
            // both since the exact wording isn't documented as stable.
            $isPhpServer = $serverSoft !== ''
                && (str_starts_with($serverSoft, 'PHP/') || str_starts_with($serverSoft, 'PHP '));

            return $isLocalhost && $isPhpServer;
        }

        /**
         * @param string $templatePath
         * @return void
         * @throws LoaderError
         */
        public function addFsPath(string $templatePath): void {
            $this->getFwLoader()->addPath($templatePath);
        }

        /**
         * @param string $templatePath
         * @return void
         * @throws LoaderError
         */
        public function prependFsPath(string $templatePath): void {
            $this->getFwLoader()->prependPath($templatePath);
        }

        /**
         * @param string $templateCachePath
         * @return void
         */
        public function defineCachePath(string $templateCachePath): void {
            $this->twig()->setCache($templateCachePath);
        }

        /**
         * @param LoaderInterface $loader
         * @return void
         */
        public function addLoader(LoaderInterface $loader): void {
            $this->getChainLoader()->addLoader($loader);
        }

        /**
         * @param LoaderInterface $loader
         * @return void
         */
        public function prependLoader(LoaderInterface $loader): void {
            $this->getChainLoader()->prependLoader($loader);
        }

        /**
         * @return Environment
         */
        public function twig(): Environment {
            if (empty($this->twig)) {
                $localeAware = new LocaleResolvingLoader(
                    static::getChainLoader(),
                    static fn (): string => self::resolveLocale(),
                );

                $this->twig = new Environment($localeAware, ['auto_reload' => true]);
            }

            return $this->twig;
        }

        /**
         * Reads the app's default locale from `app.ini`
         * (`default_locale=en|ru|…`). When the config isn't loaded yet —
         * early boot, MaintenanceMiddleware, or a unit test that never
         * called `IniConfig::defineAppIni` — falls back to `'en'`. The
         * Twig loader resolves `Foo/Bar.twig` → `Foo/Bar.{locale}.twig`
         * based on this value.
         */
        private static function resolveLocale(): string {
            try {
                return (string)IniConfig::app()->paramString('default_locale', 'en');
            } catch (Throwable) {
                return 'en';
            }
        }

        /**
         * @return FilesystemLoader
         */
        public function getFwLoader(): FilesystemLoader {
            if (empty($this->fwLoader)) {
                $this->fwLoader = new FilesystemLoader([]);
            }

            return $this->fwLoader;
        }

        /**
         * @return ChainLoader
         */
        protected function getChainLoader(): ChainLoader {
            if (empty($this->chainLoader)) {
                $chainLoader = new ChainLoader([]);
                $fwLoader = static::getFwLoader();
                $chainLoader->addLoader($fwLoader);

                $this->chainLoader = $chainLoader;
            }

            return $this->chainLoader;
        }
    }
}

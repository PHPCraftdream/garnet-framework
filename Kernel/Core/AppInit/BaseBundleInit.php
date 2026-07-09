<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Core\AppInit {
    use PHPCraftdream\Garnet\Kernel\Core\Tools\FsTools;
    use PHPCraftdream\Garnet\Kernel\Exceptions\BundleException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommonException;
    use PHPCraftdream\Garnet\Kernel\Io\I18n\I18nFrontendDumper;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;
    use Twig\Error\LoaderError;

    if (!defined('DS')) {
        define('DS', DIRECTORY_SEPARATOR);
    }

    abstract class BaseBundleInit {
        protected static ?BaseBundleInit $instance = null;

        public readonly string $namespace;

        public readonly string $bundleDir;

        public readonly string $bundleAssetsDir;

        public readonly string $frontendDir;

        public readonly string $frontendTrDir;

        public readonly string $bundleAssetsSrc;

        public readonly string $twigEnv;

        public readonly string $twigTemplatesDir;

        public readonly string $twigTemplatesSubspaceDir;

        public readonly string $twigCacheDir;

        public readonly string $bundleName;

        public readonly string $assetsWebPath;

        public readonly bool $isFrameworkBundle;

        /**
         * @param string $workDir
         * @param BaseAppInit $app
         * @throws LoaderError
         */
        public function __construct(
            public readonly string $workDir,
            protected readonly BaseAppInit $app,
        ) {
            // A PHP FQCN uses `\` as its separator regardless of OS, but
            // basename()/dirname() only split on `/` on Linux (Windows also
            // accepts `\`, which is why this silently "worked" there and
            // only broke in CI: on Linux, basename(static::class) returned
            // the whole FQCN unchanged instead of the short class name,
            // corrupting every generated *Gen.php path with a literal
            // backslash-namespace prefix baked into the filename).
            $fqcn = static::class;
            $lastSep = strrpos($fqcn, '\\');
            $this->bundleName = $lastSep !== false ? substr($fqcn, $lastSep + 1) : $fqcn;
            $this->namespace = $lastSep !== false ? substr($fqcn, 0, $lastSep) : '';
            $this->twigEnv = $this->bundleName;

            if ($this->bundleName === 'Framework') {
                $isFrameworkBundle = true;
                $assetsWebPath = dirname($app->assetsWebPath) . '/framework/assets/';
                $bundleAssetsDir = dirname($app->assetsDir) . DS . 'framework' . DS . 'assets' . DS;
            } else {
                $isFrameworkBundle = false;
                $assetsWebPath = $app->assetsWebPath . 'assets/' . $this->bundleName . '/';
                $bundleAssetsDir = $app->assetsDir . 'assets' . DS . $this->bundleName . DS;
            }

            $this->isFrameworkBundle = $isFrameworkBundle;
            $this->assetsWebPath = $assetsWebPath;
            $this->bundleAssetsDir = $bundleAssetsDir;

            $this->bundleDir = static::getBundleDir() . DS;
            $this->frontendDir = $this->getFrontendDir($this->app, $this->bundleName);
            $this->frontendTrDir = $this->frontendDir . 'I18nGen' . DS;
            $this->bundleAssetsSrc = $this->frontendDir . 'Assets' . DS;
            $this->twigTemplatesDir = $this->bundleDir . 'TwigTemplates' . DS;
            $this->twigTemplatesSubspaceDir = $this->twigTemplatesDir . $this->bundleName . DS;
            $this->twigCacheDir = $this->workDir . 'TwigCache' . DS . $this->bundleName . DS;

            static::$instance = $this;

            $this->init();
            $this->afterConstruct();
        }

        protected function getFrontendDir(BaseAppInit $app, string $bundleName): string {
            return $app->getFrontDir() . $app->appDirName . DS . $bundleName . DS;
        }

        abstract public static function getBundleDir(): string;

        abstract public function initLang(): void;

        abstract public function getLangData(): array;

        /**
         * @return void
         * @throws CommonException
         */
        public function dumpFrontLang(): void {
            I18nFrontendDumper::dump(
                $this->getLangData(),
                $this->frontendTrDir,
                $this->bundleName,
                $this->bundleName === 'Framework',
            );
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

            return $result;
        }

        /**
         * @return BaseBundleInit|null
         * @throws BundleException
         */
        public static function getInstance(): ?BaseBundleInit {
            if (empty(static::$instance)) {
                throw new BundleException('Bundle instance not found');
            }

            return self::$instance;
        }

        protected function afterConstruct(): void {
        }

        /**
         * @throws LoaderError
         */
        protected function init(): void {
            Twig::get()->prependFsPath($this->twigTemplatesDir);
        }

        /**
         * @return Twig
         */
        public function getTwig(): Twig {
            return Twig::get();
        }

        /**
         * @return void
         */
        public function touchDirs(): void {
            !is_dir($this->bundleDir) && mkdir($this->bundleDir, 0o755, true);
            !is_dir($this->frontendDir) && mkdir($this->frontendDir, 0o755, true);
            !is_dir($this->twigTemplatesDir) && mkdir($this->twigTemplatesDir, 0o755, true);
            !is_dir($this->twigTemplatesSubspaceDir) && mkdir($this->twigTemplatesSubspaceDir, 0o755, true);
            !is_dir($this->twigCacheDir) && mkdir($this->twigCacheDir, 0o755, true);
            !is_dir($this->bundleAssetsDir) && mkdir($this->bundleAssetsDir, 0o755, true);
            !is_dir($this->bundleAssetsSrc) && mkdir($this->bundleAssetsSrc, 0o755, true);
            !is_dir($this->frontendTrDir) && mkdir($this->frontendTrDir, 0o755, true);
        }

        /**
         * @return void
         * @throws BundleException
         * @throws CommonException
         */
        public function copyAssets(): void {
            $resultFiles = [];

            FsTools::copyDirectory(
                $this->bundleAssetsSrc,
                $this->bundleAssetsDir,
                afterCopy: function ($src, $dest) use (&$resultFiles): void {
                    $resultFiles[] = $dest;
                },
            );

            $methods = InitTools::makeMethodsFromFiles($resultFiles, $this->bundleAssetsDir, $this->assetsWebPath);
            InitTools::saveAssetsClass($methods, $this->bundleDir, $this->bundleName, $this->namespace);
        }
    }
}

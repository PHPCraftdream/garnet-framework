<?php declare(strict_types=1);

namespace PHPCraftdream\Application {
    use Detection\MobileDetect;
    use PHPCraftdream\Application\Common\Backend\CommonController;
    use PHPCraftdream\Application\Common\Common;
    use PHPCraftdream\Application\Common\Services\AppCronService;
    use PHPCraftdream\Application\Dashboard\Dashboard;
    use PHPCraftdream\Application\Foreground\Backend\AccountController;
    use PHPCraftdream\Application\Foreground\Backend\MainController;
    use PHPCraftdream\Application\Foreground\Foreground;
    use PHPCraftdream\Application\Migrations\AppMigration;
    use PHPCraftdream\Garnet\Bundle\Framework;
    use PHPCraftdream\Garnet\Bundle\FrameworkJsGen;
    use PHPCraftdream\Garnet\Bundle\I18n\FwI18n;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares\EmailAuthMiddleware;
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseAppInit;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Migration\CMDMigration;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Cron\CMDCron;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\AppConfig;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use PHPCraftdream\Garnet\Kernel\Io\Router\Router;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;
    use Psr\Http\Message\ResponseInterface;

    if (!defined('DS')) {
        define('DS', DIRECTORY_SEPARATOR);
    }

    class Application extends BaseAppInit {
        public function getAppDir(): string {
            return __DIR__;
        }

        public function getFrontDir(): string {
            return dirname(__DIR__, 2) . DS . 'FrontBuilder' . DS;
        }

        public function runWebApp(IGlobalReqParams $globals, IRouterUriParams $uriParams): ResponseInterface|string|null {
            $router = new Router(
                [CommonController::class, 'not_found_404'](...)
            );

            // $router->add('dev/{page}', DevController::class);
            // $router->add('dev', DevController::class);

            $router->add('/', MainController::class);

            // Reference route for the framework's built-in email-code auth
            // flow (Bundle/Modules/Auth) — `/` itself is deliberately left
            // public so a fresh scaffold's homepage isn't a login wall. Move
            // this middleware onto `/` (or any other route) instead if your
            // app should require auth everywhere.
            $router->add(AccountController::URL, AccountController::class, [
                [EmailAuthMiddleware::class, 'authOnly'],
            ]);

            return $router->dispatch($globals, $uriParams);
        }

        protected function defineBundles(): void {
            $this->bundles = [
                new Framework($this->workDir, $this),
                new Common($this->workDir, $this),
                new Dashboard($this->workDir, $this),
                new Foreground($this->workDir, $this),
            ];
        }

        protected function defineMigrationClass(): void {
            CMDMigration::setMigrationClass(AppMigration::class);
            CMDCron::setCronServiceClass(AppCronService::class);
        }

        protected function defineTwigParams(): void {
            // FwI18n::getInstance()->setLang('EN');

            $twigParams = TwigParams::init();

            $twigParams->set(TwigParams::DEF_LAYOUT_PARAMS, function (): array {
                $appConf = AppConfig::get(IniConfig::ENV_APP);

                return [
                    'lang' => FwI18n::getInstance()->getLang(),
                    'base_url' => $appConf->baseUrl(),
                    'upload_dir' => $this->publicUploadWebPath,
                    'csrf' => Session::touchCSRF_(),
                    'title' => $appConf->paramString('title'),
                    'styles_assets' => [],
                    'js_assets' => [
                        FrameworkJsGen::framework(),
                    ],
                    'isMobile' => (new MobileDetect())->isMobile(),
                ];
            });

            $twigParams->set(TwigParams::DEF_EMAIL_PARAMS, function (): array {
                $appConf = AppConfig::get(IniConfig::ENV_APP);

                return [
                    'content_align' => 'left',
                    'head_align' => 'left',
                    'block_title_align' => 'left',
                    'bottom_align' => 'center',
                    'head' => $appConf->paramString('title'),
                ];
            });
        }
    }
}

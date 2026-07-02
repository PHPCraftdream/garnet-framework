<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Dashboard {
    use PHPCraftdream\Application\Dashboard\I18n\DashboardI18n;
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseAppInit;
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseBundleInit;
    use PHPCraftdream\Garnet\Kernel\Exceptions\I18nException;

    class Dashboard extends BaseBundleInit {
        public static function getBundleDir(): string {
            return __DIR__;
        }

        protected function getFrontendDir(BaseAppInit $app, string $bundleName): string {
            return static::getBundleDir() . DS . 'Front' . DS;
        }

        /**
         * @return void
         * @throws I18nException
         */
        public function initLang(): void {
            $tr = DashboardI18n::init();

            $this->getTwig()->twig()->addGlobal('trDashboard', $tr);
        }

        /**
         * @return array
         */
        public function getLangData(): array {
            return DashboardI18n::getInstance()->getLangData();
        }
    }
}

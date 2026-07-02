<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Foreground {
    use PHPCraftdream\Application\Foreground\I18n\ForegroundI18n;
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseAppInit;
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseBundleInit;
    use PHPCraftdream\Garnet\Kernel\Exceptions\I18nException;

    class Foreground extends BaseBundleInit {
        public static function getBundleDir(): string {
            return __DIR__;
        }

        protected function getFrontendDir(BaseAppInit $app, string $bundleName): string {
            return $app->getAppDir() . DS . 'Front' . DS;
        }

        /**
         * @return void
         * @throws I18nException
         */
        public function initLang(): void {
            $tr = ForegroundI18n::init();

            $this->getTwig()->twig()->addGlobal('trForeground', $tr);
        }

        /**
         * @return array
         */
        public function getLangData(): array {
            return ForegroundI18n::getInstance()->getLangData();
        }
    }
}

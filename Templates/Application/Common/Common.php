<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Common {
    use PHPCraftdream\Application\Common\I18n\CommonI18n;
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseAppInit;
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseBundleInit;
    use PHPCraftdream\Garnet\Kernel\Exceptions\I18nException;

    class Common extends BaseBundleInit {
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
            $tr = CommonI18n::init();

            $this->getTwig()->twig()->addGlobal('trCommon', $tr);
        }

        /**
         * @return array
         */
        public function getLangData(): array {
            return CommonI18n::getInstance()->getLangData();
        }
    }
}

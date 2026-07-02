<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle {
    use PHPCraftdream\Garnet\Bundle\I18n\FwI18n;
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseAppInit;
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseBundleInit;
    use PHPCraftdream\Garnet\Kernel\Exceptions\I18nException;

    class Framework extends BaseBundleInit {
        public static function getBundleDir(): string {
            return __DIR__;
        }

        protected function getFrontendDir(BaseAppInit $app, string $bundleName): string {
            // FrameworkBundle frontend now lives next to its backend at
            // Framework/Bundle/Front/, matching the
            // Apps/<App>/<Bundle>/Front/ pattern app code already uses.
            // The bundleName ("Framework") stays the asset / CSS / JS gen
            // identifier — only the source-on-disk location moves.
            return static::getBundleDir() . DS . 'Front' . DS;
        }

        /**
         * @return void
         * @throws I18nException
         */
        public function initLang(): void {
            $tr = FwI18n::init();

            $this->getTwig()->twig()->addGlobal('tr', $tr);
        }

        /**
         * @return array
         */
        public function getLangData(): array {
            return FwI18n::getInstance()->getLangData();
        }
    }
}

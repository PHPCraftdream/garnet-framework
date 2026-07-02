<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\I18n {
    use PHPCraftdream\Garnet\Kernel\Interfaces\II18n;
    use PHPCraftdream\Garnet\Kernel\Io\I18n\GarnetI18n;

    class FwI18n extends GarnetI18n {
        protected string $lang = I18nDataRu::LANG;

        public function initData(): II18n {
            return $this
                ->addLangData(I18nDataEn::LANG, I18nDataEn::$data)
                ->addLangData(I18nDataRu::LANG, I18nDataRu::$data);
        }

        /**
         * @return II18n
         */
        public static function getInstance(): II18n {
            if (empty(static::$instance)) {
                static::$instance = new FwI18n();
            }

            return static::$instance;
        }
    }
}

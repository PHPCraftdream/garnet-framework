<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Common\I18n {
    use PHPCraftdream\Garnet\Kernel\Interfaces\Core\II18n;
    use PHPCraftdream\Garnet\Kernel\Io\Render\I18n\GarnetI18n;

    class CommonI18n extends GarnetI18n {
        protected static ?II18n $instance = null;

        protected string $lang = CommonI18nDataRu::LANG;

        public function initData(): II18n {
            return $this
                ->addLangData(CommonI18nDataRu::LANG, CommonI18nDataRu::$data)
                ->addLangData(CommonI18nDataEn::LANG, CommonI18nDataEn::$data);
        }

        /**
         * @return II18n
         */
        public static function getInstance(): II18n {
            if (empty(static::$instance)) {
                static::$instance = new CommonI18n();
            }

            return static::$instance;
        }
    }
}

<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Dashboard\I18n {
    use PHPCraftdream\Garnet\Kernel\Interfaces\II18n;
    use PHPCraftdream\Garnet\Kernel\Io\I18n\GarnetI18n;

    class DashboardI18n extends GarnetI18n {
        protected static ?II18n $instance = null;

        protected string $lang = DashboardI18nDataRu::LANG;

        public function initData(): II18n {
            return $this
                ->addLangData(DashboardI18nDataRu::LANG, DashboardI18nDataRu::$data)
                ->addLangData(DashboardI18nDataEn::LANG, DashboardI18nDataEn::$data);
        }

        /**
         * @return II18n
         */
        public static function getInstance(): II18n {
            if (empty(static::$instance)) {
                static::$instance = new DashboardI18n();
            }

            return static::$instance;
        }
    }
}

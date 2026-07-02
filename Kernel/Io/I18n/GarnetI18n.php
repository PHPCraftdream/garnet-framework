<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\I18n {
    use PHPCraftdream\Garnet\Kernel\Exceptions\LoggerException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\II18n;
    use PHPCraftdream\Garnet\Kernel\Io\Logs\Logger;

    abstract class GarnetI18n implements II18n {
        protected string $lang = 'RU';

        protected bool $initiated = false;

        /**
         * @var $langData array
         */
        protected array $langData = [];

        /**
         * @return array
         */
        public function getLangData(): array {
            return $this->langData;
        }

        /**
         * @var $instance II18n|null
         */
        protected static ?II18n $instance = null;

        /**
         * @return bool
         */
        public function isInitiated(): bool {
            return $this->initiated;
        }

        /**
         * @return II18n
         */
        public function setInitiated(): II18n {
            $this->initiated = true;

            return $this;
        }

        /**
         * @return II18n
         */
        abstract public function initData(): II18n;

        /**
         * @return GarnetI18n
         */
        abstract public static function getInstance(): II18n;

        /**
         * @return II18n
         */
        public static function init(): II18n {
            $instance = static::getInstance();

            if ($instance->isInitiated()) {
                return $instance;
            }

            $instance->initData();
            $instance->setInitiated();

            return $instance;
        }

        /**
         * @param string $id
         * @param array $args
         * @return string
         * @throws LoggerException
         */
        public static function t(string $id, array $args = []): string {
            return static::getInstance()->tr($id, $args);
        }

        /**
         * @return string
         */
        public function getLang(): string {
            return $this->lang;
        }

        /**
         * @param string $lang
         * @return II18n
         */
        public function setLang(string $lang): II18n {
            $this->lang = $lang;

            return $this;
        }

        public function addLangData(string $lang, array $data): II18n {
            $this->langData[$lang] = $data;

            return $this;
        }

        /**
         * @param string $id
         * @param array ...$args
         * @return string
         * @throws LoggerException
         */
        public function tr(string $id, array $args = []): string {
            $lang = $this->lang;

            if (!array_key_exists($lang, $this->langData)) {
                Logger::get(Logger::ERROR_LOGGER)->write('i18n', "Lang data not found for [{$lang}].");

                return $id;
            }

            if (!array_key_exists($id, $this->langData[$lang])) {
                Logger::get(Logger::ERROR_LOGGER)->write('i18n', "translation not found for [{$id}].");

                return $id;
            }

            $tr = $this->langData[$lang][$id];

            if (!is_string($tr)) {
                Logger::get(Logger::ERROR_LOGGER)->write('i18n', "translation not string for [{$id}].");

                return $id;
            }

            if (empty($args)) {
                return $tr;
            }

            return sprintf($tr, ...$args);
        }

        /**
         * @param string $name
         * @param array $arguments
         * @return string
         * @throws LoggerException
         */
        public function __call(string $name, array $arguments): string {
            return $this->tr($name, $arguments);
        }
    }
}

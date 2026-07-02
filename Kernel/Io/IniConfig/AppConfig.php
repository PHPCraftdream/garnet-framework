<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\IniConfig {
    use PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IAppConfig;

    class AppConfig extends IniConfig implements IAppConfig {
        public const BASE_URL_PARAM = 'base_url';

        public function baseUrl(): string {
            $param = static::BASE_URL_PARAM;
            $baseUrl = rtrim($this->paramString($param, ''), '/');

            if (empty($baseUrl)) {
                throw new IniConfigException("Param '{$param}' is empty");
            }

            return $baseUrl;
        }

        public static function get(string $name): static {
            $cached = static::$items[$name] ?? null;

            if (!($cached instanceof static)) {
                unset(static::$items[$name]);
            }

            return parent::get($name);
        }
    }
}

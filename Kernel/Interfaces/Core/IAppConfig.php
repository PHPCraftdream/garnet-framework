<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces\Core {
    use PHPCraftdream\Garnet\Kernel\Interfaces\Io\IIniConfig;

    interface IAppConfig extends IIniConfig {
        public function baseUrl(): string;
    }
}

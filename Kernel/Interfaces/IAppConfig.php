<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Interfaces {
    interface IAppConfig extends IIniConfig {
        public function baseUrl(): string;
    }
}

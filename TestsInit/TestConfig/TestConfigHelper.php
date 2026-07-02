<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\TestsInit\TestConfig;

class TestConfigHelper {
    public static function getDbConfigPath(): string {
        // This file is in Framework/TestsInit/TestConfig/
        return __DIR__ . '/db.ini';
    }
}

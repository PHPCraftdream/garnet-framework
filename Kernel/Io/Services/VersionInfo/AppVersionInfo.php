<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Services\VersionInfo {
    use Composer\InstalledVersions;
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseAppInit;

    /**
     * Версия фреймворка и версия приложения, одним вызовом — для показа в
     * системных настройках (или где угодно ещё).
     *
     * Версия фреймворка приходит от Composer напрямую (InstalledVersions),
     * всегда актуальна и не требует никакой генерации: composer сам пишет
     * эти данные при каждом install/update.
     *
     * Версия приложения так со стороны не достать — у приложения нет
     * своего composer-пакета с версией, только git. Читает
     * `Foreground/VersionGen.php`, который на каждой сборке пишет
     * `GarnetPrepareCommand::generateVersionGen()` (git describe/HEAD в
     * момент сборки). Файла может не быть (например, на деве до первого
     * `garnet prepare`) — тогда эта часть просто пустая, не ошибка.
     */
    final class AppVersionInfo {
        private const FRAMEWORK_PACKAGE = 'phpcraftdream/garnet-framework';

        /**
         * @return array{
         *     frameworkVersion: ?string,
         *     frameworkCommit: ?string,
         *     appVersion: ?string,
         *     appCommit: ?string,
         *     builtAt: ?string,
         * }
         */
        public static function current(): array {
            [$frameworkVersion, $frameworkCommit] = self::readFrameworkVersion();
            $app = self::readAppVersionGen();

            return [
                'frameworkVersion' => $frameworkVersion,
                'frameworkCommit' => $frameworkCommit,
                'appVersion' => $app['appVersion'] ?? null,
                'appCommit' => $app['appCommit'] ?? null,
                'builtAt' => $app['builtAt'] ?? null,
            ];
        }

        /** @return array{0: ?string, 1: ?string} */
        private static function readFrameworkVersion(): array {
            if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled(self::FRAMEWORK_PACKAGE)) {
                return [null, null];
            }
            $version = InstalledVersions::getPrettyVersion(self::FRAMEWORK_PACKAGE);
            $reference = InstalledVersions::getReference(self::FRAMEWORK_PACKAGE);

            return [$version, $reference !== null ? substr($reference, 0, 7) : null];
        }

        /** @return array<string, string> */
        private static function readAppVersionGen(): array {
            $appDir = BaseAppInit::getInstance()?->appDir;

            if ($appDir === null) {
                return [];
            }
            $file = rtrim($appDir, '/\\') . DIRECTORY_SEPARATOR . 'Foreground' . DIRECTORY_SEPARATOR . 'VersionGen.php';

            if (!is_file($file)) {
                return [];
            }
            $data = require $file;

            return is_array($data) ? $data : [];
        }
    }
}

<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Viewer\Controllers {
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Request\Controllers\FwDashboardRequestLogController;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;

    /**
     * Proxy — exposes FwDashboardRequestLogController's request/error helpers
     * + POST endpoint logic to the unified viewer without forcing inheritance.
     */
    final class FwLogsRequestProxy extends FwDashboardRequestLogController {
        protected static bool $allowed = true;

        protected static function pageUrl(): string {
            return '';
        }

        protected static function isModerator(): bool {
            return static::$allowed;
        }

        protected static function isOwner(): bool {
            return false;
        }

        /** @return array<int, array<string, mixed>> */
        protected static function getSideMenu(string $url): array {
            return [];
        }

        /** @return array<int, array<string, mixed>> */
        protected static function getMainMenu(string $url): array {
            return [];
        }

        /** @return string[] */
        public static function listRequestDatesPublic(): array {
            return parent::listRequestDates();
        }

        /** @return string[] */
        public static function listErrorDatesPublic(): array {
            return parent::listErrorDates();
        }

        public static function requestsPage(IGlobalReqParams $globals, IRouterUriParams $params, bool $allowed): mixed {
            static::$allowed = $allowed;

            return parent::post__page($globals, $params);
        }

        public static function errorsPage(IGlobalReqParams $globals, IRouterUriParams $params, bool $allowed): mixed {
            static::$allowed = $allowed;

            return parent::post__errorsPage($globals, $params);
        }
    }
}

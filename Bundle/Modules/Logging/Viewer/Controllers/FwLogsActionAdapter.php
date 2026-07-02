<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Viewer\Controllers {
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Admin\Controllers\FwDashboardLogsController;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Admin\Tables\FwAdminActionLog;

    /**
     * Composition adapter — exposes FwDashboardLogsController::fetchLogs()
     * to the unified viewer without forcing inheritance.
     */
    final class FwLogsActionAdapter extends FwDashboardLogsController {
        protected static FwAdminActionLog $table;

        protected static function actionLogTable(): FwAdminActionLog {
            return static::$table;
        }

        /** @return array<string, mixed> */
        protected static function gridConfig(): array {
            return [];
        }

        protected static function isModerator(): bool {
            return true; // gating handled by the calling controller
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

        /**
         * @return array<int, array<string, mixed>>
         */
        public static function run(FwAdminActionLog $table, int $limit): array {
            static::$table = $table;

            return static::fetchLogs($limit);
        }
    }
}

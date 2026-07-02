<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Viewer\Controllers {
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Controllers\FwDashboardMailLogController;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables\FwMailLog;

    /**
     * Composition adapter — exposes FwDashboardMailLogController::fetchLogs()
     * to the unified viewer without forcing inheritance.
     */
    final class FwLogsMailAdapter extends FwDashboardMailLogController {
        protected static FwMailLog $table;

        protected static bool $isAdmin = false;

        protected static function mailLogTable(): FwMailLog {
            return static::$table;
        }

        protected static function isAdmin(): bool {
            return static::$isAdmin;
        }

        /** @return array<string, mixed> */
        protected static function gridConfig(): array {
            return [];
        }

        protected static function isModerator(): bool {
            return true;
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
        public static function run(FwMailLog $table, bool $isAdmin, int $limit): array {
            static::$table = $table;
            static::$isAdmin = $isAdmin;

            return static::fetchLogs($limit);
        }
    }
}

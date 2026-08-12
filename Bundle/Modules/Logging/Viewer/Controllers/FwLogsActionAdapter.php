<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Viewer\Controllers {
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Admin\Controllers\FwDashboardLogsController;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Admin\Tables\FwAdminActionLog;

    /**
     * Composition adapter — exposes FwDashboardLogsController::fetchLogs()
     * to the unified viewer without forcing inheritance.
     *
     * The caller's real isModerator() result is threaded through run() and
     * enforced here, instead of unconditionally reporting true — mirrors
     * the isAdmin passthrough FwLogsMailAdapter already uses.
     */
    final class FwLogsActionAdapter extends FwDashboardLogsController {
        protected static FwAdminActionLog $table;

        protected static bool $isModerator = false;

        protected static function actionLogTable(): FwAdminActionLog {
            return static::$table;
        }

        /** @return array<string, mixed> */
        protected static function gridConfig(): array {
            return [];
        }

        protected static function isModerator(): bool {
            return static::$isModerator;
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
         * @throws LogicException if $isModerator is false — the calling
         *     controller must verify isModerator() itself before calling run().
         */
        public static function run(FwAdminActionLog $table, int $limit, bool $isModerator): array {
            if (!$isModerator) {
                throw new LogicException('FwLogsActionAdapter::run() requires isModerator to be true.');
            }

            static::$table = $table;
            static::$isModerator = $isModerator;

            return static::fetchLogs($limit);
        }
    }
}

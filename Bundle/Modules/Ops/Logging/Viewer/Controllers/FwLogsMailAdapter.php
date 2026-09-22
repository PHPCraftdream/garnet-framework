<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Ops\Logging\Viewer\Controllers {
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Ops\Logging\Mail\Controllers\FwDashboardMailLogController;
    use PHPCraftdream\Garnet\Bundle\Modules\Ops\Logging\Mail\Tables\FwMailLog;

    /**
     * Composition adapter — exposes FwDashboardMailLogController::fetchLogs()
     * to the unified viewer without forcing inheritance.
     *
     * The caller's real isModerator() result is threaded through run() and
     * enforced here, instead of unconditionally reporting true.
     */
    final class FwLogsMailAdapter extends FwDashboardMailLogController {
        protected static FwMailLog $table;

        protected static bool $isAdmin = false;

        protected static bool $isModerator = false;

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
         * @param array{status?: string, accountId?: int, noAccount?: bool, mailType?: string} $filters
         * @return array<string, mixed> PageResponse shape
         * @throws LogicException if $isModerator is false — the calling
         *     controller must verify isModerator() itself before calling run().
         */
        public static function run(
            FwMailLog $table,
            bool $isAdmin,
            bool $isModerator,
            int $page = 1,
            int $perPage = 10,
            string $query = '',
            ?string $sortField = null,
            string $sortDir = 'asc',
            array $filters = [],
        ): array {
            if (!$isModerator) {
                throw new LogicException('FwLogsMailAdapter::run() requires isModerator to be true.');
            }

            static::$table = $table;
            static::$isAdmin = $isAdmin;
            static::$isModerator = $isModerator;

            return static::fetchLogsPage($page, $perPage, $query, $sortField, $sortDir, $filters);
        }

        /**
         * @return array{accounts: list<array{id: int, name: string}>, hasNoAccount: bool, types: list<string>}
         */
        public static function runFilterOptions(FwMailLog $table, bool $isAdmin, bool $isModerator): array {
            if (!$isModerator) {
                throw new LogicException('FwLogsMailAdapter::runFilterOptions() requires isModerator to be true.');
            }
            static::$table = $table;
            static::$isAdmin = $isAdmin;
            static::$isModerator = $isModerator;

            return static::fetchFilterOptions();
        }
    }
}

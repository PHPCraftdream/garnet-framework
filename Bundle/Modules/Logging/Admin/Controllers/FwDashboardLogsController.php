<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Admin\Controllers {
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Bundle\Modules\Dashboard\Controllers\FwDashboardController;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Admin\Tables\FwAdminActionLog;
    use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;

    /**
     * Generic admin-action-log viewer.
     *
     * Apps must subclass this and supply the concrete log table, i18n labels
     * and the grid configuration via the abstract hooks below.
     */
    abstract class FwDashboardLogsController extends FwDashboardController {
        /**
         * Concrete admin-action-log table instance for the app
         * (e.g. MyApp's AdminActionLog with its own tableName).
         */
        abstract protected static function actionLogTable(): FwAdminActionLog;

        /**
         * Grid configuration array, including columns, searchFields, sortFields, pageSize.
         *
         * @return array<string, mixed>
         */
        abstract protected static function gridConfig(): array;

        /**
         * Island name to render. Override to use a custom island.
         */
        protected static function islandName(): string {
            return 'admin-logs';
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        protected static function fetchLogs(int $limit = 100): array {
            $logs = static::actionLogTable()->selectAll(function (SelectInterface $query) use ($limit): void {
                $query->orderBy(['id DESC']);
                $query->limit($limit);
            });

            // Collect all unique account IDs from actor_id and target_id
            $accountIds = array_unique(array_filter(array_merge(
                array_column($logs, 'actor_id'),
                array_column($logs, 'target_id'),
            ), static fn ($id) => (int)$id > 0));

            $accounts = [];

            if (!empty($accountIds)) {
                $accs = Account::getAccounts(
                    selectCallback: static function (SelectInterface $select) use ($accountIds): void {
                        $select->resetCols();
                        $select->cols(['id', 'login', 'name', 'type']);
                        $select->where('id IN (?)', [array_map('intval', $accountIds)]);
                    },
                    accountDataFields: [Account::IS_MODERATOR, Account::IS_OWNER, Account::IS_ADMIN],
                );

                foreach ($accs as $a) {
                    $accounts[(int)$a['id']] = $a;
                }
            }

            foreach ($logs as &$log) {
                $actorId = (int)$log['actor_id'];
                $targetId = (int)$log['target_id'];
                $actor = $accounts[$actorId] ?? null;
                $target = $accounts[$targetId] ?? null;

                $log['actor_name'] = $actor['name'] ?? '';
                $log['actor_type'] = $actor ? static::resolveRole($actor) : '';

                $log['target_name'] = $target['name'] ?? '';
                $log['target_type'] = $target ? static::resolveRole($target) : '';
            }

            return $logs;
        }

        /**
         * @param array<string, mixed> $account
         */
        protected static function resolveRole(array $account): string {
            if (intval($account[Account::IS_ADMIN] ?? 0) > 0) {
                return 'admin';
            }

            if (intval($account[Account::IS_OWNER] ?? 0) > 0) {
                return 'owner';
            }

            if (intval($account[Account::IS_MODERATOR] ?? 0) > 0) {
                return 'moderator';
            }

            return $account['type'] ?? 'user';
        }

        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::redirect('/');
            }

            $url = $globals->getUri();
            $content = RenderIsland::render(static::islandName(), [
                'logs' => static::fetchLogs(),
                'gridConfig' => static::gridConfig(),
            ]);

            return ControllerTools::ok(HtmlLayout::render(
                TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS, [
                    'content' => $content,
                    'top_menu_items' => static::getMainMenu($url),
                    'side_menu_items' => static::getSideMenu($url),
                ])
            ));
        }
    }
}

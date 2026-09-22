<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Ops\Logging\Admin\Controllers {
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Bundle\Modules\Content\Dashboard\Controllers\FwDashboardController;
    use PHPCraftdream\Garnet\Bundle\Modules\Ops\Logging\Admin\Tables\FwAdminActionLog;
    use PHPCraftdream\Garnet\Bundle\Support\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Support\Utils\PaginationHelper;
    use PHPCraftdream\Garnet\Bundle\Support\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Core\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Web\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Http\Router\Controller\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Render\Twig\TwigParams;

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
         * @param array{actorId?: int, targetId?: int, action?: string, dateFrom?: int, dateTo?: int} $filters
         * @return array<string, mixed> PageResponse shape (see PaginationHelper::toPageResponse)
         */
        protected static function fetchLogsPage(
            int $page,
            int $perPage,
            string $query = '',
            ?string $sortField = null,
            string $sortDir = 'asc',
            array $filters = [],
        ): array {
            $searchFields = ['actor_login', 'actor_name', 'target_login', 'target_name', 'action'];
            $sortFields = ['id', 'actor_id', 'target_id', 'created_at'];

            $pageData = PaginationHelper::fetchPage(
                static::actionLogTable(),
                $page,
                $perPage,
                static function (SelectInterface $q) use ($query, $sortField, $sortDir, $searchFields, $sortFields, $filters): void {
                    if (isset($filters['actorId'])) {
                        $q->where('actor_id = :flt_actor', ['flt_actor' => $filters['actorId']]);
                    }

                    if (isset($filters['targetId'])) {
                        $q->where('target_id = :flt_target', ['flt_target' => $filters['targetId']]);
                    }

                    if (isset($filters['action']) && $filters['action'] !== '') {
                        $q->where('action = :flt_action', ['flt_action' => $filters['action']]);
                    }

                    if (isset($filters['dateFrom'])) {
                        $q->where('created_at >= :flt_from', ['flt_from' => $filters['dateFrom']]);
                    }

                    if (isset($filters['dateTo'])) {
                        $q->where('created_at <= :flt_to', ['flt_to' => $filters['dateTo']]);
                    }
                    PaginationHelper::applySearchAndSort($q, $query, $searchFields, $sortField, $sortDir, $sortFields);
                },
            );

            $pageData->pageItems = static::hydrateActorTarget($pageData->pageItems);

            return PaginationHelper::toPageResponse($pageData);
        }

        /**
         * @param list<array<string, mixed>> $logs
         * @return list<array<string, mixed>>
         */
        private static function hydrateActorTarget(array $logs): array {
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
            unset($log);

            return $logs;
        }

        /**
         * Distinct actors (any account that has ever performed a logged
         * action) and distinct action types — feeds the filter dropdowns
         * without shipping the whole log table to the browser.
         *
         * @return array{actors: list<array{id: int, name: string}>, actions: list<string>}
         */
        protected static function fetchFilterOptions(): array {
            $actorIds = array_map('intval', array_column(
                static::actionLogTable()->selectAll(static function (SelectInterface $q): void {
                    $q->resetCols();
                    $q->cols(['DISTINCT actor_id AS actor_id']);
                }),
                'actor_id',
            ));

            $actors = [];

            if (!empty($actorIds)) {
                $accs = Account::getAccounts(
                    selectCallback: static function (SelectInterface $select) use ($actorIds): void {
                        $select->resetCols();
                        $select->cols(['id', 'login', 'name']);
                        $select->where('id IN (?)', [$actorIds]);
                    },
                );

                foreach ($accs as $a) {
                    $actors[] = ['id' => (int)$a['id'], 'name' => (string)($a['name'] ?? $a['login'] ?? ('#' . $a['id']))];
                }
                usort($actors, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
            }

            $actions = array_map('strval', array_column(
                static::actionLogTable()->selectAll(static function (SelectInterface $q): void {
                    $q->resetCols();
                    $q->cols(['DISTINCT action AS action']);
                }),
                'action',
            ));
            sort($actions);

            return ['actors' => $actors, 'actions' => $actions];
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

            $gridConfig = static::gridConfig();
            $perPage = (int)($gridConfig['pageSize'] ?? PaginationHelper::DEFAULT_PER_PAGE) ?: PaginationHelper::DEFAULT_PER_PAGE;

            $url = $globals->getUri();
            $content = RenderIsland::render(static::islandName(), [
                'logsPayload' => static::fetchLogsPage(1, $perPage),
                'filterOptions' => static::fetchFilterOptions(),
                'gridConfig' => $gridConfig,
            ]);

            return ControllerTools::ok(HtmlLayout::render(
                TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS, [
                    'content' => $content,
                    'top_menu_items' => static::getMainMenu($url),
                    'side_menu_items' => static::getSideMenu($url),
                ])
            ));
        }

        public static function post__logsPage(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }
            ['page' => $page, 'perPage' => $perPage] = PaginationHelper::readPageParams($globals);
            ['query' => $query, 'sortField' => $sortField, 'sortDir' => $sortDir] = PaginationHelper::readSearchSortParams($globals);
            $filters = static::readLogFilters($globals);

            return ControllerTools::JSON(static::fetchLogsPage($page, $perPage, $query, $sortField, $sortDir, $filters));
        }

        /**
         * @return array{actorId?: int, targetId?: int, action?: string, dateFrom?: int, dateTo?: int}
         */
        protected static function readLogFilters(IGlobalReqParams $globals): array {
            $filters = [];
            $actorId = (int)$globals->readPostValue('actorId', 0);

            if ($actorId > 0) {
                $filters['actorId'] = $actorId;
            }
            $targetId = (int)$globals->readPostValue('targetId', 0);

            if ($targetId > 0) {
                $filters['targetId'] = $targetId;
            }
            $action = trim((string)$globals->readPostValue('action', ''));

            if ($action !== '') {
                $filters['action'] = $action;
            }
            $dateFrom = (int)$globals->readPostValue('dateFrom', 0);

            if ($dateFrom > 0) {
                $filters['dateFrom'] = $dateFrom;
            }
            $dateTo = (int)$globals->readPostValue('dateTo', 0);

            if ($dateTo > 0) {
                $filters['dateTo'] = $dateTo;
            }

            return $filters;
        }
    }
}

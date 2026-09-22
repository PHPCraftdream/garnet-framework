<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Ops\Logging\Mail\Controllers {
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Bundle\Modules\Content\Dashboard\Controllers\FwDashboardController;
    use PHPCraftdream\Garnet\Bundle\Modules\Ops\Logging\Mail\Tables\FwMailLog;
    use PHPCraftdream\Garnet\Bundle\Support\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Support\Utils\PaginationHelper;
    use PHPCraftdream\Garnet\Bundle\Support\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Core\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Web\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Http\Router\Controller\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Render\Twig\TwigParams;

    /**
     * Generic mail-log viewer.
     *
     * Apps must subclass this and supply the concrete mail-log table, the
     * "is admin?" check (admins see body_html and meta — sensitive content)
     * and grid configuration via the abstract hooks below.
     */
    abstract class FwDashboardMailLogController extends FwDashboardController {
        /**
         * Concrete mail-log table for the app.
         */
        abstract protected static function mailLogTable(): FwMailLog;

        /**
         * Whether the current user has full-admin access (sees body_html / meta).
         */
        abstract protected static function isAdmin(): bool;

        /**
         * Grid configuration array, including columns, searchFields, sortFields, pageSize.
         *
         * @return array<string, mixed>
         */
        abstract protected static function gridConfig(): array;

        protected static function islandName(): string {
            return 'admin-mail-log';
        }

        /**
         * @param array{status?: string, accountId?: int, noAccount?: bool, mailType?: string} $filters
         * @return array<string, mixed> PageResponse shape
         */
        protected static function fetchLogsPage(
            int $page,
            int $perPage,
            string $query = '',
            ?string $sortField = null,
            string $sortDir = 'asc',
            array $filters = [],
        ): array {
            $isAdmin = static::isAdmin();
            $searchFields = ['recipient_email', 'account_name', 'account_login', 'mail_type', 'subject', 'status', 'error_log'];

            if ($isAdmin) {
                $searchFields[] = 'body_html';
                $searchFields[] = 'meta';
            }
            $sortFields = ['id', 'created_at', 'mail_type', 'status'];

            $pageData = PaginationHelper::fetchPage(
                static::mailLogTable(),
                $page,
                $perPage,
                static function (SelectInterface $q) use ($query, $sortField, $sortDir, $searchFields, $sortFields, $filters): void {
                    if (isset($filters['status']) && $filters['status'] !== '') {
                        $q->where('status = :flt_status', ['flt_status' => $filters['status']]);
                    }

                    if (!empty($filters['noAccount'])) {
                        $q->where('account_id IS NULL');
                    } elseif (isset($filters['accountId'])) {
                        $q->where('account_id = :flt_account', ['flt_account' => $filters['accountId']]);
                    }

                    if (isset($filters['mailType']) && $filters['mailType'] !== '') {
                        $q->where('mail_type = :flt_type', ['flt_type' => $filters['mailType']]);
                    }
                    PaginationHelper::applySearchAndSort($q, $query, $searchFields, $sortField, $sortDir, $sortFields);
                },
            );

            $pageData->pageItems = static::hydrateAccounts($pageData->pageItems, $isAdmin);

            return PaginationHelper::toPageResponse($pageData);
        }

        /**
         * @param list<array<string, mixed>> $logs
         * @return list<array<string, mixed>>
         */
        private static function hydrateAccounts(array $logs, bool $isAdmin): array {
            $accountIds = array_unique(array_filter(
                array_column($logs, 'account_id'),
                static fn ($id) => (int)$id > 0
            ));

            $accounts = [];

            if (!empty($accountIds)) {
                $accs = Account::getAccounts(
                    selectCallback: static function (SelectInterface $select) use ($accountIds): void {
                        $select->resetCols();
                        $select->cols(['id', 'login', 'name']);
                        $select->where('id IN (?)', [array_map('intval', $accountIds)]);
                    },
                );

                foreach ($accs as $a) {
                    $accounts[(int)$a['id']] = $a;
                }
            }

            foreach ($logs as &$log) {
                $accountId = (int)($log['account_id'] ?? 0);
                $acc = $accounts[$accountId] ?? null;
                $log['account_name'] = $acc['name'] ?? '';
                $log['account_login'] = $acc['login'] ?? '';

                // body_html and meta contain sensitive content (auth codes etc.)
                // Only expose to admins
                if (!$isAdmin) {
                    unset($log['body_html'], $log['meta']);
                }
            }
            unset($log);

            return $logs;
        }

        /**
         * Distinct recipients-with-accounts and mail types — feeds the
         * filter dropdowns without shipping the whole log table.
         *
         * @return array{accounts: list<array{id: int, name: string}>, hasNoAccount: bool, types: list<string>}
         */
        protected static function fetchFilterOptions(): array {
            $accountIds = array_map('intval', array_column(
                static::mailLogTable()->selectAll(static function (SelectInterface $q): void {
                    $q->resetCols();
                    $q->cols(['DISTINCT account_id AS account_id']);
                    $q->where('account_id IS NOT NULL');
                }),
                'account_id',
            ));

            $hasNoAccount = static::mailLogTable()->getCount(static function (SelectInterface $q): void {
                $q->where('account_id IS NULL');
            }) > 0;

            $accounts = [];

            if (!empty($accountIds)) {
                $accs = Account::getAccounts(
                    selectCallback: static function (SelectInterface $select) use ($accountIds): void {
                        $select->resetCols();
                        $select->cols(['id', 'login', 'name']);
                        $select->where('id IN (?)', [$accountIds]);
                    },
                );

                foreach ($accs as $a) {
                    $accounts[] = ['id' => (int)$a['id'], 'name' => (string)($a['name'] ?? $a['login'] ?? ('#' . $a['id']))];
                }
                usort($accounts, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
            }

            $types = array_map('strval', array_column(
                static::mailLogTable()->selectAll(static function (SelectInterface $q): void {
                    $q->resetCols();
                    $q->cols(['DISTINCT mail_type AS mail_type']);
                }),
                'mail_type',
            ));
            sort($types);

            return ['accounts' => $accounts, 'hasNoAccount' => $hasNoAccount, 'types' => $types];
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
            $filters = static::readMailFilters($globals);

            return ControllerTools::JSON(static::fetchLogsPage($page, $perPage, $query, $sortField, $sortDir, $filters));
        }

        /**
         * @return array{status?: string, accountId?: int, noAccount?: bool, mailType?: string}
         */
        protected static function readMailFilters(IGlobalReqParams $globals): array {
            $filters = [];
            $status = trim((string)$globals->readPostValue('status', ''));

            if ($status !== '') {
                $filters['status'] = $status;
            }
            $accountIdRaw = trim((string)$globals->readPostValue('accountId', ''));

            if ($accountIdRaw === '__no_account__') {
                $filters['noAccount'] = true;
            } elseif ($accountIdRaw !== '' && (int)$accountIdRaw > 0) {
                $filters['accountId'] = (int)$accountIdRaw;
            }
            $mailType = trim((string)$globals->readPostValue('mailType', ''));

            if ($mailType !== '') {
                $filters['mailType'] = $mailType;
            }

            return $filters;
        }
    }
}

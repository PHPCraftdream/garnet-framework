<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Controllers {
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Bundle\Modules\Dashboard\Controllers\FwDashboardController;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables\FwMailLog;
    use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;

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
         * @return array<int, array<string, mixed>>
         */
        protected static function fetchLogs(int $limit = 200): array {
            $isAdmin = static::isAdmin();

            $logs = static::mailLogTable()->selectAll(function (SelectInterface $query) use ($limit): void {
                $query->orderBy(['id DESC']);
                $query->limit($limit);
            });

            // Enrich with account names
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

            return $logs;
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

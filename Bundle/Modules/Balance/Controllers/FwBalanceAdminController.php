<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Balance\Controllers {
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Bundle\Modules\Balance\Tables\FwAccountBalance;
    use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;

    abstract class FwBalanceAdminController extends FrameworkController {
        /**
         * Return the concrete FwAccountBalance subclass instance.
         */
        abstract protected static function balanceTable(): FwAccountBalance;

        /**
         * Return side-menu items array for the current URL.
         */
        abstract protected static function getSideMenu(string $url): array;

        /**
         * Return top/main-menu items array for the current URL.
         */
        abstract protected static function getMainMenu(string $url): array;

        /**
         * Check whether current user is allowed (moderator / admin).
         */
        abstract protected static function isAllowed(): bool;

        /**
         * Island name for the admin balances page.
         */
        protected static function islandName(): string {
            return 'admin-balances';
        }

        /**
         * Build grid config array. Override in subclass to localise column labels.
         *
         * @return array Grid configuration
         */
        abstract protected static function buildGridConfig(): array;

        protected static function fetchBalances(): array {
            $balances = static::balanceTable()->selectAll(function (SelectInterface $q): void {
                $q->orderBy(['balance DESC']);
            });

            $accountIds = array_unique(array_filter(array_column($balances, 'account_id')));
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

            foreach ($balances as &$bal) {
                $aid = (int)$bal['account_id'];
                $acc = $accounts[$aid] ?? null;
                $bal['login'] = $acc['login'] ?? '';
                $bal['name'] = $acc['name'] ?? '';
                $bal['type'] = $acc ? static::resolveRole($acc) : '';
            }

            return $balances;
        }

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
            if (!static::isAllowed()) {
                return ControllerTools::redirect('/');
            }

            $url = $globals->getUri();
            $content = RenderIsland::render(static::islandName(), [
                'balances' => static::fetchBalances(),
                'gridConfig' => static::buildGridConfig(),
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

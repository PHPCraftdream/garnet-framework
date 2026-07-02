<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Balance\Controllers {
    use Aura\SqlQuery\Common\SelectInterface;
    use Closure;
    use PHPCraftdream\Garnet\Bundle\Modules\Balance\Tables\FwAccountBalance;
    use PHPCraftdream\Garnet\Bundle\Modules\Balance\Tables\FwBalanceLedger;
    use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Utils\PaginationHelper;
    use PHPCraftdream\Garnet\Bundle\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;

    abstract class FwBalanceController extends FrameworkController {
        /**
         * Return the concrete FwAccountBalance subclass instance.
         */
        abstract protected static function balanceTable(): FwAccountBalance;

        /**
         * Return the concrete FwBalanceLedger subclass instance.
         */
        abstract protected static function ledgerTable(): FwBalanceLedger;

        /**
         * Return side-menu items array for the current URL.
         */
        abstract protected static function getSideMenu(string $url): array;

        /**
         * Return top/main-menu items array for the current URL.
         */
        abstract protected static function getMainMenu(string $url): array;

        /**
         * Island name for the balance page (e.g. 'balance').
         */
        protected static function islandName(): string {
            return 'balance';
        }

        /**
         * Ledger page endpoint path (relative to controller URL).
         */
        protected static function ledgerPagePath(): string {
            return '/balance/~ledgerPage';
        }

        /**
         * Top-up note (default for the ledger entry).
         */
        protected static function topUpNote(): string {
            return 'Top-up';
        }

        public static function renderContent(string $content, string $url): string {
            return HtmlLayout::render(
                TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS, [
                    'content' => $content,
                    'top_menu_items' => static::getMainMenu($url),
                    'side_menu_items' => static::getSideMenu($url),
                ])
            );
        }

        private static function ledgerWhereCallback(int $accountId): Closure {
            return function (SelectInterface $query) use ($accountId): void {
                $query->where('account_id = ?', [$accountId])
                      ->orderBy(['created_at DESC']);
            };
        }

        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $url = $globals->getUri();
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::redirect('/register');
            }

            $accountId = (int)$account->id();
            $balance = static::balanceTable()::getBalance($accountId);

            $pageData = PaginationHelper::fetchPage(
                static::ledgerTable(), 1, 20, static::ledgerWhereCallback($accountId)
            );

            $content = RenderIsland::render(static::islandName(), [
                'balance' => $balance,
                'ledgerPagination' => PaginationHelper::toPageResponse($pageData),
                'ledgerPageUrl' => static::ledgerPagePath(),
                'csrf' => Session::touchCSRF_(),
            ]);

            return ControllerTools::ok(static::renderContent($content, $url));
        }

        public static function post__ledgerPage(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $accountId = (int)$account->id();
            ['page' => $page, 'perPage' => $perPage] = PaginationHelper::readPageParams($globals);

            $pageData = PaginationHelper::fetchPage(
                static::ledgerTable(), $page, $perPage, static::ledgerWhereCallback($accountId)
            );

            return ControllerTools::JSON(PaginationHelper::toPageResponse($pageData));
        }

        public static function post__topup(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $postCsrf = $globals->readPostValue(Session::CSRF_TOKEN, '');

            if (!hash_equals(Session::touchCSRF_(), (string)$postCsrf)) {
                return ControllerTools::JSON(['error' => 'CSRF check failed'], status: 403);
            }

            $amount = (int)$globals->readPostValue('amount', 0);

            if ($amount <= 0 || $amount > 1_000_000) {
                return ControllerTools::JSON(['error' => 'Invalid amount'], status: 400);
            }

            $ledger = static::ledgerTable();
            $ledger::addEntry(
                (int)$account->id(),
                true,
                $amount,
                'top_up',
                '',
                0,
                static::topUpNote()
            );

            $newBalance = static::balanceTable()::getBalance((int)$account->id());

            $newEntry = $ledger->selectAll(function (SelectInterface $query) use ($account): void {
                $query->where('account_id = ?', [(int)$account->id()])
                      ->orderBy(['id DESC'])
                      ->limit(1);
            });

            return ControllerTools::JSON([
                'success' => true,
                'balance' => $newBalance,
                'newEntry' => !empty($newEntry) ? $newEntry[0] : null,
            ]);
        }
    }
}

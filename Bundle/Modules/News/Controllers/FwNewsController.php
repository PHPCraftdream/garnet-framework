<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\News\Controllers {
    use PHPCraftdream\Garnet\Bundle\Modules\News\FwNewsService;
    use PHPCraftdream\Garnet\Bundle\Utils\PaginationHelper;
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;

    abstract class FwNewsController extends FrameworkController {
        /**
         * Return the fully-qualified class name of the FwNewsService subclass.
         *
         * @return class-string<FwNewsService>
         */
        abstract protected static function newsService(): string;

        public static function post__feed(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            ['page' => $page, 'perPage' => $perPage] = PaginationHelper::readPageParams($globals);
            $includeArchived = (bool)$globals->readPostValue('includeArchived', '0');

            /** @var FwNewsService $svc */
            $svc = static::newsService();
            $feed = $svc::getFeed($account->id(), $page, $perPage, $includeArchived);

            return ControllerTools::JSON($feed);
        }

        public static function post__markRead(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $idsJson = $globals->readPostValue('event_ids', '[]');
            $ids = json_decode($idsJson, true);

            if (!is_array($ids)) {
                $ids = [];
            }

            /** @var FwNewsService $svc */
            $svc = static::newsService();
            $svc::markRead($account->id(), array_map('intval', $ids));

            return ControllerTools::JSON([
                'success' => true,
                'unreadCount' => $svc::getUnreadCount($account->id()),
            ]);
        }

        public static function post__markAllRead(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            /** @var FwNewsService $svc */
            $svc = static::newsService();
            $svc::markAllRead($account->id());

            return ControllerTools::JSON(['success' => true, 'unreadCount' => 0]);
        }

        public static function post__archive(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $idsJson = $globals->readPostValue('event_ids', '[]');
            $ids = json_decode($idsJson, true);

            if (!is_array($ids)) {
                $ids = [];
            }

            /** @var FwNewsService $svc */
            $svc = static::newsService();
            $svc::archive($account->id(), array_map('intval', $ids));

            return ControllerTools::JSON(['success' => true]);
        }

        public static function post__unarchive(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $idsJson = $globals->readPostValue('event_ids', '[]');
            $ids = json_decode($idsJson, true);

            if (!is_array($ids)) {
                $ids = [];
            }

            /** @var FwNewsService $svc */
            $svc = static::newsService();
            $svc::unarchive($account->id(), array_map('intval', $ids));

            return ControllerTools::JSON(['success' => true]);
        }
    }
}

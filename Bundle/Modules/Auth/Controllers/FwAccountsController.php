<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Auth\Controllers {
    use Aura\Sql\Exception;
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Bundle\FrameworkJsGen;
    use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Core\Tools\ArrayTools;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\DbAccount;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\DbAccountData;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity\SaveFilesParams;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\DbLog\EntityLog;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\SessionDataTable;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\SessionTable;
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\ValidationException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IEntityConfig;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;

    abstract class FwAccountsController extends FrameworkController {
        public const URL = '/dashboard/';

        abstract protected static function getEntityConfig(): IEntityConfig;

        abstract protected static function publicDir(): string;

        abstract protected static function getSideMenu(string $url): array;

        abstract protected static function getMainMenu(string $url): array;

        protected static function getUsersGridInfo(): array {
            $config = static::getEntityConfig();

            return [
                'saveUrl' => static::URL . '~save_user',
                ...$config->getGridInfo(),
            ];
        }

        public static function getAccounts(?callable $selectCallback = null): array {
            $config = static::getEntityConfig();

            $accounts = Account::getAccounts(
                selectCallback: static function (SelectInterface $select) use ($selectCallback, $config): void {
                    $select->resetCols();
                    $select->cols($config->selectFields());
                    $select->orderBy(['id desc']);

                    if (!empty($selectCallback)) {
                        $selectCallback($select);
                    }
                },
                accountDataFields: $config->dataFields(),
            );

            foreach ($accounts as &$account) {
                $config->patchItem($account);
            }

            return $accounts;
        }

        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $url = $globals->getUri();

            $gridInfo = static::getUsersGridInfo();
            $gridInfo['items'] = static::getAccounts();

            $props = [
                'gridInfo' => base64_encode(json_encode($gridInfo)),
            ];

            $content = RenderIsland::render('users-grid', $props);

            $render = HtmlLayout::render(
                TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS, [
                    'content' => $content,
                    'top_menu_items' => static::getMainMenu($url),
                    'side_menu_items' => static::getSideMenu($url),
                    'styles_assets' => [],
                    'js_assets' => [
                        FrameworkJsGen::gridtable(),
                    ]
                ])
            );

            return ControllerTools::ok($render);
        }

        /**
         * @throws Exception
         * @throws ValidationException
         * @throws IniConfigException
         * @throws DbException
         */
        public static function post__save_user(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $config = static::getEntityConfig();
            $idKey = $config->idField();
            $id = $globals->readPostValue($idKey);

            $updateAccount = Account::get($id);
            $updateAccount->readDbAsync();
            $updateAccount->readDataAsyncPollFinishAll();

            $prevData = [
                $idKey => $id,
                ...$updateAccount->readParams($config->selectFields()),
                ...$updateAccount->readDataParams($config->dataFields()),
            ];

            $saveAccount = $config->saveOne(
                $globals->readPostAll(),
                $config->manageFormFields(),
                SaveFilesParams::make(
                    $globals->readFilesAll(),
                    static::publicDir(),
                    $updateAccount->getParams(),
                )
            );

            $errors = $saveAccount->update->getErrors();

            if (!empty($errors)) {
                return ControllerTools::JSON($errors);
            }

            $updateAccount->setParams($saveAccount->update->resultData);

            if (!empty($saveAccount->addData)) {
                $updateAccount->setBoolDataArr($saveAccount->addData->resultData);
            }

            $updateAccount->flush();

            $resultData = [
                $idKey => $id,
                ...$prevData,
                ...$saveAccount->update->resultData,
                ...($saveAccount?->addData->resultData ?? []),
            ];

            $diff = ArrayTools::arrayDbDiffValues($prevData, $resultData);

            if (!empty($diff)) {
                EntityLog::get()->writeLog('account', $id, 'update', $diff, true);
            }

            $result = [
                'ok' => true,
                'data' => $config->patchItem($resultData),
            ];

            $updateAccount->readDataAsyncPollFinishAll();

            return ControllerTools::JSON($result);
        }

        public static function post__create_user(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $config = static::getEntityConfig();

            $saveAccount = $config->saveOne(
                $globals->readPostAll(),
                $config->manageFormFields(),
                SaveFilesParams::make(
                    $globals->readFilesAll(),
                    static::publicDir(),
                    [],
                )
            );

            $errors = $saveAccount->update->getErrors();

            if (!empty($errors)) {
                return ControllerTools::JSON($errors);
            }

            $login = $globals->readPostValue('login');

            if (empty($login)) {
                return ControllerTools::JSON(['login' => 'Login is required']);
            }

            $newAccount = Account::touchAccount($login, DbAccount::LOGIN_TYPE_EMAIL);
            $newAccount->setParams($saveAccount->update->resultData);

            if (!empty($saveAccount->addData)) {
                $newAccount->setBoolDataArr($saveAccount->addData->resultData);
            }

            $newAccount->flush();
            $newAccount->readDataAsyncPollFinishAll();

            $idKey = $config->idField();
            $id = $newAccount->id();

            $resultData = [
                $idKey => $id,
                ...$saveAccount->update->resultData,
                ...($saveAccount?->addData->resultData ?? []),
            ];

            EntityLog::get()->writeLog('account', $id, 'create', $resultData, true);

            $result = [
                'ok' => true,
                'data' => $config->patchItem($resultData),
            ];

            return ControllerTools::JSON($result);
        }

        public static function post__delete_user(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $config = static::getEntityConfig();
            $idKey = $config->idField();
            $id = $globals->readPostValue($idKey);

            if (empty($id)) {
                return ControllerTools::JSON(['error' => 'ID is required']);
            }

            $deleteAccount = Account::get($id);
            $deleteAccount->readDbAsync();
            $deleteAccount->readDataAsyncPollFinishAll();

            $login = $deleteAccount->readParam('login');

            if (!str_starts_with($login, 'testuser_')) {
                return ControllerTools::JSON(['error' => 'Can only delete test users']);
            }

            $dbPool = DbPool::get();

            $dbAccount = DbAccount::get();
            $dbAccountData = DbAccountData::get();
            $sessionTable = SessionTable::get();
            $sessionDataTable = SessionDataTable::get();

            $deleteAccountData = $dbAccountData->newDelete();
            $deleteAccountData->where('account_id = :account_id', ['account_id' => $id]);
            $dbAccountData->getQueryEx()->exDeleteAsync($deleteAccountData);

            $selectSessionIds = $sessionDataTable->newSelect();
            $selectSessionIds->cols(['sessionId']);
            $selectSessionIds->where('param = :param AND value = :login', [
                'param' => Account::SESSION_AUTH_LOGIN,
                'login' => $login,
            ]);
            $sessionRows = $sessionDataTable->getQueryEx()->exSelect($selectSessionIds);
            $sessionIds = array_column($sessionRows, 'sessionId');

            if (!empty($sessionIds)) {
                $deleteSessionData = $sessionDataTable->newDelete();
                $deleteSessionData->where('sessionId IN (:ids)', ['ids' => $sessionIds]);
                $sessionDataTable->getQueryEx()->exDeleteAsync($deleteSessionData);

                $deleteSessions = $sessionTable->newDelete();
                $deleteSessions->where('id IN (:ids)', ['ids' => $sessionIds]);
                $sessionTable->getQueryEx()->exDeleteAsync($deleteSessions);
            }

            $dbAccount->deleteByIdAsync($id);

            $dbPool->pollFinishAll();

            EntityLog::get()->writeLog('account', $id, 'delete', ['id' => $id], true);

            $result = [
                'ok' => true,
            ];

            return ControllerTools::JSON($result);
        }
    }
}

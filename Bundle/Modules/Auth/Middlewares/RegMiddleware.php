<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares {
    use Aura\Sql\Exception;
    use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Core\Tools\ArrayTools;
    use PHPCraftdream\Garnet\Kernel\Core\Tools\StrTools;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity\SaveFilesParams;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\DbLog\EntityLog;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session;
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommonException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\ValidationException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IEntityConfig;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\AppConfig;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;
    use Psr\Http\Message\ResponseInterface;

    abstract class RegMiddleware {
        abstract protected static function getEntityConfig(): IEntityConfig;

        abstract protected static function publicDir(): string;

        /**
         * App-specific defaults for fresh accounts on first registration submission.
         * Override in subclasses to set business-level fields (e.g. account type).
         *
         * @return array<string, mixed>
         */
        /**
         * Hook for app-specific wrapping of the registration form content
         * (e.g. inject site header/footer snippets). Default: identity.
         */
        protected static function wrapPageContent(string $content): string {
            return $content;
        }

        protected static function initialAccountParams(): array {
            return [
                'token16' => StrTools::randomUtString(16),
                'token32' => StrTools::randomUtString(32),
                'reg_time' => time(),
                'last_auth_time' => time(),
                'last_online_time' => time(),
            ];
        }

        /**
         * @param IGlobalReqParams $globals
         * @param IRouterUriParams $params
         * @return ResponseInterface|null
         * @throws CommonException
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         * @throws \Twig\Error\LoaderError
         * @throws \Twig\Error\RuntimeError
         * @throws \Twig\Error\SyntaxError
         * @throws ValidationException
         */
        public static function process(IGlobalReqParams $globals, IRouterUriParams $params): ?ResponseInterface {
            $account = Account::fromSession();
            $params = $account->getParams();

            if (!empty($params['name']) || !empty($params['time_zone'])) {
                return null;
            }

            if (!$globals->isPost()) {
                return static::registrationView();
            }

            if ($globals->readPostValue('action') !== 'reg_user') {
                return null;
            }

            return static::processPost($globals);
        }

        public static function processPost(IGlobalReqParams $globals): ?ResponseInterface {
            $account = Account::fromSession();
            $config = static::getEntityConfig();
            $prevData = $account->getParams();
            $logAction = 'update';
            $logIsDiff = true;

            if (empty($account->readParam('token16'))) {
                $prevData = [];
                $logAction = 'create';
                $logIsDiff = false;

                $initialParams = static::initialAccountParams();

                $account->setParams($initialParams);
            }

            $saveAccount = $config->saveOne(
                $globals->readPostAll(),
                $config->editFields(),
                SaveFilesParams::make(
                    $globals->readFilesAll(),
                    static::publicDir(),
                    $account->getParams(),
                )
            );

            $errors = $saveAccount->update->getErrors();

            if (!empty($errors)) {
                return ControllerTools::JSON($errors);
            }

            $appConf = AppConfig::get(IniConfig::ENV_APP);
            $adminEmails = $appConf->paramArray('admin_emails');
            $moderatorEmails = $appConf->paramArray('moderator_emails');

            if ($account->readParam('login_type') === 'email') {
                $adminEmails = array_map('strtolower', $adminEmails);
                $moderatorEmails = array_map('strtolower', $moderatorEmails);
                $mail = strtolower($account->readParam('login'));

                if (!empty($adminEmails) && in_array($mail, $adminEmails, true)) {
                    $account->setAdmin(true);
                } elseif (!empty($moderatorEmails) && in_array($mail, $moderatorEmails, true)) {
                    $account->setModerator(true);
                }
            }

            $diff = ArrayTools::arrayDbDiffValues($prevData, $saveAccount->update->resultData);

            if (!empty($diff)) {
                EntityLog::get()->writeLog('account', $account->id(), $logAction, $diff, $logIsDiff);
            }

            $account->setParams($saveAccount->update->resultData);
            $account->flush();
            $account->readDataAsyncPollFinishAll();

            return ControllerTools::JSON(['ok' => true]);
        }

        protected static function registrationView(): ?ResponseInterface {
            // CSRF token is already in the session (the user got it
            // when they consented in the auth step). The layout helper
            // uses peekCSRF_ which reads it from the existing cookie.
            $config = static::getEntityConfig();

            $details = Account::fromSession()->readParams($config->editFields());
            $detailsInfo = [
                'saveUrl' => null,
                'idColumn' => $config->idField(),
                'fields' => $config->getFieldsInfo(),
                'detailsFields' => $config->editFields(),
            ];

            $props = [
                'detailsInfo' => $detailsInfo,
                'details' => $details,
                'action' => 'reg_user',
            ];

            $content = RenderIsland::render('registration-form', $props);
            $content = static::wrapPageContent($content);

            $render = HtmlLayout::render(
                TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS, [
                    'side_menu_items' => [],
                    'top_menu_items' => [],
                    'content' => $content,
                    'styles_assets' => [],
                    'js_assets' => []
                ])
            );

            return ControllerTools::ok($render);
        }
    }
}

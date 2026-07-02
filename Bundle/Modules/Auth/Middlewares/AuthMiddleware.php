<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares {
    use Exception;
    use PHPCraftdream\Garnet\Bundle\FrameworkJsGen;
    use PHPCraftdream\Garnet\Bundle\I18n\FwI18n;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\AuthStrategy\AuthConfig;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\AuthStrategy\AuthStrategyInterface;
    use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Core\Tools\DateTools;
    use PHPCraftdream\Garnet\Kernel\Core\Tools\StrTools;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session;
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommonException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\LoggerException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\HtmlMinify\HtmlMinify;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\AppConfig;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use PHPCraftdream\Garnet\Kernel\Io\Mailer\Mailer;
    use PHPCraftdream\Garnet\Kernel\Io\RateLimit\RateLimit;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;
    use Psr\Http\Message\ResponseInterface;

    class AuthMiddleware implements AuthStrategyInterface {
        public static function getMethodName(): string {
            return 'email';
        }

        protected static int $authCodeLen = 8;

        protected static int $codeInputTries = 3;

        protected static int $codeSecondsTTL = 300;

        public const SESSION_AUTH_LOGIN = 'auth_login';

        public const SESSION_AUTH_CODE = 'auth_code';

        public const SESSION_AUTH_CODE_UT = 'auth_code_ut';

        public const SESSION_AUTH_TRIES = 'auth_tries';

        public const PHASE_KEY = 'AUTH_PHASE';

        public const CSRF_TOKEN = Session::CSRF_TOKEN;

        public const PHASE_NULL = 'PHASE_NULL';

        public const PHASE_SENT_CODE = 'PHASE_SENT_CODE';

        public const PHASE_DONE = 'PHASE_DONE';

        protected static ?AuthConfig $authConfig = null;

        protected static function getAuthConfig(): AuthConfig {
            if (empty(static::$authConfig)) {
                static::$authConfig = AuthConfig::get();
            }

            return static::$authConfig;
        }

        /**
         * @param IGlobalReqParams $globals
         * @return ResponseInterface|null
         */
        protected static function processOrigin(IGlobalReqParams $globals): ?ResponseInterface {
            $origin = $globals->readServerValue('HTTP_ORIGIN');
            $referer = $globals->readServerValue('HTTP_REFERER');

            $config = static::getAuthConfig();

            if (!empty($origin)) {
                if (!$config->isOriginAllowed($origin)) {
                    return ControllerTools::JSON(['message' => 'Bad origin'], status: 400);
                }
            } elseif (!empty($referer)) {
                $refererOrigin = parse_url($referer, PHP_URL_SCHEME) . '://' . parse_url($referer, PHP_URL_HOST);
                $refererPort = parse_url($referer, PHP_URL_PORT);

                if ($refererPort) {
                    $refererOrigin .= ':' . $refererPort;
                }

                if (!$config->isOriginAllowed($refererOrigin)) {
                    return ControllerTools::JSON(['message' => 'Bad referer'], status: 400);
                }
            }

            return null;
        }

        protected static function updateLastOnline(): void {
            $account = Account::fromSession();
            $lastOnline = (int)$account->readParam('last_online_time');
            $time = time();

            if (time() - $lastOnline > 360) {
                $account->setParam('last_online_time', $time);
            }
        }

        /**
         * @param IGlobalReqParams $globals
         * @param IRouterUriParams $params
         * @return ResponseInterface|null
         * @throws IniConfigException
         * @throws \Twig\Error\LoaderError
         * @throws \Twig\Error\RuntimeError
         * @throws \Twig\Error\SyntaxError
         * @throws Exception
         */
        public static function authOnly(IGlobalReqParams $globals, IRouterUriParams $params): ?ResponseInterface {
            $session = Session::get();
            $session->readDataAsyncPollFinishAll();

            if ($globals->isPost()) {
                $originErrorResponse = static::processOrigin($globals);

                if (!empty($originErrorResponse)) {
                    return $originErrorResponse;
                }

                $csrfErrorResponse = static::processCSRF($globals);

                if (!empty($csrfErrorResponse)) {
                    return $csrfErrorResponse;
                }

                $action = strtolower($globals->readPostValue('action', '') . '');

                if ($action === 'logout') {
                    static::closeAuthSession();

                    return ControllerTools::JSON(['logout' => true], status: 200);
                }
            }

            // --------------------------------------------

            $phase = $session->getValue(static::PHASE_KEY, static::PHASE_NULL);
            $authEmail = $globals->readPostValue('auth_email', null);

            if (!empty($authEmail)) {
                return static::processPhaseNullPost($globals, $params);
            }

            if ($phase === static::PHASE_DONE) {
                static::updateLastOnline();

                return null;
            }

            if ($phase === static::PHASE_NULL) {
                return static::renderPage($globals, ['phase' => 'INPUT_EMAIL']);
            }

            // --------------------------------------------

            $postCode = $globals->readPostValue('code', null);

            if (!empty($postCode)) {
                return static::processPhaseSentCodePost($globals, $params);
            }

            if ($phase === static::PHASE_SENT_CODE) {
                return static::processPhaseSentCode($globals, $params);
            }

            throw new CommonException('Unknown case authOnly');
        }

        /**
         * @param IGlobalReqParams $globals
         * @return bool
         * @throws Exception
         */
        public static function checkCSRF(IGlobalReqParams $globals): bool {
            $postToken = $globals->readPostValue(static::CSRF_TOKEN, false);

            if (!$postToken) {
                return false;
            }

            $sessionToken = Session::touchCSRF_();

            if (!$sessionToken) {
                return false;
            }

            return $postToken === $sessionToken;
        }

        /**
         * @param IGlobalReqParams $globals
         * @return ResponseInterface|null
         * @throws Exception
         */
        public static function processCSRF(IGlobalReqParams $globals): ?ResponseInterface {
            if ($globals->isPost()) {
                $okCsrf = static::checkCSRF($globals);

                if (!$okCsrf) {
                    $mess = ['message' => FwI18n::t('Auth_FailCheckCSRF')];

                    return ControllerTools::JSON($mess, status: 403);
                }
            }

            return null;
        }

        /**
         * @param IGlobalReqParams $globals
         * @param array $applyParams
         * @return ResponseInterface
         * @throws \Twig\Error\LoaderError
         * @throws \Twig\Error\RuntimeError
         * @throws \Twig\Error\SyntaxError
         */
        protected static function renderPage(IGlobalReqParams $globals, array $applyParams = []): ResponseInterface {
            $params = TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS);
            $params['js_assets'][] = FrameworkJsGen::framework();
            $params['js_assets'][] = FrameworkJsGen::auth();
            $params['js_assets'] = array_unique($params['js_assets']);

            if (!empty($applyParams)) {
                $params = array_merge($params, $applyParams);
            }

            $islandHtml = RenderIsland::render('auth2-container', $applyParams);
            $params['content'] = Twig::get()->render('Components/AuthMount.twig', [
                'island_html' => $islandHtml,
            ]);

            $render = HtmlLayout::render($params);

            if (!$globals->isDev()) {
                $render = HtmlMinify::get()->minify($render);
            }

            return ControllerTools::ok($render);
        }

        /**
         * @param IGlobalReqParams $globals
         * @param IRouterUriParams $params
         * @return ResponseInterface
         * @throws \Twig\Error\LoaderError
         * @throws \Twig\Error\RuntimeError
         * @throws \Twig\Error\SyntaxError
         * @throws Exception
         */
        protected static function processPhaseNullPost(IGlobalReqParams $globals, IRouterUriParams $params): ResponseInterface {
            $authEmail = $globals->readPostValue('auth_email', null);
            $authEmailStr = $authEmail . '';

            if (mb_strlen($authEmailStr) > 5 && stripos($authEmailStr, '@') > 0) {
                $rateLimitKey = 'email_auth:' . strtolower($authEmailStr);

                if (!RateLimit::hit($rateLimitKey, 5, 600)) {
                    return ControllerTools::JSON(['message' => FwI18n::t('Auth_TooManyRequests')], status: 429);
                }

                static::sendCode($globals, $authEmailStr);
                $mess = [
                    'message' => FwI18n::t('Auth_CodeSent'),
                    'codeLifeTime' => static::$codeSecondsTTL,
                    'codeInputTries' => static::$codeInputTries,
                ];

                return ControllerTools::JSON($mess, status: 200);
            }

            $mess = ['message' => FwI18n::t('Auth_EmailRequired')];

            return ControllerTools::JSON($mess, status: 401);
        }

        /**
         * @return void
         */
        protected static function closeAuthSession(): void {
            $session = Session::get();

            $session->setValue(static::PHASE_KEY, static::PHASE_NULL);

            $session->unsetValues([
                static::SESSION_AUTH_CODE,
                static::SESSION_AUTH_CODE_UT,
                static::SESSION_AUTH_TRIES,
                Account::SESSION_AUTH_LOGIN,
            ]);
        }

        /**
         * @param IGlobalReqParams $globals
         * @param IRouterUriParams $params
         * @return ResponseInterface
         * @throws \Twig\Error\LoaderError
         * @throws \Twig\Error\RuntimeError
         * @throws \Twig\Error\SyntaxError
         * @throws Exception
         */
        protected static function processPhaseSentCode(IGlobalReqParams $globals, IRouterUriParams $params): ResponseInterface {
            $session = Session::get();
            $sessionTries = intval($session->getValue(static::SESSION_AUTH_TRIES, '0'));

            $codeUt = intval($session->getValue(static::SESSION_AUTH_CODE_UT, '0'));
            $codeAge = abs(time() - $codeUt);
            $codeLifeTime = static::$codeSecondsTTL - $codeAge;

            if ($codeAge > static::$codeSecondsTTL) {
                static::closeAuthSession();

                return static::renderPage($globals, ['phase' => 'INPUT_EMAIL_AFTER_TIMEOUT']);
            }

            if ($sessionTries < 1) {
                return static::renderPage($globals, ['phase' => 'INPUT_EMAIL_AFTER_FAIL_TRIES']);
            }

            if ($sessionTries < static::$codeInputTries) {
                return static::renderPage($globals, [
                    'codeInputTries' => $sessionTries,
                    'codeLifeTime' => $codeLifeTime,
                    'phase' => 'INPUT_CODE_FAIL',
                ]);
            }

            return static::renderPage($globals, [
                'code_input_tries' => $sessionTries,
                'code_life_time' => $codeLifeTime,
                'phase' => 'INPUT_CODE',
            ]);
        }

        /**
         * @param IGlobalReqParams $globals
         * @param IRouterUriParams $params
         * @return ResponseInterface
         * @throws \Twig\Error\LoaderError
         * @throws \Twig\Error\RuntimeError
         * @throws \Twig\Error\SyntaxError
         * @throws Exception
         */
        protected static function processPhaseSentCodePost(IGlobalReqParams $globals, IRouterUriParams $params): ResponseInterface {
            $postCode = $globals->readPostValue('code', null);
            $postCodeStr = $postCode . '';

            $session = Session::get();
            $sessionCode = $session->getValue(static::SESSION_AUTH_CODE, '');
            $sessionEmail = $session->getValue(Account::SESSION_AUTH_LOGIN, '');
            $sessionTries = intval($session->getValue(static::SESSION_AUTH_TRIES, '0'));

            $codeUt = intval($session->getValue(static::SESSION_AUTH_CODE_UT, '0'));
            $codeAge = abs(time() - $codeUt);
            $codeLifeTime = static::$codeSecondsTTL - $codeAge;

            if ($codeAge > static::$codeSecondsTTL) {
                static::closeAuthSession();
            }

            if (empty($sessionEmail) || empty($sessionCode) || mb_strlen($sessionCode) < 8) {
                $mess = ['message' => FwI18n::t('Common_RequestError') . ' #empty_session'];

                return ControllerTools::JSON($mess, status: 401);
            }

            if ($codeAge > static::$codeSecondsTTL) {
                return ControllerTools::JSON([
                    'success' => false,
                    'codeInputTries' => 0,
                    'codeLifeTime' => $codeLifeTime,
                    'timeout' => true
                ], status: 200);
            }

            if ($sessionTries < 1) {
                static::closeAuthSession();

                return ControllerTools::JSON([
                    'success' => false,
                    'codeInputTries' => 0,
                    'codeLifeTime' => $codeLifeTime
                ], status: 200);
            }

            if ($postCodeStr === $sessionCode) {
                $session->setValue(static::PHASE_KEY, static::PHASE_DONE);

                $session->unsetValues([
                    static::SESSION_AUTH_CODE,
                    static::SESSION_AUTH_CODE_UT,
                    static::SESSION_AUTH_TRIES,
                ]);

                // Verify succeeded — only NOW the account row is materialised.
                // fromSession() is read-only and would return id=0 stub here
                // for first-time logins.
                $account = Account::touchAccount($sessionEmail, 'email');
                $time = time();
                $account->setParam('last_auth_time', $time);
                $account->setParam('last_online_time', $time);

                static::sendSuccessLogin($globals, $sessionEmail);

                return ControllerTools::JSON(['success' => true], status: 200);
            }

            // that was the last attempt
            if ($sessionTries === 1) {
                static::closeAuthSession();

                return ControllerTools::JSON([
                    'success' => false,
                    'codeInputTries' => 0,
                    'codeLifeTime' => $codeLifeTime
                ], status: 200);
            }

            $newSessionTries = $sessionTries - 1;
            $session->setValue(static::SESSION_AUTH_TRIES, $newSessionTries . '');

            return ControllerTools::JSON([
                'success' => false,
                'codeInputTries' => $newSessionTries,
                'codeLifeTime' => $codeLifeTime
            ], status: 200);
        }

        /**
         * @param IGlobalReqParams $globals
         * @param string $authEmail
         * @return void
         * @throws \Twig\Error\LoaderError
         * @throws LoggerException
         * @throws \Twig\Error\RuntimeError
         * @throws \Twig\Error\SyntaxError
         */
        protected static function sendCode(IGlobalReqParams $globals, string $authEmail): void {
            $session = Session::get();
            $mailer = Mailer::get();
            $twig = Twig::get();

            $authCode = StrTools::randomString(max(8, static::$authCodeLen));
            $render = $twig->render('Email/Email.twig', static::authEmailParams($globals, $authCode));
            $render = HtmlMinify::get()->minify($render);

            $mailer->sendHtmlMail($authEmail, FwI18n::t('Auth'), $render);

            $session->setValue(static::PHASE_KEY, static::PHASE_SENT_CODE);
            $session->setValue(static::SESSION_AUTH_CODE, $authCode);
            $session->setValue(static::SESSION_AUTH_CODE_UT, time() . '');
            $session->setValue(static::SESSION_AUTH_TRIES, static::$codeInputTries . '');
            $session->setValue(Account::SESSION_AUTH_LOGIN, $authEmail);
        }

        /**
         * @param IGlobalReqParams $globals
         * @param string $authEmail
         * @return void
         * @throws \Twig\Error\LoaderError
         * @throws LoggerException
         * @throws \Twig\Error\RuntimeError
         * @throws \Twig\Error\SyntaxError
         */
        protected static function sendSuccessLogin(IGlobalReqParams $globals, string $authEmail): void {
            $mailer = Mailer::get();
            $twig = Twig::get();
            $result = TwigParams::init()->get(TwigParams::DEF_EMAIL_PARAMS);

            // Render the login-time in the recipient's tz, not the server's:
            // emails must show times the user can read on their own clock.
            $recipientTz = Account::touchAccount($authEmail, 'email')->readParam('time_zone');
            $loginTime = DateTools::formatForUser(time(), $recipientTz, 'H:i');

            $result['info_blocks'] = [
                [
                    'title' => FwI18n::t('Email_Auth_SuccessLogin_Title'),
                    'rows' => [
                        FwI18n::t('Email_Auth_SuccessLogin_A', [$globals->ip(), $loginTime]),
                        FwI18n::t('Email_Auth_SuccessLogin_B'),
                    ],
                ],
            ];

            $render = $twig->render('Email/Email.twig', $result);
            $render = HtmlMinify::get()->minify($render);

            $mailer->sendHtmlMail($authEmail, FwI18n::t('Auth'), $render);
        }

        /**
         * @param IGlobalReqParams $globals
         * @param string $code
         * @return array
         * @throws \Twig\Error\LoaderError
         * @throws \Twig\Error\RuntimeError
         * @throws \Twig\Error\SyntaxError
         * @throws LoggerException
         */
        public static function authEmailParams(IGlobalReqParams $globals, string $code): array {
            $twig = Twig::get();
            $row = fn (string $str, string $align = 'left') => $twig->render('Email/Row.twig', ['row' => $str, 'align' => $align]);
            $button = fn (string $text, string $href) => $twig->render('Email/ButtonMain.twig', ['text' => $text, 'href' => $href]);

            $result = TwigParams::init()->get(TwigParams::DEF_EMAIL_PARAMS);
            $ttl = floor(static::$codeSecondsTTL / 60);

            // See EmailAuthMiddleware::authEmailParams — HTTP_REFERER is
            // attacker-controlled (any external page can set it before
            // posting), so it can't decide where the magic-link points.
            // Build from app.ini base_url + the current request URI.
            $baseUrl = rtrim(AppConfig::get(IniConfig::ENV_APP)->paramString('base_url'), '/');
            $uri = $globals->getUri() ?: '/';
            $authButton = $button(FwI18n::t('Auth_Login'), $baseUrl . $uri . '#token=' . $code);

            $result['info_blocks'] = [
                [
                    'title' => FwI18n::t('Auth'),
                    'rows' => [
                        FwI18n::t('Email_Auth_Hello'),
                        ['raw' => $row(sprintf(FwI18n::t('Email_Auth_CodeLifetime'), $ttl), 'center')],
                        ['raw' => $row($twig->render('Email/CodeHighlight.twig', ['code' => $code]), 'center')],
                        ['raw' => $row(FwI18n::t('Email_Auth_UseAuthButton'), 'center')],
                        ['raw' => $row($authButton, 'center')],
                        ['raw' => $row(FwI18n::t('Email_Auth_UseAuthButtonOnlyForYou'), 'center')],
                        FwI18n::t('Email_Auth_CodeIsSecret'),
                        FwI18n::t('Email_Auth_TeamSign'),
                    ],
                ],
            ];

            return $result;
        }
    }
}

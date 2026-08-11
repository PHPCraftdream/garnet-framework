<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares {
    use Exception;
    use PHPCraftdream\Garnet\Bundle\FrameworkJsGen;
    use PHPCraftdream\Garnet\Bundle\I18n\FwI18n;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\AuthStrategy\AuthConfig;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\AuthStrategy\AuthStrategyInterface;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\FwMagicLoginService;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\FwAppMailer;
    use PHPCraftdream\Garnet\Bundle\Modules\SystemSettings\FwAppSettings;
    use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Core\Env\TestScope;
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
    use Throwable;

    class EmailAuthMiddleware implements AuthStrategyInterface {
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

        public static function getMethodName(): string {
            return 'email';
        }

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
                $action = strtolower((string)$globals->readPostValue('action', ''));

                if ($action === 'start-session') {
                    return static::processStartSession($globals, $params);
                }

                $originErrorResponse = static::processOrigin($globals);

                if (!empty($originErrorResponse)) {
                    return $originErrorResponse;
                }

                $csrfErrorResponse = static::processCSRF($globals);

                if (!empty($csrfErrorResponse)) {
                    return $csrfErrorResponse;
                }

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
            $postToken = $globals->readPostValue(static::CSRF_TOKEN, null);

            if ($postToken === null || $postToken === '') {
                return false;
            }

            $sessionToken = Session::touchCSRF_();

            if ($sessionToken === '') {
                return false;
            }

            return hash_equals($sessionToken, (string)$postToken);
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

        protected static function processStartSession(IGlobalReqParams $globals, IRouterUriParams $params): ResponseInterface {
            $consentPd = (string)$globals->readPostValue('consent_pd', '');

            if ($consentPd !== '1') {
                return ControllerTools::JSON([
                    'success' => false,
                    'message' => FwI18n::t('Consent_PD_Required_Error'),
                ], status: 400);
            }

            $session = Session::get();
            $session->setValue('consent_pd_at', (string)time());

            $consentMk = (string)$globals->readPostValue('consent_marketing', '');

            if ($consentMk === '1') {
                $session->setValue('consent_marketing_at', (string)time());
            }

            $csrf = Session::touchCSRF_();

            return ControllerTools::JSON([
                'success' => true,
                'csrf' => $csrf,
            ], status: 200);
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
            // The INPUT_EMAIL page stays consent-gated: CSRF is minted only on
            // the consent click (processStartSession), and the layout uses
            // peekCSRF_ (existing token or '').
            //
            // The code-entry phases are different: they're only reachable AFTER
            // consent (you must request a code first). A fresh tab/device that
            // lands directly on the code-entry screen (e.g. after the one-click
            // magic-login link failed validation and fell back here) may never
            // have replayed the CSRF cookie, so the manual-code-submit POST
            // needs a valid token regardless. Mint a fresh one here and inject
            // it into these phases.
            $phase = (string)($applyParams['phase'] ?? '');

            if (str_starts_with($phase, 'INPUT_CODE')) {
                $applyParams['csrf'] = Session::touchCSRF_();
            }

            $params = TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS);
            $params['js_assets'][] = FrameworkJsGen::framework();
            $params['js_assets'][] = FrameworkJsGen::auth();
            $params['js_assets'] = array_unique($params['js_assets']);

            if (!empty($applyParams)) {
                $params = array_merge($params, $applyParams);
            }

            $params['content'] = static::buildPageContent($applyParams);

            $render = HtmlLayout::render($params);

            if (!$globals->isDev()) {
                $render = HtmlMinify::get()->minify($render);
            }

            return ControllerTools::ok($render);
        }

        /**
         * Build the body of the auth page. Override in app-level
         * subclasses to wrap the auth widget with extra chrome (e.g.
         * a marketing header / footer pulled from static-page snippets).
         */
        protected static function buildPageContent(array $applyParams): string {
            // Island renders the `auth2-container-init` mount point; AuthMount
            // wraps it with the layout chrome (max width + centred padding).
            // JSON encoding inside RenderIsland strips <, >, &, ', ", so the
            // value is safe to drop into a single-quoted attribute.
            $islandHtml = RenderIsland::render('auth2-container', $applyParams);

            return Twig::get()->render('Components/AuthMount.twig', [
                'island_html' => $islandHtml,
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
        protected static function processPhaseNullPost(IGlobalReqParams $globals, IRouterUriParams $params): ResponseInterface {
            $authEmail = $globals->readPostValue('auth_email', null);
            $authEmailStr = $authEmail . '';

            if (mb_strlen($authEmailStr) > 5 && stripos($authEmailStr, '@') > 0) {
                // Registration gate. When the admin toggle is off, refuse to
                // mint codes for unknown emails. Two carve-outs: (a) existing
                // account — re-login, not a registration; (b) email on the
                // site's own domain so operators can always get in.
                $registrationGateOwnDomainNotice = false;

                if (!FwAppSettings::registrationsEnabled()) {
                    $isExisting = Account::get($authEmailStr)->id() > 0;
                    $isOwnDomain = static::emailMatchesSiteDomain($authEmailStr);
                    // Test-scope carve-out: an authorized UI-test run (token
                    // file + matching header) may freely register `*.test`
                    // mailboxes. They never receive real email (FwAppMailer
                    // skips the send) and live only in the `test_worker_0`
                    // scope, so this can't mint codes for real addresses.
                    $isTestScope = TestScope::isActive()
                        && str_ends_with(strtolower($authEmailStr), '.test');

                    if (!$isExisting && !$isOwnDomain && !$isTestScope) {
                        $contact = FwAppSettings::supportContacts()['email'];

                        return ControllerTools::JSON([
                            'message' => FwI18n::t('Auth_RegistrationsDisabled', [$contact]),
                        ], status: 403);
                    }

                    if (!$isExisting && $isOwnDomain) {
                        $registrationGateOwnDomainNotice = true;
                    }
                }

                // Per-IP rate limit: caps distinct-email attempts from a single IP.
                // 30 attempts per IP per 10 minutes, regardless of target address.
                // Prevents SMTP abuse by rotating through many recipient addresses.
                $ipRateLimitKey = 'email_auth_ip:' . $globals->ip();

                if (!RateLimit::hit($ipRateLimitKey, 30, 600)) {
                    return ControllerTools::JSON(['message' => FwI18n::t('Auth_TooManyRequests')], status: 429);
                }

                // Per-email rate limit: caps attempts to a single recipient address.
                // 5 attempts per address per 10 minutes.
                $rateLimitKey = 'email_auth:' . strtolower($authEmailStr);

                if (!RateLimit::hit($rateLimitKey, 5, 600)) {
                    return ControllerTools::JSON(['message' => FwI18n::t('Auth_TooManyRequests')], status: 429);
                }

                static::sendCode($globals, $authEmailStr);
                $messageText = $registrationGateOwnDomainNotice
                    ? FwI18n::t('Auth_RegistrationsDisabledOwnDomainOk')
                    : FwI18n::t('Auth_CodeSent');
                $mess = [
                    'message' => $messageText,
                    'codeLifeTime' => static::$codeSecondsTTL,
                    'codeInputTries' => static::$codeInputTries,
                ];

                return ControllerTools::JSON($mess, status: 200);
            }

            $mess = ['message' => FwI18n::t('Auth_EmailRequired')];

            return ControllerTools::JSON($mess, status: 401);
        }

        /**
         * Compares the domain part of $email to the host part of app.ini's
         * `base_url`. Used as a carve-out for the registration gate: even
         * with registrations disabled, an address on the site's own domain
         * (e.g. admin@example.com) must still get in.
         */
        protected static function emailMatchesSiteDomain(string $email): bool {
            $atPos = strrpos($email, '@');

            if ($atPos === false) {
                return false;
            }
            $emailDomain = strtolower(substr($email, $atPos + 1));

            if ($emailDomain === '') {
                return false;
            }

            try {
                $baseUrl = IniConfig::app()->paramString('base_url', '');
            } catch (Throwable) {
                return false;
            }

            if ($baseUrl === '') {
                return false;
            }

            $host = parse_url($baseUrl, PHP_URL_HOST);

            if (!is_string($host) || $host === '') {
                return false;
            }
            $host = strtolower(preg_replace('/^www\./', '', $host));

            return $emailDomain === $host;
        }

        /**
         * @return void
         */
        protected static function closeAuthSession(): void {
            $session = Session::get();

            // Rotate the session identifier on the authenticated->anonymous
            // transition — the symmetric counterpart of completeLogin()'s
            // anonymous->authenticated rotate(). Without this, logout leaves
            // the session cookie value (and its DB row) unchanged, so any
            // non-auth session data accumulated while authenticated (cart,
            // preferences, ...) survives the logout boundary under the SAME
            // identifier and is inherited by the next user of a shared/kiosk
            // browser. rotate() also reissues the CSRF token.
            //
            // Ordering mirrors completeLogin(): rotate() FIRST, then the
            // setValue()/unsetValues() calls. rotate() persists ONLY
            // $changedValues to the new row on flush() — it does NOT clear
            // $this->sessionData (see the rotate() docblock) — so setValue()
            // MUST run AFTER rotate() to land PHASE_NULL in $changedValues,
            // where flush() writes it under the fresh anonymous identifier.
            // The trailing unsetValues([...]) is then redundant for the NEW
            // row (rotate() never copied those keys forward) but NOT for this
            // request: $sessionData still holds the pre-rotation auth values,
            // so a later getValue() in the same response must see them gone.
            // Safe on an already-anonymous session too — rotate() guards the
            // old-row delete on `if (!empty($oldSessionId))` and mints a fresh
            // identifier regardless.
            $session->rotate();
            $session->setValue(static::PHASE_KEY, static::PHASE_NULL);

            $session->unsetValues([
                static::SESSION_AUTH_CODE,
                static::SESSION_AUTH_CODE_UT,
                static::SESSION_AUTH_TRIES,
                Account::SESSION_AUTH_LOGIN,
            ]);
        }

        /**
         * Complete the login for $email in the CURRENT session/request — shared
         * by the manual code-verification success path (processPhaseSentCodePost)
         * and the one-click magic-login controller (FwMagicLoginController),
         * which logs the user in from a token validated independently of any
         * session. Called via `static::` (not self::) so app-level overrides of
         * sendSuccessLogin() (e.g. IrabiAuthMiddleware's consent-journal audit
         * write) still fire correctly through late static binding.
         *
         * $consentPdAt/$consentMarketingAt let a caller seed the session's
         * consent timestamps before completing login — needed by the magic-login
         * path, where the CURRENT session never went through processStartSession()
         * and so has no consent values of its own; the magic-login token carries
         * a snapshot taken when the code was originally requested. Pass '' (the
         * default) to leave the session's existing values untouched — this is
         * what the manual code-verification path does, since its session already
         * has them from processStartSession().
         */
        public static function completeLogin(IGlobalReqParams $globals, string $email, string $consentPdAt = '', string $consentMarketingAt = ''): void {
            $session = Session::get();

            if ($consentPdAt !== '') {
                $session->setValue('consent_pd_at', $consentPdAt);
            }

            if ($consentMarketingAt !== '') {
                $session->setValue('consent_marketing_at', $consentMarketingAt);
            }

            // Rotate the session identifier on the anonymous->authenticated
            // transition so a pre-login cookie value (possibly planted by
            // an attacker before the victim logged in, or replayed from the
            // magic-login flow) can never carry over into an authenticated
            // session (session fixation).
            $session->rotate();
            $session->setValue(static::PHASE_KEY, static::PHASE_DONE);
            $session->setValue(Account::SESSION_AUTH_LOGIN, $email);

            $session->unsetValues([
                static::SESSION_AUTH_CODE,
                static::SESSION_AUTH_CODE_UT,
                static::SESSION_AUTH_TRIES,
            ]);

            $account = Account::touchAccount($email, 'email');
            $time = time();
            $account->setParam('last_auth_time', $time);
            $account->setParam('last_online_time', $time);

            $sessionConsentPd = $session->getValue('consent_pd_at', '');

            if ($sessionConsentPd !== '' && empty($account->readParam(Account::PARAM_CONSENT_PD_AT))) {
                $account->setParam(Account::PARAM_CONSENT_PD_AT, $sessionConsentPd);
            }

            $sessionConsentMarketing = $session->getValue('consent_marketing_at', '');

            if ($sessionConsentMarketing !== '') {
                $account->setParam(Account::PARAM_CONSENT_MARKETING_AT, $sessionConsentMarketing);
            }

            static::sendSuccessLogin($globals, $email);
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

            if (hash_equals($sessionCode, $postCodeStr)) {
                static::completeLogin($globals, $sessionEmail);

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

            // One-click magic-login token: a 32-char, single-use, 5-minute code
            // carried as a URL parameter (not a #hash) on a dedicated endpoint —
            // unlike the 8-char code above (session-scoped, manual entry only),
            // this one authenticates standalone, independent of which browser
            // context opens the link. Replaces the old hash-based auto-verify,
            // which silently failed whenever the link was opened in a different
            // session than the one that requested the code (e.g. a webmail
            // client's in-app browser).
            $returnUri = $globals->getUri() ?: '/';
            $consentPdAt = (string)$session->getValue('consent_pd_at', '');
            $consentMarketingAt = (string)$session->getValue('consent_marketing_at', '');
            $magicToken = FwMagicLoginService::generate($authEmail, $returnUri, $consentPdAt, $consentMarketingAt)['token'];

            $render = $twig->render('Email/Email.twig', static::authEmailParams($globals, $authCode, $magicToken));
            $render = HtmlMinify::get()->minify($render);

            FwAppMailer::setNextMeta(['auth_code' => $authCode]);
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
         * Path of the one-click magic-login endpoint, relative to base_url.
         * Apps register a route at exactly this path (see FwMagicLoginController)
         * — override this if a concrete app needs a different path, but the
         * default matches the framework's own FwMagicLoginController convention.
         *
         * Deliberately does NOT start with `/~`: RouterUriParams::fromGlobals()
         * finds the LAST `/~` in the whole URI to split off an explicit
         * method name/params — a controller URL beginning with `/~` collides
         * with that convention (the base path itself gets parsed as the
         * method-dispatch marker, so `code~<token>` never reaches the
         * controller as a URI param at all). Same reasoning as why
         * RegisterController::URL is `/first-step`, not `/~first-step`.
         */
        protected static function magicLoginUrl(string $magicToken): string {
            return '/magic-login/code~' . $magicToken;
        }

        /**
         * @param IGlobalReqParams $globals
         * @param string $code
         * @param string $magicToken
         * @return array
         * @throws \Twig\Error\LoaderError
         * @throws \Twig\Error\RuntimeError
         * @throws \Twig\Error\SyntaxError
         * @throws LoggerException
         */
        public static function authEmailParams(IGlobalReqParams $globals, string $code, string $magicToken): array {
            $twig = Twig::get();
            $row = fn (string $str, string $align = 'left') => $twig->render('Email/Row.twig', ['row' => $str, 'align' => $align]);
            $button = fn (string $text, string $href) => $twig->render('Email/ButtonMain.twig', ['text' => $text, 'href' => $href]);

            $result = TwigParams::init()->get(TwigParams::DEF_EMAIL_PARAMS);
            $ttl = floor(static::$codeSecondsTTL / 60);

            // Build the magic-link URL from app.ini base_url + the dedicated
            // magic-login endpoint path, NOT from HTTP_REFERER and NOT as a
            // URL fragment (#token=...). Referer can be empty (programmatic
            // POST, browser privacy modes, some proxies), and a fragment
            // never reaches the server, so a hash-based link couldn't be
            // verified independently of the session that requested it.
            $baseUrl = rtrim(AppConfig::get(IniConfig::ENV_APP)->paramString('base_url'), '/');
            $authButton = $button(FwI18n::t('Auth_Login'), $baseUrl . static::magicLoginUrl($magicToken));

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

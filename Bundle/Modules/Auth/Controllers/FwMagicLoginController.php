<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Auth\Controllers {
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\FwMagicLoginService;
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;

    /**
     * One-click magic-login endpoint: `/magic-login/code~{32-char token}`
     * (see EmailAuthMiddleware::magicLoginUrl() — the path deliberately
     * does not start with `/~`, which would collide with the router's
     * own `/path/~methodName` convention). Validates and atomically
     * consumes the token, then logs the user in via the app's auth
     * middleware and redirects to the originally requested page.
     *
     * Replaces the old hash-based `#token=` auto-verify, which relied on
     * client-side JS replaying the token from the URL fragment against the
     * SAME session that requested it — this endpoint authenticates the
     * token itself, independent of which browser/session opens the link.
     */
    abstract class FwMagicLoginController extends FrameworkController {
        /**
         * @return class-string EmailAuthMiddleware (or an app-level subclass,
         * e.g. IrabiAuthMiddleware) — completeLogin() is invoked through this
         * class so an app-level override of sendSuccessLogin() still fires
         * via late static binding.
         */
        abstract protected static function authMiddlewareClass(): string;

        /**
         * Turns the return_uri stored on the token (a relative path, e.g.
         * "/first-step/token~INVITE") into the final redirect target — the
         * app prepends its own base_url/route-prefix (e.g. via IRabi::url()).
         */
        abstract protected static function buildRedirectUrl(string $returnUri): string;

        /**
         * Renders the error page for an invalid/expired/already-used link.
         * $reason is one of 'unknown'|'expired'|'used'.
         */
        abstract protected static function renderError(IGlobalReqParams $globals, string $reason): mixed;

        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $code = (string)$params->getUriParam('code');
            $validation = FwMagicLoginService::validate($code);

            if (!$validation['valid']) {
                return static::renderError($globals, $validation['reason']);
            }

            $tokenRow = $validation['token'];

            // Consume atomically BEFORE logging in — a race (e.g. an email
            // client's link-prefetcher, or the user double-clicking) must let
            // only ONE request through; the loser gets the same "used" error
            // page as a genuinely already-used link, never a duplicate login.
            if (!FwMagicLoginService::consume((int)$tokenRow['id'])) {
                return static::renderError($globals, 'used');
            }

            $authClass = static::authMiddlewareClass();
            $authClass::completeLogin(
                $globals,
                (string)$tokenRow['email'],
                (string)$tokenRow['consent_pd_at'],
                (string)$tokenRow['consent_marketing_at'],
            );

            $returnUri = (string)($tokenRow['return_uri'] ?? '');
            $redirectTarget = static::buildRedirectUrl($returnUri !== '' ? $returnUri : '/');

            return ControllerTools::redirect($redirectTarget);
        }
    }
}

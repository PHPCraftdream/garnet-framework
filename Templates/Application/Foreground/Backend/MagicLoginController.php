<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Foreground\Backend {
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\Controllers\FwMagicLoginController;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares\EmailAuthMiddleware;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;

    /**
     * One-click magic-login endpoint: GET /magic-login/code~{32-char token}.
     *
     * Serves the link EmailAuthMiddleware::magicLoginUrl() embeds as the
     * CTA button in every login email. Without this route, that link 404s
     * on every freshly-scaffolded app — the scaffold wires token
     * generation (FwMagicLoginService::setTableClasses) but until now
     * never served the endpoint the link points to.
     *
     * All validate/consume/login/redirect flow lives in the framework
     * base FwMagicLoginController; this subclass only wires app-specific
     * bits: which auth middleware to complete the login through, how to
     * turn the stored return_uri into a redirect URL, and how to render
     * the error page for an invalid/expired/used link.
     *
     * Registered at URL . '/{code}', NOT just URL: RouterUriParams
     * normalizes `/code~<token>` into the `/{code}` URI-param placeholder
     * (see RouterUriParams::fromGlobals), so the route key must include
     * that placeholder to match. Deliberately not `/~magic-login` — that
     * would collide with the router's `/path/~methodName` convention,
     * which finds the LAST `/~` to split off a method name and would
     * swallow `code~<token>` as method-dispatch input instead of a URI
     * param. Same shape as the existing AccountController::URL convention.
     */
    class MagicLoginController extends FwMagicLoginController {
        public const URL = '/magic-login';

        protected static function authMiddlewareClass(): string {
            return EmailAuthMiddleware::class;
        }

        /**
         * $returnUri is captured at code-request time as $globals->getUri()
         * (see EmailAuthMiddleware::sendCode) — already relative to the
         * domain root. The bare scaffold mounts no route prefix, so the
         * stored URI needs no re-prefixing here.
         */
        protected static function buildRedirectUrl(string $returnUri): string {
            return $returnUri;
        }

        /**
         * Renders a minimal error page for an invalid/expired/already-used
         * link. $reason is one of 'unknown'|'expired'|'used'. Inlines the
         * twig params the same way CommonController::commonTwigParams()
         * does — that helper is protected, and this class extends
         * FwMagicLoginController (not CommonController; PHP has no multiple
         * inheritance), so the params array is duplicated here verbatim.
         */
        protected static function renderError(IGlobalReqParams $globals, string $reason): mixed {
            $messages = [
                'unknown' => 'This login link is not valid.',
                'expired' => 'This login link has expired.',
                'used' => 'This login link has already been used.',
            ];

            $content = '<h1>' . ($messages[$reason] ?? $messages['unknown']) . '</h1>';

            $render = Twig::get()->render(
                'Foreground/main.twig',
                TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS, [
                    'styles_assets' => [],
                    'js_assets' => [],
                    'content' => $content,
                ]),
            );

            return ControllerTools::ok($render);
        }
    }
}

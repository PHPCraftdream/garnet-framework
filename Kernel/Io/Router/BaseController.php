<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Router {
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;
    use Psr\Http\Message\ResponseInterface;
    use Throwable;

    class BaseController {
        /** @var (callable(IGlobalReqParams, IRouterUriParams): ?ResponseInterface)|null */
        protected static $custom404Handler = null;

        /**
         * Register a custom 404 handler. Called before the default fallback.
         * If the handler returns a ResponseInterface, it's used; if null, fallback kicks in.
         */
        public static function setCustom404Handler(callable $handler): void {
            static::$custom404Handler = $handler;
        }

        /**
         * @return Twig
         */
        protected static function getTwig(): Twig {
            return Twig::get();
        }

        /**
         * @param IGlobalReqParams $globals
         * @param IRouterUriParams $params
         * @param string $error
         * @return ResponseInterface
         */
        public static function internal_error_500(IGlobalReqParams $globals, IRouterUriParams $params, string $error): ResponseInterface {
            if ($globals->isPost()) {
                $err = ErrorTools::makeErrorJsonArr($globals->isDev(), $error);

                return ControllerTools::JSON($err, status: 500);
            }

            $error = static::makeErrorPage('Internal server error', $error, $globals->isDev());

            return ControllerTools::internalError($error);
        }

        /**
         * @param IGlobalReqParams $globals
         * @param IRouterUriParams $params
         * @return ResponseInterface
         */
        public static function not_found_404(IGlobalReqParams $globals, IRouterUriParams $params): ResponseInterface {
            $error = 'Page not found';

            if ($globals->isPost()) {
                $err = ['code' => 404, 'message' => $error];

                return ControllerTools::JSON($err, status: 404);
            }

            // Try custom 404 handler (e.g. static page with slug "404")
            if (static::$custom404Handler !== null) {
                try {
                    $customResponse = (static::$custom404Handler)($globals, $params);

                    if ($customResponse instanceof ResponseInterface) {
                        return $customResponse;
                    }
                } catch (Throwable) {
                    // Fallback to default on any error
                }
            }

            $result = static::render404Fallback();

            return ControllerTools::notFound($result);
        }

        /**
         * Beautiful standalone 404 page — no external CSS/JS dependencies.
         * Primary path renders via Twig (Layout/Error404Fallback.twig). Since
         * this is itself a last-resort fallback path, we catch any Twig
         * failure and emit a minimal plain-text response so the user always
         * sees *something*.
         */
        public static function render404Fallback(): string {
            $vars = [
                'title' => '404',
                'code' => '404',
                'home_href' => '/',
                'home_title' => 'Home',
                'back_href' => 'javascript:history.back()',
                'back_title' => 'Back',
            ];

            try {
                return Twig::get()->render('Layout/Error404Fallback.twig', $vars);
            } catch (Throwable) {
                return '<!doctype html><html lang="en"><head><meta charset="utf-8"/><title>404</title></head><body><h1>404</h1><p>Page not found.</p><p><a href="/">Home</a></p></body></html>';
            }
        }

        /**
         * @param string $title
         * @param string $error
         * @param bool $isLocal
         * @return string
         */
        protected static function makeErrorPage(string $title, string $error, bool $isLocal): string {
            return ErrorTools::makeErrorPageHtml($title, $error, $isLocal);
        }
    }
}

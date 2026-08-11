<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Common\Backend {
    use PHPCraftdream\Garnet\Kernel\Core\Env\Env;
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;
    use Psr\Http\Message\ResponseInterface;

    class CommonController extends FrameworkController {
        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $twig = Twig::get();

            $render = $twig->render(
                'Layout/Main.twig',
                TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS, [
                    'content' => '<h1>Hello, World!</h1>',
                ])
            );

            return ControllerTools::ok($render);
        }

        protected static function commonTwigParams(IGlobalReqParams $globals): array {
            $params = TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS, [
                'styles_assets' => [
                ],
                'js_assets' => [
                ],
            ]);

            return $params;
        }

        public static function not_found_404(IGlobalReqParams $globals, IRouterUriParams $params): ResponseInterface {
            // Callers doing a JSON POST expect a JSON error back, not an
            // HTML page; matches the framework's own
            // BaseController::not_found_404 convention.
            if ($globals->isPost()) {
                return ControllerTools::JSON(['code' => 404, 'message' => 'Page not found'], status: 404);
            }

            $twig = Twig::get();

            $twigParams = self::commonTwigParams($globals);

            return ControllerTools::notFound($twig->render('Foreground/not_found_404.twig', $twigParams));
        }

        public static function internal_error_500(IGlobalReqParams $globals, IRouterUriParams $params, string $error): ResponseInterface {
            // Same reasoning as not_found_404 above: a POST expects JSON
            // back, even on an unhandled exception.
            if ($globals->isPost()) {
                $err = ['code' => 500, 'message' => 'Internal server error'];

                if ($globals->isDev() && Env::isDevDir()) {
                    $err['details'] = $error;
                }

                return ControllerTools::JSON($err, status: 500);
            }

            $twig = Twig::get();
            $twigParams = self::commonTwigParams($globals);

            if ($globals->isDev() && Env::isDevDir()) {
                $twigParams['errorContent'] = $error;
            }

            return ControllerTools::internalError($twig->render('Foreground/internal_error_500.twig', $twigParams));
        }
    }
}

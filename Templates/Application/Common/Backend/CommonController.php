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
            $twig = Twig::get();

            $twigParams = self::commonTwigParams($globals);

            return ControllerTools::notFound($twig->render('Foreground/not_found_404.twig', $twigParams));
        }

        public static function internal_error_500(IGlobalReqParams $globals, IRouterUriParams $params, string $error): ResponseInterface {
            $twig = Twig::get();
            $twigParams = self::commonTwigParams($globals);

            if ($globals->isDev() && Env::isDevDir()) {
                $twigParams['errorContent'] = $error;
            }

            return ControllerTools::internalError($twig->render('Foreground/internal_error_500.twig', $twigParams));
        }
    }
}

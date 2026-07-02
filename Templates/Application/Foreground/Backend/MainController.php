<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Foreground\Backend {
    use PHPCraftdream\Application\Common\Backend\CommonController;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;

    class MainController extends CommonController {
        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $twig = Twig::get();

            $render = $twig->render(
                'Foreground/main.twig',
                [
                    ...static::commonTwigParams($globals),
                    'content' => '<h1>Hello, World!</h1>',
                ],
            );

            return ControllerTools::ok($render);
        }
    }
}

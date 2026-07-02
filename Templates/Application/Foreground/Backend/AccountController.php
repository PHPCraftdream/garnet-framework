<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Foreground\Backend {
    use PHPCraftdream\Application\Common\Backend\CommonController;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;

    /**
     * Reference route gated by EmailAuthMiddleware::authOnly (wired in
     * Application.php::runWebApp) — demonstrates the framework's built-in
     * email-code auth flow. `/` itself stays public by default so a fresh
     * scaffold doesn't force every visitor through a login form; apps that
     * want the whole site behind auth can move this middleware onto `/`
     * (or any other route) instead.
     */
    class AccountController extends CommonController {
        public const URL = '/account';

        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $twig = Twig::get();
            $login = Account::fromSession()?->readParam('login') ?? '';

            $render = $twig->render(
                'Foreground/main.twig',
                [
                    ...static::commonTwigParams($globals),
                    'content' => '<h1>Signed in' . ($login !== '' ? ' as ' . htmlspecialchars($login) : '') . '</h1>',
                ],
            );

            return ControllerTools::ok($render);
        }
    }
}

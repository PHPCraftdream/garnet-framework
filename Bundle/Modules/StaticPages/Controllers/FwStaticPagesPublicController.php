<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\StaticPages\Controllers {
    use PHPCraftdream\Garnet\Bundle\Modules\StaticPages\FwStaticPagesService;
    use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;

    abstract class FwStaticPagesPublicController extends FrameworkController {
        abstract protected static function service(): FwStaticPagesService;

        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $slug = (string)$params->getUriParam('view');

            if ($slug === '') {
                return static::not_found_404($globals, $params);
            }

            $page = static::service()::getPublishedPageBySlug($slug);

            if (!$page) {
                return static::not_found_404($globals, $params);
            }

            // Check visibility access
            $account = Account::fromSession();
            $isLoggedIn = $account && $account->id();
            $visibility = (string)($page['visibility'] ?? 'all');

            if ($visibility !== 'all') {
                if ($visibility === 'guest' && $isLoggedIn) {
                    return static::not_found_404($globals, $params);
                }

                if ($visibility === 'auth' && !$isLoggedIn) {
                    return static::not_found_404($globals, $params);
                }

                if ($visibility === 'moderator') {
                    $isModerator = $isLoggedIn && ($account->isAdmin() || $account->isOwner() || $account->isModerator());

                    if (!$isModerator) {
                        return static::not_found_404($globals, $params);
                    }
                }
            }
            $isMod = $isLoggedIn && ($account->isAdmin() || $account->isOwner() || $account->isModerator());
            $blocksHtml = static::service()::renderBlocksToHtml($page['blocks'] ?? [], $isLoggedIn, $isMod);

            $body = static::service()::renderPageBody($page, $blocksHtml);
            $content = static::service()::renderPageShell($page, $body);

            $layoutParams = TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS, [
                'content' => $content,
                'top_menu_items' => [],
                'side_menu_items' => [],
            ]);
            $layoutParams['tz_banner'] = false;
            // Static pages render their own full-bleed header/footer in `content`.
            $layoutParams['bare_main'] = true;
            $layoutParams = array_merge($layoutParams, FwStaticPagesService::seoLayoutParams($page));

            return ControllerTools::ok(HtmlLayout::render($layoutParams));
        }
    }
}

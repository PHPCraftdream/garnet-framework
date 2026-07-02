<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Dashboard\Controllers {
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;

    /**
     * Generic admin dashboard base controller.
     *
     * App-specific dashboard controllers (e.g. MyApp's DashboardController) should
     * extend this class and supply the side / top menus and access checks
     * via the abstract methods below.
     *
     * Framework code intentionally stays free of any business-specific URLs,
     * i18n keys, role flags or menu items.
     */
    abstract class FwDashboardController extends FrameworkController {
        /**
         * Whether the current user is allowed to view moderator-level pages.
         */
        abstract protected static function isModerator(): bool;

        /**
         * Whether the current user is the owner (highest privilege).
         */
        abstract protected static function isOwner(): bool;

        /**
         * Side-menu items for the admin section.
         *
         * @return array<int, array<string, mixed>>
         */
        abstract protected static function getSideMenu(string $url): array;

        /**
         * Top/main-menu items shared with the rest of the app.
         *
         * @return array<int, array<string, mixed>>
         */
        abstract protected static function getMainMenu(string $url): array;
    }
}

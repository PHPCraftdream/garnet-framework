<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Viewer\Controllers {
    use PHPCraftdream\Garnet\Bundle\Modules\Dashboard\Controllers\FwDashboardController;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Admin\Tables\FwAdminActionLog;
    use PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail\Tables\FwMailLog;
    use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;

    /**
     * Unified logs viewer with 4 tabs:
     *  - actions   — admin action log entries
     *  - mails     — outbound mail log entries
     *  - requests  — per-day request log files
     *  - errors    — per-day error log files
     *
     * Initial render builds data only for the active tab (selected via ?tab=…);
     * the other tabs lazy-load via the four POST endpoints below.
     *
     * Apps subclass this and supply:
     *  - the action-log table + grid config
     *  - the mail-log table + grid config + admin gate
     *  - the page URL constant (used to derive POST endpoint URLs)
     *  - menu/role hooks (via the parent FwDashboardController contract)
     */
    abstract class FwDashboardLogsViewerController extends FwDashboardController {
        public const TAB_ACTIONS = 'actions';

        public const TAB_MAILS = 'mails';

        public const TAB_REQUESTS = 'requests';

        public const TAB_ERRORS = 'errors';

        /** @return array<int, string> */
        protected static function allTabs(): array {
            return array_merge(
                [self::TAB_ACTIONS, self::TAB_MAILS, self::TAB_REQUESTS, self::TAB_ERRORS],
                static::extraTabs(),
            );
        }

        /**
         * App-specific extra tab IDs (rendered after the four built-in tabs).
         * Default: none. Apps override to add tabs.
         *
         * @return array<int, string>
         */
        protected static function extraTabs(): array {
            return [];
        }

        /**
         * App-specific extra POST endpoints — keyed by tab ID. Each value is
         * the URL the island will POST to in order to lazy-load that tab's data.
         * Default: empty.
         *
         * @return array<string, string>
         */
        protected static function extraEndpoints(): array {
            return [];
        }

        /**
         * App-specific extra props passed to the AdminLogsViewerIsland.
         * Each entry is keyed by tab ID and contains arbitrary tab-specific data
         * (e.g. initial logs + grid config). Default: empty.
         *
         * @return array<string, mixed>
         */
        protected static function extraInitialData(string $activeTab): array {
            return [];
        }

        // ────────────────── Hooks the app must provide ──────────────────

        /** Concrete admin-action-log table. */
        abstract protected static function actionLogTable(): FwAdminActionLog;

        /**
         * Grid config for the actions tab.
         * @return array<string, mixed>
         */
        abstract protected static function actionsGridConfig(): array;

        /** Concrete mail-log table. */
        abstract protected static function mailLogTable(): FwMailLog;

        /**
         * Grid config for the mails tab.
         * @return array<string, mixed>
         */
        abstract protected static function mailsGridConfig(): array;

        /** Whether the current user has full-admin access (sees mail body_html / meta). */
        abstract protected static function isAdmin(): bool;

        /** Page URL of this controller — used to derive POST endpoint URLs. */
        abstract protected static function pageUrl(): string;

        // ────────────────── URL helpers ──────────────────

        protected static function islandName(): string {
            return 'admin-logs-viewer';
        }

        protected static function endpointUrl(string $method): string {
            return rtrim(static::pageUrl(), '/') . '/~' . $method;
        }

        // ────────────────── Composition: pull data via existing Fw* helpers ──────────────────

        /**
         * @return array<int, array<string, mixed>>
         */
        protected static function fetchActions(int $limit = 100): array {
            return FwLogsActionAdapter::run(static::actionLogTable(), $limit);
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        protected static function fetchMails(int $limit = 200): array {
            return FwLogsMailAdapter::run(static::mailLogTable(), static::isAdmin(), $limit);
        }

        // ────────────────── Routes ──────────────────

        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::redirect('/');
            }

            $url = $globals->getUri();
            $tab = (string)$globals->readGetValue('tab', self::TAB_ACTIONS);

            if (!in_array($tab, static::allTabs(), true)) {
                $tab = self::TAB_ACTIONS;
            }

            $endpoints = array_merge([
                self::TAB_ACTIONS => static::endpointUrl('actionsPage'),
                self::TAB_MAILS => static::endpointUrl('mailsPage'),
                self::TAB_REQUESTS => static::endpointUrl('requestsPage'),
                self::TAB_ERRORS => static::endpointUrl('errorsPage'),
            ], static::extraEndpoints());

            // Always pre-load the date lists so the date pickers render with data
            // (cheap directory scans).
            $requestDates = FwLogsRequestProxy::listRequestDatesPublic();
            $errorDates = FwLogsRequestProxy::listErrorDatesPublic();

            // Initial-tab data: only the active tab. Other tabs render empty
            // until activated; the island POSTs to the matching endpoint.
            $actionsLogs = $tab === self::TAB_ACTIONS ? static::fetchActions() : [];
            $actionsLoaded = $tab === self::TAB_ACTIONS;
            $mailsLogs = $tab === self::TAB_MAILS ? static::fetchMails() : [];
            $mailsLoaded = $tab === self::TAB_MAILS;

            $islandProps = array_merge([
                'initialTab' => $tab,
                'endpoints' => $endpoints,
                'extraTabs' => static::extraTabs(),
                'actions' => [
                    'gridConfig' => static::actionsGridConfig(),
                    'logs' => $actionsLogs,
                    'loaded' => $actionsLoaded,
                ],
                'mails' => [
                    'gridConfig' => static::mailsGridConfig(),
                    'logs' => $mailsLogs,
                    'loaded' => $mailsLoaded,
                ],
                'requests' => [
                    'dates' => $requestDates,
                ],
                'errors' => [
                    'dates' => $errorDates,
                ],
            ], static::extraInitialData($tab));

            $content = RenderIsland::render(static::islandName(), $islandProps);

            return ControllerTools::ok(HtmlLayout::render(
                TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS, [
                    'content' => $content,
                    'top_menu_items' => static::getMainMenu($url),
                    'side_menu_items' => static::getSideMenu($url),
                ])
            ));
        }

        public static function post__actionsPage(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            return ControllerTools::JSON([
                'logs' => static::fetchActions(),
            ]);
        }

        public static function post__mailsPage(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            return ControllerTools::JSON([
                'logs' => static::fetchMails(),
            ]);
        }

        public static function post__requestsPage(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            return FwLogsRequestProxy::requestsPage($globals, $params, static::isModerator());
        }

        public static function post__errorsPage(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            return FwLogsRequestProxy::errorsPage($globals, $params, static::isModerator());
        }
    }
}

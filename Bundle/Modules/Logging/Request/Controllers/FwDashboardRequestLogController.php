<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Request\Controllers {
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Bundle\Modules\Dashboard\Controllers\FwDashboardController;
    use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Utils\PaginationHelper;
    use PHPCraftdream\Garnet\Bundle\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseAppInit;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\DbAccount;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;
    use Throwable;

    /**
     * Generic logs viewer with two tabs:
     *  - "Requests" — per-day route requests log (BaseAppInit::$logRouteDir).
     *  - "Errors"   — per-day error log files (BaseAppInit::$logErrorDir),
     *               each file is one entry written by Logger::write().
     *
     * Apps subclass this to wire side/top menus and the URL constant.
     */
    abstract class FwDashboardRequestLogController extends FwDashboardController {
        protected const ROUTE_LOG_FILE_NAME = 'ROUTE_LOGGER-requests.log';

        protected static function islandName(): string {
            return 'admin-logs-viewer';
        }

        /**
         * Base URL of the Logs page. Used to derive both POST endpoints.
         * Apps return their concrete `self::URL`.
         */
        abstract protected static function pageUrl(): string;

        /**
         * Endpoint URL for the "requests" tab data fetcher (handled by post__page).
         */
        protected static function requestsPageUrl(): string {
            return rtrim(static::pageUrl(), '/') . '/~page';
        }

        /**
         * Endpoint URL for the "errors" tab data fetcher (handled by post__errorsPage).
         */
        protected static function errorsPageUrl(): string {
            return rtrim(static::pageUrl(), '/') . '/~errorsPage';
        }

        /**
         * Resolve the directory containing per-day request log subdirectories.
         */
        protected static function routeLogDir(): string {
            $app = BaseAppInit::getInstance();

            return $app !== null ? $app->logRouteDir : '';
        }

        /**
         * Resolve the directory containing per-day error log subdirectories.
         */
        protected static function errorLogDir(): string {
            $app = BaseAppInit::getInstance();

            return $app !== null ? $app->logErrorDir : '';
        }

        /**
         * Generic listing: subdirectories named YYYY-MM-DD, DESC.
         * @return string[]
         */
        protected static function listDatesIn(string $dir): array {
            if ($dir === '' || !is_dir($dir)) {
                return [];
            }
            $entries = scandir($dir) ?: [];
            $dates = [];

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $full = $dir . $entry;

                if (!is_dir($full)) {
                    continue;
                }

                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry)) {
                    continue;
                }
                $dates[] = $entry;
            }
            rsort($dates);

            return $dates;
        }

        /**
         * @return string[] available request-log dates (YYYY-MM-DD), DESC
         */
        protected static function listRequestDates(): array {
            $dir = static::routeLogDir();
            $dates = static::listDatesIn($dir);

            // require the actual route-log file inside
            return array_values(array_filter(
                $dates,
                static fn (string $d): bool => is_file(
                    $dir . $d . DIRECTORY_SEPARATOR . static::ROUTE_LOG_FILE_NAME
                )
            ));
        }

        /**
         * @return string[] available error-log dates (YYYY-MM-DD), DESC
         */
        protected static function listErrorDates(): array {
            return static::listDatesIn(static::errorLogDir());
        }

        /**
         * Parses entries from a daily request-log file.
         * Format per entry: "Y-m-d H:i:s: \n{json}\n\n"
         * @return array<int, array<string, mixed>>
         */
        protected static function parseLog(string $date): array {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return [];
            }
            $dir = static::routeLogDir();

            if ($dir === '') {
                return [];
            }
            $file = $dir . $date . DIRECTORY_SEPARATOR . static::ROUTE_LOG_FILE_NAME;

            if (!is_file($file)) {
                return [];
            }
            $raw = file_get_contents($file);

            if ($raw === false || $raw === '') {
                return [];
            }
            // entries are separated by blank lines (Logger::append writes "\n\n" after each)
            $blocks = preg_split('/\R\R/u', trim($raw)) ?: [];
            $rows = [];

            foreach ($blocks as $block) {
                $block = trim($block);

                if ($block === '') {
                    continue;
                }
                // First line: "YYYY-MM-DD HH:MM:SS: "
                $nlPos = strpos($block, "\n");

                if ($nlPos === false) {
                    continue;
                }
                $header = trim(substr($block, 0, $nlPos));
                $body = trim(substr($block, $nlPos + 1));
                $logTs = rtrim($header, ': ');
                $row = null;

                try {
                    $decoded = json_decode($body, true, 16, JSON_THROW_ON_ERROR);

                    if (is_array($decoded)) {
                        $row = $decoded;
                    }
                } catch (Throwable) {
                    $row = null;
                }

                if ($row === null) {
                    continue;
                }
                $row['log_ts'] = $logTs;
                $rows[] = $row;
            }
            // Newest first
            $rows = array_reverse($rows);

            return $rows;
        }

        /**
         * Batch-load display labels for the given account IDs.
         *
         * Single SELECT against the accounts table; returns map [id => label],
         * where label is the user's `name` if non-empty, else `login`.
         *
         * @param int[] $ids
         * @return array<int, string>
         */
        protected static function loadAccountsMap(array $ids): array {
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $v): bool => $v > 0)));

            if ($ids === []) {
                return [];
            }
            $map = [];

            try {
                $rows = DbAccount::get()->selectByIds($ids, static function (SelectInterface $q): void {
                    $q->resetCols();
                    $q->cols(['id', 'login', 'name']);
                });

                foreach ($rows as $row) {
                    $id = (int)($row['id'] ?? 0);

                    if ($id <= 0) {
                        continue;
                    }
                    $name = trim((string)($row['name'] ?? ''));
                    $login = trim((string)($row['login'] ?? ''));
                    $map[$id] = $name !== '' ? $name : ($login !== '' ? $login : ('#' . $id));
                }
            } catch (Throwable) {
                // DB issues should not break the log viewer — return whatever we got.
            }

            return $map;
        }

        /**
         * Reads error log files for a given date directory.
         *
         * Each file represents one error entry written by `Logger::write()`:
         *   filename:  "ERROR_LOGGER-{name}-{md5}.log"
         *   contents:  "YYYY-MM-DD HH:MM:SS: \n{message…}\n"
         *
         * @return array<int, array{ts: string, name: string, hash: string, message: string, file: string, mtime: int}>
         */
        protected static function parseErrors(string $date): array {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return [];
            }
            $dir = static::errorLogDir();

            if ($dir === '') {
                return [];
            }
            $dayDir = $dir . $date . DIRECTORY_SEPARATOR;

            if (!is_dir($dayDir)) {
                return [];
            }
            $entries = scandir($dayDir) ?: [];
            $rows = [];

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                if (!str_ends_with($entry, '.log')) {
                    continue;
                }
                $full = $dayDir . $entry;

                if (!is_file($full)) {
                    continue;
                }
                // Parse filename "{prefix}-{name}-{hash}.log"
                $base = substr($entry, 0, -4); // drop ".log"
                $name = '';
                $hash = '';

                if (preg_match('/^(.+?)-(.+)-([0-9a-f]{32})$/', $base, $m) === 1) {
                    // m[1] = prefix (e.g. ERROR_LOGGER), m[2] = name, m[3] = hash
                    $name = $m[2];
                    $hash = $m[3];
                }

                $raw = (string)@file_get_contents($full);
                $ts = '';
                $message = $raw;
                $nlPos = strpos($raw, "\n");

                if ($nlPos !== false) {
                    $header = trim(substr($raw, 0, $nlPos));
                    $message = ltrim(substr($raw, $nlPos + 1), "\n");
                    // strip trailing colon
                    $ts = rtrim($header, ': ');
                }
                $message = rtrim($message, "\r\n");

                $mtime = (int)@filemtime($full);
                $rows[] = [
                    'ts' => $ts !== '' ? $ts : ($mtime > 0 ? date('Y-m-d H:i:s', $mtime) : ''),
                    'name' => $name,
                    'hash' => $hash,
                    'message' => $message,
                    'file' => $entry,
                    'mtime' => $mtime,
                ];
            }
            // Newest first by mtime (fallback to ts string compare)
            usort(
                $rows,
                static function (array $a, array $b): int {
                    $am = (int)$a['mtime'];
                    $bm = (int)$b['mtime'];

                    if ($am !== $bm) {
                        return $bm <=> $am;
                    }

                    return strcmp((string)$b['ts'], (string)$a['ts']);
                }
            );

            return $rows;
        }

        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::redirect('/');
            }

            $url = $globals->getUri();
            $tab = (string)$globals->readGetValue('tab', 'requests');

            if ($tab !== 'requests' && $tab !== 'errors') {
                $tab = 'requests';
            }

            $content = RenderIsland::render(static::islandName(), [
                'initialTab' => $tab,
                'requestsPageUrl' => static::requestsPageUrl(),
                'errorsPageUrl' => static::errorsPageUrl(),
                'requestDates' => static::listRequestDates(),
                'errorDates' => static::listErrorDates(),
            ]);

            return ControllerTools::ok(HtmlLayout::render(
                TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS, [
                    'content' => $content,
                    'top_menu_items' => static::getMainMenu($url),
                    'side_menu_items' => static::getSideMenu($url),
                ])
            ));
        }

        public static function post__page(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            $date = (string)$globals->readPostValue('date', '');
            $page = max(1, (int)$globals->readPostValue('page', 1));
            $perPage = (int)$globals->readPostValue('perPage', PaginationHelper::DEFAULT_PER_PAGE);

            if ($perPage < PaginationHelper::MIN_PER_PAGE) {
                $perPage = PaginationHelper::MIN_PER_PAGE;
            }

            if ($perPage > PaginationHelper::MAX_PER_PAGE_LARGE) {
                $perPage = PaginationHelper::MAX_PER_PAGE_LARGE;
            }

            if ($date === '') {
                $dates = static::listRequestDates();
                $date = $dates[0] ?? '';
            }

            if ($date === '') {
                return ControllerTools::JSON([
                    'date' => '',
                    'rows' => [],
                    'total' => 0,
                    'page' => 1,
                    'perPage' => $perPage,
                    'dates' => [],
                    'accounts' => (object)[],
                ]);
            }

            $statusFilter = trim((string)$globals->readPostValue('status', ''));
            $methodFilter = strtoupper(trim((string)$globals->readPostValue('method', '')));
            $uriFilter = trim((string)$globals->readPostValue('uri', ''));
            $uaFilter = trim((string)$globals->readPostValue('ua', ''));

            $rows = static::parseLog($date);

            if ($statusFilter !== '' || $methodFilter !== '' || $uriFilter !== '' || $uaFilter !== '') {
                $rows = array_values(array_filter($rows, static function (array $r) use ($statusFilter, $methodFilter, $uriFilter, $uaFilter): bool {
                    if ($methodFilter !== '' && strtoupper((string)($r['method'] ?? '')) !== $methodFilter) {
                        return false;
                    }

                    if ($uriFilter !== '' && stripos((string)($r['uri'] ?? ''), $uriFilter) === false) {
                        return false;
                    }

                    if ($uaFilter !== '' && stripos((string)($r['ua'] ?? ''), $uaFilter) === false) {
                        return false;
                    }

                    if ($statusFilter !== '') {
                        $st = (int)($r['status'] ?? 0);

                        // Class filter "2xx" / "3xx" / "4xx" / "5xx", or exact code "404"
                        if (preg_match('/^([2-5])xx$/i', $statusFilter, $m)) {
                            $bucket = (int)$m[1];

                            if ((int)floor($st / 100) !== $bucket) {
                                return false;
                            }
                        } elseif (ctype_digit($statusFilter)) {
                            if ($st !== (int)$statusFilter) {
                                return false;
                            }
                        }
                    }

                    return true;
                }));
            }

            $total = count($rows);
            $offset = ($page - 1) * $perPage;
            $slice = array_slice($rows, $offset, $perPage);

            // Collect distinct non-null account IDs from the visible page,
            // batch-load their display labels in a single SELECT.
            $ids = [];

            foreach ($slice as $r) {
                $aid = $r['account_id'] ?? null;

                if ($aid !== null && (int)$aid > 0) {
                    $ids[] = (int)$aid;
                }
            }
            $accounts = static::loadAccountsMap($ids);

            return ControllerTools::JSON([
                'date' => $date,
                'rows' => $slice,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'dates' => static::listRequestDates(),
                'accounts' => $accounts !== [] ? $accounts : (object)[],
            ]);
        }

        public static function post__errorsPage(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            $date = (string)$globals->readPostValue('date', '');
            $page = max(1, (int)$globals->readPostValue('page', 1));
            $perPage = (int)$globals->readPostValue('perPage', PaginationHelper::DEFAULT_PER_PAGE);

            if ($perPage < PaginationHelper::MIN_PER_PAGE) {
                $perPage = PaginationHelper::MIN_PER_PAGE;
            }

            // Tighter ceiling for the errors tab — each row carries the full error
            // message text, so we cap it below the request-log ceiling.
            if ($perPage > PaginationHelper::MAX_PER_PAGE_MEDIUM) {
                $perPage = PaginationHelper::MAX_PER_PAGE_MEDIUM;
            }
            $search = trim((string)$globals->readPostValue('search', ''));

            $availableDates = static::listErrorDates();

            if ($date === '') {
                $date = $availableDates[0] ?? '';
            }

            if ($date === '') {
                return ControllerTools::JSON([
                    'date' => '',
                    'rows' => [],
                    'total' => 0,
                    'page' => 1,
                    'perPage' => $perPage,
                    'dates' => [],
                    'search' => $search,
                ]);
            }

            $rows = static::parseErrors($date);

            if ($search !== '') {
                $needle = mb_strtolower($search, 'UTF-8');
                $rows = array_values(array_filter(
                    $rows,
                    static function (array $r) use ($needle): bool {
                        $hay = mb_strtolower(
                            (string)$r['name'] . "\n" .
                            (string)$r['message'] . "\n" .
                            (string)$r['file'],
                            'UTF-8'
                        );

                        return str_contains($hay, $needle);
                    }
                ));
            }

            $total = count($rows);
            $offset = ($page - 1) * $perPage;
            $slice = array_slice($rows, $offset, $perPage);

            // Strip mtime from response payload (kept only for sorting).
            foreach ($slice as &$row) {
                unset($row['mtime']);
            }
            unset($row);

            return ControllerTools::JSON([
                'date' => $date,
                'rows' => $slice,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'dates' => $availableDates,
                'search' => $search,
            ]);
        }
    }
}

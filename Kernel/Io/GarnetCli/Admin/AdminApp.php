<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin;

use PHPCraftdream\Garnet\Kernel\Core\Env\Env;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv;

class AdminApp {
    private const ALLOWED_COMMANDS = ['build', 'build:watch', 'prepare', 'migration'];

    /**
     * The console is a dev-only tool (proc_open of the `garnet` CLI on the
     * host it runs on) — it must never be reachable from a production
     * deploy. Gate on two independent signals: (1) Env::isDevDir() — the
     * same dev/prod signal the rest of the framework uses, and (2) the
     * request must originate from loopback, so even a dev box that's
     * temporarily exposed on a LAN/tunnel doesn't hand out CLI exec to
     * anyone but the machine itself.
     *
     * $_ENV['GARNET_ADMIN_FORCE_DEV'] is a test-only escape hatch (mirrors
     * the $_ENV['GARNET_ROOT'] override already used throughout this class)
     * — Env::isDevDir() walks real filesystem markers (.idea/.git/...) that
     * kahlan specs can't fake without touching disk state outside their
     * temp dirs.
     */
    private static function isDevRequestAllowed(): bool {
        $isDev = ($_ENV['GARNET_ADMIN_FORCE_DEV'] ?? '') === '1' || Env::isDevDir();

        return $isDev && self::isLoopbackRequest();
    }

    private static function isLoopbackRequest(): bool {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

        return in_array($remoteAddr, ['127.0.0.1', '::1'], true);
    }

    public static function handle(string $uri): void {
        if (!self::isDevRequestAllowed()) {
            self::sendJson(['error' => 'Not found'], 404);

            return;
        }

        $path = parse_url($uri, PHP_URL_PATH);
        $path = rtrim($path, '/') ?: '/__garnet';

        // Token activation (no auth required)
        if ($path === '/__garnet') {
            $token = $_GET['token'] ?? '';

            if ($token !== '') {
                self::handleTokenActivation($token);

                return;
            }

            // Dashboard or login
            if (self::isAuthenticated()) {
                self::handleDashboard();
            } else {
                self::sendHtml(AdminView::loginPage());
            }

            return;
        }

        // Serve admin assets (no auth required — static JS/CSS bundle for the
        // login/dashboard UI, no secrets embedded; already gated above by
        // isDevRequestAllowed() and path-traversal-safe via the filename
        // whitelist in handleAsset()).
        if (str_starts_with($path, '/__garnet/assets/')) {
            self::handleAsset($path);

            return;
        }

        // All other routes require auth
        if (!self::isAuthenticated()) {
            self::sendJson(['error' => 'Unauthorized'], 401);

            return;
        }

        // Logout (auth required, CSRF-checked like every other mutating
        // admin-console POST — see handleExecTicket() for the same pattern).
        if ($path === '/__garnet/logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handleLogout();

            return;
        }

        match ($path) {
            '/__garnet/api/status' => self::handleStatus(),
            '/__garnet/api/app-use' => self::handleAppUse(),
            '/__garnet/api/exec-ticket' => self::handleExecTicket(),
            '/__garnet/api/exec' => self::handleExec(),
            default => self::sendJson(['error' => 'Not found'], 404),
        };
    }

    private static function handleLogout(): void {
        $body = json_decode(file_get_contents('php://input'), true);
        $csrf = (string)($body['csrf'] ?? '');

        if (!AdminAuth::checkCsrfToken($csrf)) {
            self::sendJson(['error' => 'Bad CSRF token'], 403);

            return;
        }

        AdminAuth::deleteToken();
        setcookie('garnet_admin', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        header('Location: /__garnet/');
    }

    private static function handleTokenActivation(string $token): void {
        if (AdminAuth::activateToken($token)) {
            setcookie('garnet_admin', $token, [
                'expires' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            header('Location: /__garnet/');
        } else {
            self::sendHtml(AdminView::deniedPage(), 403);
        }
    }

    private static function handleDashboard(): void {
        $garnetRoot = $_ENV['GARNET_ROOT'] ?? GARNET_ROOT;
        self::sendHtml(AdminView::dashboardPage(
            GarnetEnv::readAppNameFromRoot($garnetRoot),
            GarnetEnv::listAppsFromRoot($garnetRoot),
            AdminAuth::csrfToken() ?? '',
        ));
    }

    private static function handleStatus(): void {
        $garnetRoot = $_ENV['GARNET_ROOT'] ?? GARNET_ROOT;
        self::sendJson([
            'app' => GarnetEnv::readAppNameFromRoot($garnetRoot),
            'apps' => GarnetEnv::listAppsFromRoot($garnetRoot),
        ]);
    }

    private static function handleAppUse(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::sendJson(['error' => 'Method not allowed'], 405);

            return;
        }

        // text/plain is a CORS "simple request" content type that skips
        // preflight — trusting it here would let a cross-origin page
        // smuggle a JSON body past the CSRF check below (same reasoning as
        // GlobalReqParams::mergeJsonBody()'s Content-Type gate).
        $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');

        if (stripos($contentType, 'application/json') !== 0) {
            self::sendJson(['error' => 'Unsupported content type'], 415);

            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $csrf = (string)($body['csrf'] ?? '');

        if (!AdminAuth::checkCsrfToken($csrf)) {
            self::sendJson(['error' => 'Bad CSRF token'], 403);

            return;
        }

        $app = $body['app'] ?? '';

        if (empty($app) || !preg_match('#^[A-Za-z_][A-Za-z0-9_]+$#', $app)) {
            self::sendJson(['error' => 'Invalid app name'], 400);

            return;
        }

        $garnetRoot = $_ENV['GARNET_ROOT'] ?? GARNET_ROOT;
        $appDir = $garnetRoot . DIRECTORY_SEPARATOR . 'Apps' . DIRECTORY_SEPARATOR . $app;

        if (!is_dir($appDir)) {
            self::sendJson(['error' => 'App not found'], 404);

            return;
        }

        GarnetEnv::writeAppNameFromRoot($garnetRoot, $app);
        self::sendJson(['ok' => true, 'app' => $app]);
    }

    /**
     * exec is fundamentally a GET request (EventSource has no way to send a
     * request body or custom headers, and it's the only browser-native way
     * to stream proc_open output), which means a bare GET can't carry a CSRF
     * token in the body the way the rest of the framework's POST-based CSRF
     * checks do. Split it in two: this endpoint is a real POST, requires the
     * admin-console CSRF token in the body, and — only if that checks out —
     * mints a single-use, short-lived ticket. handleExec() (GET, via
     * EventSource) then redeems the ticket instead of trusting the cookie
     * alone. An attacker driving a same-site GET navigation (the actual
     * SameSite=Lax CSRF vector) can never obtain a ticket because they can't
     * complete this POST cross-site.
     */
    private static function handleExecTicket(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            self::sendJson(['error' => 'Method not allowed'], 405);

            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $csrf = (string)($body['csrf'] ?? '');

        if (!AdminAuth::checkCsrfToken($csrf)) {
            self::sendJson(['error' => 'Bad CSRF token'], 403);

            return;
        }

        $cmd = (string)($body['cmd'] ?? '');

        if (!in_array($cmd, self::ALLOWED_COMMANDS, true)) {
            self::sendJson(['error' => 'Command not allowed'], 400);

            return;
        }

        self::sendJson(['ticket' => AdminAuth::issueExecTicket($cmd)]);
    }

    // Defense-in-depth cap on the exec SSE loop: no whitelisted command
    // should legitimately run this long, so if one hangs (or a future
    // ALLOWED_COMMANDS entry turns out to be long-running like build:watch
    // was), the worker gets freed instead of pinned forever.
    private const EXEC_STREAM_TIMEOUT_SECONDS = 300;

    private static function handleExec(): void {
        $ticket = (string)($_GET['ticket'] ?? '');
        $cmd = AdminAuth::redeemExecTicket($ticket);

        if ($cmd === null || !in_array($cmd, self::ALLOWED_COMMANDS, true)) {
            self::sendJson(['error' => 'Command not allowed'], 400);

            return;
        }

        $garnetRoot = $_ENV['GARNET_ROOT'] ?? GARNET_ROOT;

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        if (ob_get_level()) {
            ob_end_flush();
        }

        // build:watch is a file watcher by definition — it never exits, so
        // it cannot be streamed to completion over a single SSE response
        // like the other (run-to-completion) commands below. Start it
        // detached with its own lifecycle and immediately report "started";
        // the operator watches its effect (rebuilt assets) rather than a
        // live log here. This mirrors GarnetServeWatchCommand's own
        // detached-start pattern for the same rspack watcher.
        if ($cmd === 'build:watch') {
            self::execBuildWatchDetached($garnetRoot);

            return;
        }

        self::execStreamed($cmd, $garnetRoot);
    }

    private static function execBuildWatchDetached(string $garnetRoot): void {
        $garnetBin = $garnetRoot . DIRECTORY_SEPARATOR . 'garnet';
        $isWindows = DIRECTORY_SEPARATOR === '\\';

        if ($isWindows) {
            pclose(popen(
                'cd /d ' . escapeshellarg($garnetRoot) . ' && start /B php '
                    . escapeshellarg($garnetBin) . ' build:watch',
                'r'
            ));
        } else {
            exec(
                'cd ' . escapeshellarg($garnetRoot) . ' && php '
                    . escapeshellarg($garnetBin) . ' build:watch > /dev/null 2>&1 &'
            );
        }

        echo 'data: ' . json_encode('build:watch started in the background (detached) — it will keep running independently of this request.') . "\n\n";
        echo "event: done\ndata: 0\n\n";
        flush();
    }

    private static function execStreamed(string $cmd, string $garnetRoot): void {
        $garnetBin = $garnetRoot . DIRECTORY_SEPARATOR . 'garnet';

        $descriptors = [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $env = [
            'GARNET_ROOT' => $garnetRoot,
            'COMMON_GARNET_WEB_DIR' => $garnetRoot . DIRECTORY_SEPARATOR,
            'PATH' => getenv('PATH'),
            'SYSTEMROOT' => getenv('SYSTEMROOT') ?: '',
            'TEMP' => getenv('TEMP') ?: '',
            'TMP' => getenv('TMP') ?: '',
        ];

        // Add HOME/USERPROFILE for tools that need it
        if (getenv('HOME')) {
            $env['HOME'] = getenv('HOME');
        }

        if (getenv('USERPROFILE')) {
            $env['USERPROFILE'] = getenv('USERPROFILE');
        }

        if (getenv('APPDATA')) {
            $env['APPDATA'] = getenv('APPDATA');
        }

        if (getenv('LOCALAPPDATA')) {
            $env['LOCALAPPDATA'] = getenv('LOCALAPPDATA');
        }

        $process = proc_open(
            'php ' . escapeshellarg($garnetBin) . ' ' . $cmd,
            $descriptors,
            $pipes,
            $garnetRoot,
            $env
        );

        if (!is_resource($process)) {
            echo 'data: ' . json_encode('Failed to start process') . "\n\n";
            echo "event: done\ndata: 1\n\n";
            flush();

            return;
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $deadline = time() + self::EXEC_STREAM_TIMEOUT_SECONDS;
        $timedOut = false;

        while (true) {
            $stdout = fgets($pipes[1]);
            $stderr = fgets($pipes[2]);

            if ($stdout !== false && $stdout !== '') {
                echo 'data: ' . json_encode(rtrim($stdout, "\r\n")) . "\n\n";
                flush();
            }

            if ($stderr !== false && $stderr !== '') {
                echo 'data: ' . json_encode(rtrim($stderr, "\r\n")) . "\n\n";
                flush();
            }

            if (feof($pipes[1]) && feof($pipes[2])) {
                break;
            }

            if (time() >= $deadline) {
                $timedOut = true;
                proc_terminate($process);

                break;
            }

            if ($stdout === false && $stderr === false) {
                usleep(50000); // 50ms
            }

            if (connection_aborted()) {
                proc_terminate($process);

                break;
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($timedOut) {
            echo 'data: ' . json_encode('Timed out after ' . self::EXEC_STREAM_TIMEOUT_SECONDS . 's — process terminated') . "\n\n";
        }

        echo "event: done\ndata: {$exitCode}\n\n";
        flush();
    }

    private static function handleAsset(string $path): void {
        $filename = basename($path);

        // Only allow known safe filenames (no path traversal)
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            http_response_code(400);

            return;
        }

        $distDir = __DIR__ . DIRECTORY_SEPARATOR . 'dist';
        $file = $distDir . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($file)) {
            http_response_code(404);
            echo 'Admin assets not built. Run: cd FrontBuilder && npx rspack build --config rspack.admin.config.ts';

            return;
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $mimeTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'map' => 'application/json',
        ];

        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        header('Cache-Control: no-cache');
        readfile($file);
    }

    private static function isAuthenticated(): bool {
        $cookie = $_COOKIE['garnet_admin'] ?? '';

        return $cookie !== '' && AdminAuth::validateCookie($cookie);
    }

    private static function sendHtml(string $html, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
    }

    private static function sendJson(array $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}

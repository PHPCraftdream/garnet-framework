<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin;

use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetEnv;

class AdminApp {
    private const ALLOWED_COMMANDS = ['build', 'build:watch', 'prepare', 'migration'];

    public static function handle(string $uri): void {
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

        // Serve admin assets (no auth required — JS/CSS bundles)
        if (str_starts_with($path, '/__garnet/assets/')) {
            self::handleAsset($path);

            return;
        }

        // Logout (auth required)
        if ($path === '/__garnet/logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            AdminAuth::deleteToken();
            setcookie('garnet_admin', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            header('Location: /__garnet/');

            return;
        }

        // All other routes require auth
        if (!self::isAuthenticated()) {
            self::sendJson(['error' => 'Unauthorized'], 401);

            return;
        }

        match ($path) {
            '/__garnet/api/status' => self::handleStatus(),
            '/__garnet/api/app-use' => self::handleAppUse(),
            '/__garnet/api/exec' => self::handleExec(),
            default => self::sendJson(['error' => 'Not found'], 404),
        };
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
        $garnetRoot = $_ENV['GARNET_ROOT'];
        self::sendHtml(AdminView::dashboardPage(
            GarnetEnv::readAppNameFromRoot($garnetRoot),
            GarnetEnv::listAppsFromRoot($garnetRoot),
        ));
    }

    private static function handleStatus(): void {
        $garnetRoot = $_ENV['GARNET_ROOT'];
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

        $body = json_decode(file_get_contents('php://input'), true);
        $app = $body['app'] ?? '';

        if (empty($app) || !preg_match('#^[A-Za-z_][A-Za-z0-9_]+$#', $app)) {
            self::sendJson(['error' => 'Invalid app name'], 400);

            return;
        }

        $garnetRoot = $_ENV['GARNET_ROOT'];
        $appDir = $garnetRoot . DIRECTORY_SEPARATOR . 'Apps' . DIRECTORY_SEPARATOR . $app;

        if (!is_dir($appDir)) {
            self::sendJson(['error' => 'App not found'], 404);

            return;
        }

        GarnetEnv::writeAppNameFromRoot($garnetRoot, $app);
        self::sendJson(['ok' => true, 'app' => $app]);
    }

    private static function handleExec(): void {
        $cmd = $_GET['cmd'] ?? '';

        if (!in_array($cmd, self::ALLOWED_COMMANDS, true)) {
            self::sendJson(['error' => 'Command not allowed'], 400);

            return;
        }

        $garnetRoot = $_ENV['GARNET_ROOT'];
        $garnetBin = $garnetRoot . DIRECTORY_SEPARATOR . 'garnet';

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        if (ob_get_level()) {
            ob_end_flush();
        }

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

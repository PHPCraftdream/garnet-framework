<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Middlewares {
    use GuzzleHttp\Psr7\Response;
    use PHPCraftdream\Garnet\Kernel\Core\AppInit\BaseAppInit;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;
    use Psr\Http\Message\ResponseInterface;

    class MaintenanceMiddleware {
        private const FLAG_FILE = 'maintenance.flag';

        /**
         * Returns the path to the maintenance flag file for the current app.
         */
        public static function getFlagPath(): string {
            $app = BaseAppInit::getInstance();

            if ($app === null) {
                return '';
            }

            return $app->workDir . self::FLAG_FILE;
        }

        /**
         * Middleware entry point.
         * If maintenance.flag exists and the client IP is NOT in the allow-list,
         * returns a 503 response. Otherwise returns null (request continues).
         *
         * Supports two flag file formats:
         *   - JSON: {"enabled_at":"...","admin_ip":"...","allowed_ips":["..."]}
         *   - Legacy: one IP per line
         */
        public static function process(IGlobalReqParams $globals, IRouterUriParams $uriParams): ?ResponseInterface {
            $flagPath = self::getFlagPath();

            if ($flagPath === '' || !is_file($flagPath)) {
                return null;
            }

            $content = file_get_contents($flagPath);
            $allowedIps = self::parseAllowedIps($content ?: '');

            // Check client IP
            $clientIp = self::getClientIp($globals);

            if ($clientIp !== '' && in_array($clientIp, $allowedIps, true)) {
                return null; // Allowed through
            }

            return self::maintenanceResponse();
        }

        /**
         * Parse allowed IPs from flag file content.
         * Supports JSON format (from cli.php) and legacy one-IP-per-line format.
         *
         * @return string[]
         */
        private static function parseAllowedIps(string $content): array {
            $content = trim($content);

            if ($content === '') {
                return [];
            }

            // Try JSON first
            if ($content[0] === '{') {
                $data = json_decode($content, true);

                if (is_array($data) && isset($data['allowed_ips']) && is_array($data['allowed_ips'])) {
                    return $data['allowed_ips'];
                }

                // JSON with only admin_ip
                if (is_array($data) && isset($data['admin_ip'])) {
                    return [(string)$data['admin_ip']];
                }
            }

            // Legacy format: one IP per line
            $ips = [];

            foreach (explode("\n", $content) as $line) {
                $ip = trim($line);

                if ($ip !== '') {
                    $ips[] = $ip;
                }
            }

            return $ips;
        }

        /**
         * Extracts the client IP from the request, respecting X-Forwarded-For.
         */
        private static function getClientIp(IGlobalReqParams $globals): string {
            $server = $globals->readServerAll();

            // Check X-Forwarded-For first (behind reverse proxy)
            $xff = $server['HTTP_X_FORWARDED_FOR'] ?? '';

            if ($xff !== '') {
                $parts = explode(',', $xff);

                return trim($parts[0]);
            }

            return $server['REMOTE_ADDR'] ?? '';
        }

        /**
         * Builds a static 503 HTML response with Retry-After header.
         * Renders Layout/Maintenance.twig — self-contained, no asset
         * pipeline, no shared layout, so it keeps working when the rest of
         * the app is broken or mid-migration.
         */
        private static function maintenanceResponse(): ResponseInterface {
            $html = Twig::get()->render('Layout/Maintenance.twig');

            $response = new Response();
            $response = $response
                ->withStatus(503, 'Service Unavailable')
                ->withHeader('Content-Type', 'text/html; charset=UTF-8')
                ->withHeader('Retry-After', '300')
                ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->getBody()->write($html);

            return $response;
        }
    }
}

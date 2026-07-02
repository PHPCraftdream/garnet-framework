<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Auth\AuthStrategy;

use Exception;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;

class AuthConfig {
    protected static ?AuthConfig $instance = null;

    protected array $config = [];

    protected function __construct() {
        $this->loadConfig();
    }

    public static function get(): AuthConfig {
        if (empty(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    protected function loadConfig(): void {
        try {
            $iniConfig = IniConfig::app();
            $filePath = $iniConfig->getFilePath();

            if (!file_exists($filePath)) {
                $this->config = [];

                return;
            }

            $allConfig = parse_ini_file($filePath, true);

            // Support both flat format and [auth] section
            $this->config = $allConfig['auth'] ?? $allConfig;
        } catch (Exception $e) {
            $this->config = [];
        }
    }

    /**
     * Get allowed origins array for CORS/origin check
     * @return string[]
     */
    public function allowedOrigins(): array {
        $origins = $this->config['allowed_origins'] ?? [];

        if (!is_array($origins)) {
            $origins = [$origins];
        }

        return $origins;
    }

    /**
     * Check if origin is allowed
     */
    public function isOriginAllowed(string $origin): bool {
        $allowed = $this->allowedOrigins();

        if (empty($allowed)) {
            // Fallback: derive from base_url if configured
            $baseUrl = $this->config['base_url'] ?? '';

            if (!empty($baseUrl)) {
                $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
                $host = parse_url($baseUrl, PHP_URL_HOST);
                $port = parse_url($baseUrl, PHP_URL_PORT);
                $baseOrigin = $scheme . '://' . $host . ($port ? ':' . $port : '');

                return $origin === $baseOrigin;
            }

            return false;
        }

        return in_array($origin, $allowed, true);
    }
}

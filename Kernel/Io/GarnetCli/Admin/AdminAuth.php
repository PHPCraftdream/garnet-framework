<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin;

class AdminAuth {
    private static function tokenFile(): string {
        return ($_ENV['GARNET_ROOT'] ?? GARNET_ROOT) . DIRECTORY_SEPARATOR . '.garnet_admin';
    }

    public static function generateToken(): string {
        return bin2hex(random_bytes(16));
    }

    public static function saveToken(string $token): void {
        $data = [
            'token' => $token,
            'status' => 'pending',
            'created' => time(),
        ];
        file_put_contents(self::tokenFile(), json_encode($data, JSON_PRETTY_PRINT));
    }

    public static function readToken(): ?array {
        $file = self::tokenFile();

        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);

        if (!is_array($data) || empty($data['token'])) {
            return null;
        }

        return $data;
    }

    public static function activateToken(string $token): bool {
        $data = self::readToken();

        if ($data === null) {
            return false;
        }

        if ($data['token'] !== $token || $data['status'] !== 'pending') {
            return false;
        }

        $data['status'] = 'active';
        file_put_contents(self::tokenFile(), json_encode($data, JSON_PRETTY_PRINT));

        return true;
    }

    public static function validateCookie(string $cookieValue): bool {
        $data = self::readToken();

        if ($data === null) {
            return false;
        }

        return $data['token'] === $cookieValue && $data['status'] === 'active';
    }

    public static function deleteToken(): void {
        $file = self::tokenFile();

        if (file_exists($file)) {
            unlink($file);
        }
    }
}

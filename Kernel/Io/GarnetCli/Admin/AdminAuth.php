<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Admin;

class AdminAuth {
    // Active tokens (post-activation) expire after this many seconds —
    // a stolen/leaked cookie or token file entry stops working eventually
    // instead of granting permanent admin access.
    private const TOKEN_TTL_SECONDS = 86400;

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

        if (!hash_equals($data['token'], $token) || $data['status'] !== 'pending') {
            return false;
        }

        $data['status'] = 'active';
        // Restart the TTL clock from the moment of activation, not from
        // generateToken time — a token can sit unactivated for a while
        // (the operator copy/pastes the URL) without burning its session life.
        $data['created'] = time();
        file_put_contents(self::tokenFile(), json_encode($data, JSON_PRETTY_PRINT));

        return true;
    }

    public static function validateCookie(string $cookieValue): bool {
        $data = self::readToken();

        if ($data === null) {
            return false;
        }

        if ($data['status'] !== 'active') {
            return false;
        }

        $created = (int)($data['created'] ?? 0);

        if ($created <= 0 || (time() - $created) > self::TOKEN_TTL_SECONDS) {
            return false;
        }

        return hash_equals($data['token'], $cookieValue);
    }

    public static function deleteToken(): void {
        $file = self::tokenFile();

        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * CSRF token scoped to the admin console session — distinct from the
     * app's regular user-session CSRF (Session::CSRF_TOKEN). Derived
     * deterministically from the active admin token so it doesn't need
     * its own storage, but is not equal to the admin token itself (so it
     * can't be replayed as the auth cookie, and vice versa).
     */
    public static function csrfToken(): ?string {
        $data = self::readToken();

        if ($data === null || $data['status'] !== 'active') {
            return null;
        }

        return hash_hmac('sha256', 'garnet_admin_csrf', $data['token']);
    }

    public static function checkCsrfToken(string $submitted): bool {
        $expected = self::csrfToken();

        if ($expected === null || $submitted === '') {
            return false;
        }

        return hash_equals($expected, $submitted);
    }

    // Exec tickets: single-use, short-lived proof that a real CSRF-checked
    // POST authorized this exact command, so the follow-up GET (EventSource,
    // which can't carry a CSRF token itself) has something unforgeable to
    // present. See AdminApp::handleExecTicket()/handleExec().
    private const EXEC_TICKET_TTL_SECONDS = 30;

    private static function execTicketFile(string $ticket): string {
        // $ticket is our own hex output (see issueExecTicket) — never
        // attacker-supplied path components reach the filesystem here.
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_admin_ticket_' . $ticket;
    }

    public static function issueExecTicket(string $cmd): string {
        $ticket = bin2hex(random_bytes(16));
        $data = ['cmd' => $cmd, 'created' => time()];
        file_put_contents(self::execTicketFile($ticket), json_encode($data));

        return $ticket;
    }

    public static function redeemExecTicket(string $ticket): ?string {
        if ($ticket === '' || !preg_match('/^[0-9a-f]{32}$/', $ticket)) {
            return null;
        }

        $file = self::execTicketFile($ticket);

        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);
        // Single-use: delete immediately on redemption attempt, valid or not.
        unlink($file);

        if (!is_array($data) || empty($data['cmd'])) {
            return null;
        }

        $created = (int)($data['created'] ?? 0);

        if ($created <= 0 || (time() - $created) > self::EXEC_TICKET_TTL_SECONDS) {
            return null;
        }

        return $data['cmd'];
    }
}

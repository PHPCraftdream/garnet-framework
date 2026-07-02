<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Logging\Mail {
    use PHPCraftdream\Garnet\Kernel\Core\Env\TestScope;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\DbAccount;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IMailer;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use Throwable;

    abstract class FwAppMailer implements IMailer {
        private IMailer $inner;

        /** Structured metadata to attach to the next log entry (consumed once). */
        private static array $nextMeta = [];

        public static function setNextMeta(array $meta): void {
            self::$nextMeta = $meta;
        }

        public function __construct(IMailer $inner) {
            $this->inner = $inner;
        }

        abstract protected function mailLogTable(): DbTable;

        public function sendHtmlMail(string $to, string $subject, string $htmlMessage): void {
            $isDev = IniConfig::app()->paramString('env', 'prod') === 'dev';
            $isTestEmail = str_ends_with(strtolower($to), '.test');
            $mailType = $this->detectMailType($subject);

            // Resolve account_id by email
            $accountId = $this->resolveAccountId($to);

            $meta = self::$nextMeta;
            self::$nextMeta = [];

            // Never put a real message on the wire for a `*.test` mailbox:
            //   - local dev ($isDev), as before; or
            //   - an authorized prod TestScope run (token file + matching
            //     header / GARNET_TEST_TOKEN). The UI-test pipeline registers
            //     and logs into `*@*.test` accounts; the auth code still lands
            //     in the mail_log row's `meta` (auth_code) so the harness can
            //     read it back over the SQL bridge — it just isn't emailed.
            if (($isDev || TestScope::isActive()) && $isTestEmail) {
                // Log but don't send
                $this->log($accountId, $to, $mailType, $subject, $htmlMessage, 'skipped_dev', null, $meta);

                return;
            }

            try {
                $this->inner->sendHtmlMail($to, $subject, $htmlMessage);
                $this->log($accountId, $to, $mailType, $subject, $htmlMessage, 'sent', null, $meta);
            } catch (Throwable $e) {
                $this->log($accountId, $to, $mailType, $subject, $htmlMessage, 'failed', $e->getMessage(), $meta);

                throw $e;
            }
        }

        private function log(?int $accountId, string $to, string $mailType, string $subject, string $body, string $status, ?string $error, array $meta = []): void {
            try {
                $this->mailLogTable()->insert([
                    'account_id' => $accountId,
                    'recipient_email' => $to,
                    'mail_type' => $mailType,
                    'subject' => $subject,
                    'body_html' => $body,
                    'status' => $status,
                    'error_log' => $error,
                    'meta' => !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                    'created_at' => time(),
                ]);
            } catch (Throwable) {
                // Don't let logging failures break email sending
            }
        }

        protected function detectMailType(string $subject): string {
            // Simple detection based on subject -- can be extended
            $lower = mb_strtolower($subject);

            if (str_contains($lower, 'авториз') || str_contains($lower, 'auth')) {
                return 'auth_code';
            }

            return 'general';
        }

        private function resolveAccountId(string $email): ?int {
            try {
                $row = DbAccount::get()->selectOneByField('login', strtolower($email));

                return $row ? (int)$row['id'] : null;
            } catch (Throwable) {
                return null;
            }
        }
    }
}

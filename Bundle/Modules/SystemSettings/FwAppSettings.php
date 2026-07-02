<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\SystemSettings {
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use Throwable;

    class FwAppSettings {
        public const REGISTRATIONS_ENABLED = 'registrations_enabled';

        public const CANCELLATION_PENALTY_PERCENT = 'cancellation_penalty_percent';

        public const SUPPORT_CONTACT_EMAIL = 'support_contact_email';

        public const SUPPORT_CONTACT_PHONE = 'support_contact_phone';

        public const SUPPORT_CONTACT_TELEGRAM = 'support_contact_telegram';

        public const SEO_DESCRIPTION = 'seo_description';

        public const SEO_OG_IMAGE = 'seo_og_image';

        public const SEO_TWITTER_SITE = 'seo_twitter_site';

        /**
         * @return array{
         *     registrationsEnabled: bool,
         *     cancellationPenaltyPercent: int,
         *     smtp: array{
         *         enabled: bool,
         *         scheme: string,
         *         host: string,
         *         port: string,
         *         user: string,
         *         password: string,
         *         from: string,
         *         verify_peer: bool
         *     }
         * }
         */
        public static function read(): array {
            return [
                'registrationsEnabled' => static::registrationsEnabled(),
                'cancellationPenaltyPercent' => static::cancellationPenaltyPercent(),
                'supportContacts' => static::supportContacts(),
                'smtp' => static::smtpSettings(),
                'seo' => static::seoDefaults(),
            ];
        }

        /**
         * Site-wide SEO / social-sharing defaults. Per-page values (on static
         * pages) override these; these in turn back-fill the meta/OG/Twitter
         * tags on every page so links always unfurl with a title + description.
         *
         * @return array{description: string, ogImage: string, twitterSite: string}
         */
        public static function seoDefaults(): array {
            $appConfig = static::readIniFile(static::appIniPath());

            return [
                'description' => trim((string)($appConfig[self::SEO_DESCRIPTION] ?? '')),
                'ogImage' => trim((string)($appConfig[self::SEO_OG_IMAGE] ?? '')),
                'twitterSite' => trim((string)($appConfig[self::SEO_TWITTER_SITE] ?? '')),
            ];
        }

        public static function registrationsEnabled(): bool {
            $appConfig = static::readIniFile(static::appIniPath());

            return static::boolValue($appConfig[self::REGISTRATIONS_ENABLED] ?? 1, true);
        }

        /**
         * Brand name used in user-facing strings (email subjects, external
         * gate, page chrome, etc.). Single source of truth — driven by the
         * `title` key in app.ini. Falls back to a generic value so a missing
         * or unparseable config never leaks an empty subject line.
         */
        public static function brandName(): string {
            try {
                $appConfig = static::readIniFile(static::appIniPath());
                $raw = trim((string)($appConfig['title'] ?? ''));

                return $raw !== '' ? $raw : 'Garnet';
            } catch (Throwable) {
                return 'Garnet';
            }
        }

        public static function cancellationPenaltyPercent(): int {
            $appConfig = static::readIniFile(static::appIniPath());
            $raw = $appConfig[self::CANCELLATION_PENALTY_PERCENT] ?? 0;
            $value = (int)$raw;

            return max(0, min(100, $value));
        }

        /**
         * @return array{email: string, phone: string, telegram: string}
         */
        public static function supportContacts(): array {
            $appConfig = static::readIniFile(static::appIniPath());

            return [
                'email' => trim((string)($appConfig[self::SUPPORT_CONTACT_EMAIL] ?? '')),
                'phone' => trim((string)($appConfig[self::SUPPORT_CONTACT_PHONE] ?? '')),
                'telegram' => trim((string)($appConfig[self::SUPPORT_CONTACT_TELEGRAM] ?? '')),
            ];
        }

        /**
         * @return array{
         *     enabled: bool,
         *     scheme: string,
         *     host: string,
         *     port: string,
         *     user: string,
         *     password: string,
         *     from: string,
         *     verify_peer: bool
         * }
         */
        public static function smtpSettings(): array {
            $emailConfig = static::readIniFile(static::emailIniPath());

            return [
                'enabled' => static::boolValue($emailConfig['enabled'] ?? 0, false),
                'scheme' => trim((string)($emailConfig['scheme'] ?? 'smtp')),
                'host' => trim((string)($emailConfig['host'] ?? '')),
                'port' => trim((string)($emailConfig['port'] ?? '465')),
                'user' => trim((string)($emailConfig['user'] ?? '')),
                'password' => (string)($emailConfig['password'] ?? ''),
                'from' => trim((string)($emailConfig['from'] ?? '')),
                'verify_peer' => static::boolValue($emailConfig['verify_peer'] ?? 0, false),
            ];
        }

        /**
         * @param bool $registrationsEnabled
         * @param array{
         *     enabled?: bool,
         *     scheme?: string,
         *     host?: string,
         *     port?: string,
         *     user?: string,
         *     password?: string,
         *     from?: string,
         *     verify_peer?: bool
         * } $smtp Caller-supplied (form) input — keys may be absent.
         * @param int|null $cancellationPenaltyPercent Null preserves the existing value.
         * @return array{settings?: array, error?: string}
         */
        public static function save(bool $registrationsEnabled, array $smtp, ?int $cancellationPenaltyPercent = null, ?array $supportContacts = null, ?array $seo = null): array {
            $smtpScheme = trim((string)($smtp['scheme'] ?? ''));

            if ($smtpScheme === '') {
                $smtpScheme = 'smtp';
            }

            if (!in_array($smtpScheme, ['smtp', 'smtps'], true)) {
                return ['error' => 'invalid_scheme'];
            }

            $smtpPort = trim((string)($smtp['port'] ?? ''));

            if ($smtpPort === '' || !ctype_digit($smtpPort)) {
                return ['error' => 'invalid_port'];
            }

            $portInt = (int)$smtpPort;

            if ($portInt < 1 || $portInt > 65535) {
                return ['error' => 'invalid_port'];
            }

            $smtpHost = trim((string)($smtp['host'] ?? ''));
            $smtpFrom = trim((string)($smtp['from'] ?? ''));
            $smtpEnabled = !empty($smtp['enabled']);

            if ($smtpEnabled && $smtpHost === '') {
                return ['error' => 'required_host'];
            }

            if ($smtpEnabled && $smtpFrom === '') {
                return ['error' => 'required_from'];
            }

            if ($cancellationPenaltyPercent !== null && ($cancellationPenaltyPercent < 0 || $cancellationPenaltyPercent > 100)) {
                return ['error' => 'invalid_penalty_percent'];
            }

            $appConfig = static::readIniFile(static::appIniPath());
            $appConfig[self::REGISTRATIONS_ENABLED] = $registrationsEnabled ? 1 : 0;

            if ($cancellationPenaltyPercent !== null) {
                $appConfig[self::CANCELLATION_PENALTY_PERCENT] = $cancellationPenaltyPercent;
            }

            if ($supportContacts !== null) {
                $appConfig[self::SUPPORT_CONTACT_EMAIL] = trim((string)($supportContacts['email'] ?? ''));
                $appConfig[self::SUPPORT_CONTACT_PHONE] = trim((string)($supportContacts['phone'] ?? ''));
                $appConfig[self::SUPPORT_CONTACT_TELEGRAM] = trim((string)($supportContacts['telegram'] ?? ''));
            }

            if ($seo !== null) {
                $appConfig[self::SEO_DESCRIPTION] = trim((string)($seo['description'] ?? ''));
                $appConfig[self::SEO_OG_IMAGE] = trim((string)($seo['ogImage'] ?? ''));
                $appConfig[self::SEO_TWITTER_SITE] = trim((string)($seo['twitterSite'] ?? ''));
            }
            static::writeIniFile(static::appIniPath(), $appConfig);

            $emailConfig = static::readIniFile(static::emailIniPath());
            $emailConfig['enabled'] = $smtpEnabled ? 1 : 0;
            $emailConfig['scheme'] = $smtpScheme;
            $emailConfig['host'] = $smtpHost;
            $emailConfig['port'] = $portInt;
            $emailConfig['user'] = trim((string)($smtp['user'] ?? ''));
            $emailConfig['password'] = (string)($smtp['password'] ?? '');
            $emailConfig['from'] = $smtpFrom;
            $emailConfig['verify_peer'] = !empty($smtp['verify_peer']) ? 1 : 0;
            static::writeIniFile(static::emailIniPath(), $emailConfig);

            return ['settings' => static::read()];
        }

        protected static function appIniPath(): string {
            return IniConfig::get(IniConfig::ENV_APP)->getFilePath();
        }

        protected static function emailIniPath(): string {
            return IniConfig::get(IniConfig::ENV_EMAIL)->getFilePath();
        }

        /**
         * @return array<string, mixed>
         */
        protected static function readIniFile(string $filePath): array {
            if (!is_file($filePath)) {
                return [];
            }

            $ini = parse_ini_file($filePath);

            return is_array($ini) ? $ini : [];
        }

        /**
         * @param array<string, mixed> $data
         */
        protected static function writeIniFile(string $filePath, array $data): void {
            $lines = [];

            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $item) {
                        $lines[] = $key . '[] = ' . static::encodeIniValue($item);
                    }

                    continue;
                }

                $lines[] = $key . ' = ' . static::encodeIniValue($value);
            }

            file_put_contents($filePath, implode(PHP_EOL, $lines) . PHP_EOL);
        }

        protected static function encodeIniValue(mixed $value): string {
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            if (is_int($value) || is_float($value)) {
                return (string)$value;
            }

            $escaped = str_replace(
                ['\\', '"'],
                ['\\\\', '\\"'],
                (string)$value
            );

            return '"' . $escaped . '"';
        }

        protected static function boolValue(mixed $value, bool $default): bool {
            if ($value === null || $value === '') {
                return $default;
            }

            return in_array(mb_strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
        }
    }
}

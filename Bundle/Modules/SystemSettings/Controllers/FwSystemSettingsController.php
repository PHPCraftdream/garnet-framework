<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\SystemSettings\Controllers {
    use PHPCraftdream\Garnet\Bundle\Modules\SystemSettings\FwAppSettings;
    use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Mailer\Mailer;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;
    use Throwable;

    abstract class FwSystemSettingsController extends FrameworkController {
        /**
         * Check whether current user is allowed to access system settings.
         */
        abstract protected static function isAllowed(): bool;

        /**
         * Return the concrete FwAppSettings subclass (or FwAppSettings itself).
         */
        abstract protected static function settingsManager(): FwAppSettings;

        /**
         * Return i18n labels array for the controller/island.
         * Must include all keys used in labels() of the original controller.
         *
         * @return array<string, string>
         */
        abstract protected static function getLabels(): array;

        /**
         * Return side-menu items array for the current URL.
         */
        abstract protected static function getSideMenu(string $url): array;

        /**
         * Return top/main-menu items array for the current URL.
         */
        abstract protected static function getMainMenu(string $url): array;

        /**
         * Return the test email subject line.
         */
        abstract protected static function testEmailSubject(): string;

        /**
         * Return the test email body HTML.
         */
        abstract protected static function testEmailBody(): string;

        /**
         * Island name for the system settings page.
         */
        protected static function islandName(): string {
            return 'admin-system-settings';
        }

        /**
         * Base URL for the system settings page. Override in subclass.
         */
        protected static function baseUrl(): string {
            return '/admin/system/';
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
        protected static function smtpFromRequest(IGlobalReqParams $globals): array {
            return [
                'enabled' => (int)$globals->readPostValue('smtp_enabled', '0') > 0,
                'scheme' => trim((string)$globals->readPostValue('smtp_scheme', '')),
                'host' => trim((string)$globals->readPostValue('smtp_host', '')),
                'port' => trim((string)$globals->readPostValue('smtp_port', '')),
                'user' => trim((string)$globals->readPostValue('smtp_user', '')),
                'password' => (string)$globals->readPostValue('smtp_password', ''),
                'from' => trim((string)$globals->readPostValue('smtp_from', '')),
                'verify_peer' => (int)$globals->readPostValue('smtp_verify_peer', '0') > 0,
            ];
        }

        protected static function saveErrorMessage(string $errorCode): string {
            return match ($errorCode) {
                'invalid_scheme' => 'SMTP scheme must be "smtp" or "smtps"',
                'invalid_port' => 'SMTP port must be a number between 1 and 65535',
                'required_host' => 'SMTP host is required when SMTP is enabled',
                'required_from' => 'Sender email (From) is required when SMTP is enabled',
                default => 'Settings error: ' . $errorCode,
            };
        }

        protected static function currentSettings(): array {
            return static::settingsManager()::read();
        }

        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::redirect('/');
            }

            $url = $globals->getUri();
            $baseUrl = static::baseUrl();
            $content = RenderIsland::render(static::islandName(), [
                'settings' => static::currentSettings(),
                'saveUrl' => $baseUrl . '~save',
                'testEmailUrl' => $baseUrl . '~sendTestEmail',
                'labels' => static::getLabels(),
            ]);

            return ControllerTools::ok(HtmlLayout::render(
                TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS, [
                    'content' => $content,
                    'top_menu_items' => static::getMainMenu($url),
                    'side_menu_items' => static::getSideMenu($url),
                ])
            ));
        }

        public static function post__save(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => static::getLabels()['accessDenied']], status: 403);
            }

            $manager = static::settingsManager();
            $smtp = static::smtpFromRequest($globals);
            $saveResult = $manager::save(
                (int)$globals->readPostValue('registrations_enabled', '0') > 0,
                $smtp
            );

            if (!empty($saveResult['error'])) {
                return ControllerTools::JSON([
                    'error' => static::saveErrorMessage($saveResult['error']),
                ], status: 400);
            }

            return ControllerTools::JSON([
                'success' => true,
                'settings' => $saveResult['settings'] ?? static::currentSettings(),
            ]);
        }

        public static function post__sendTestEmail(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isAllowed()) {
                return ControllerTools::JSON(['error' => static::getLabels()['accessDenied']], status: 403);
            }

            $labels = static::getLabels();
            $testEmail = trim((string)$globals->readPostValue('test_email', ''));

            if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                return ControllerTools::JSON(['error' => $labels['invalidEmail']], status: 400);
            }

            $manager = static::settingsManager();
            $registrationsEnabled = $manager::registrationsEnabled();
            $currentSmtp = $manager::smtpSettings();
            $smtp = static::smtpFromRequest($globals);
            $saveResult = $manager::save($registrationsEnabled, $smtp);

            if (!empty($saveResult['error'])) {
                return ControllerTools::JSON([
                    'error' => static::saveErrorMessage($saveResult['error']),
                ], status: 400);
            }

            try {
                Mailer::reset();
                Mailer::get()->sendHtmlMail(
                    $testEmail,
                    static::testEmailSubject(),
                    '<p>' . htmlspecialchars(static::testEmailBody()) . '</p>'
                );
            } catch (Throwable $e) {
                $detail = $e->getMessage();
                $errorText = $labels['sendFailed'] . ($detail ? ': ' . $detail : '');

                return ControllerTools::JSON(['error' => $errorText], status: 400);
            } finally {
                $manager::save($registrationsEnabled, $currentSmtp);
                Mailer::reset();
            }

            return ControllerTools::JSON([
                'success' => true,
                'message' => $labels['testEmailSuccess'],
            ]);
        }
    }
}

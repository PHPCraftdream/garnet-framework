<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\SystemSettings\Spec {
    use PHPCraftdream\Garnet\Bundle\Modules\SystemSettings\FwAppSettings;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use ReflectionClass;

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Reset IniConfig static caches so we can redirect file paths between tests.
     */
    function resetIniConfig(): void {
        $ref = new ReflectionClass(IniConfig::class);

        $initParams = $ref->getProperty('initParams');
        $initParams->setValue(null, []);

        $items = $ref->getProperty('items');
        $items->setValue(null, []);
    }

    /**
     * Write a temp app.ini and register it with IniConfig.
     *
     * @param array<string, mixed> $data
     */
    function makeAppIni(array $data = []): string {
        $file = tempnam(sys_get_temp_dir(), 'gs_app_');
        $lines = [];

        foreach ($data as $k => $v) {
            $lines[] = $k . ' = ' . (is_string($v) ? '"' . $v . '"' : $v);
        }
        file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL);
        IniConfig::defineAppIni($file);

        return $file;
    }

    /**
     * Write a temp email.ini and register it with IniConfig.
     *
     * @param array<string, mixed> $data
     */
    function makeEmailIni(array $data = []): string {
        $file = tempnam(sys_get_temp_dir(), 'gs_email_');
        $lines = [];

        foreach ($data as $k => $v) {
            $lines[] = $k . ' = ' . (is_string($v) ? '"' . $v . '"' : $v);
        }
        file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL);
        IniConfig::defineEmailIni($file);

        return $file;
    }

    /**
     * Remove temp files, ignore if already gone.
     */
    function cleanupFiles(string ...$files): void {
        foreach ($files as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
    }

    // ---------------------------------------------------------------------------
    // Specs
    // ---------------------------------------------------------------------------

    describe('FwAppSettings', function (): void {
        // resetIniConfig() wipes IniConfig's static $initParams to redirect
        // file paths between tests, dropping the bootstrap's ENV_DB / ENV_EMAIL
        // definitions. Restore them once this describe finishes so later specs
        // that call IniConfig::db() don't fail with "Env not found: ENV_DB".
        afterAll(function (): void {
            resetIniConfig();
            $cfg = __DIR__ . '/../../../../TestsInit/TestConfig/';
            IniConfig::defineAppIni($cfg . 'app.ini');
            IniConfig::defineDbIni($cfg . 'db.ini');
            IniConfig::defineEmailIni($cfg . 'email.ini');
        });

        // -----------------------------------------------------------------------
        describe('registrationsEnabled()', function (): void {
            it('returns true when registrations_enabled=1', function (): void {
                resetIniConfig();
                $app = makeAppIni(['registrations_enabled' => 1]);
                $email = makeEmailIni();

                expect(FwAppSettings::registrationsEnabled())->toBe(true);

                cleanupFiles($app, $email);
            });

            it('returns false when registrations_enabled=0', function (): void {
                resetIniConfig();
                $app = makeAppIni(['registrations_enabled' => 0]);
                $email = makeEmailIni();

                expect(FwAppSettings::registrationsEnabled())->toBe(false);

                cleanupFiles($app, $email);
            });

            it('defaults to true when key is absent', function (): void {
                resetIniConfig();
                $app = makeAppIni([]);
                $email = makeEmailIni();

                expect(FwAppSettings::registrationsEnabled())->toBe(true);

                cleanupFiles($app, $email);
            });
        });

        // -----------------------------------------------------------------------
        describe('brandName()', function (): void {
            it('returns the title from app.ini', function (): void {
                resetIniConfig();
                $app = makeAppIni(['title' => 'MyApp']);
                $email = makeEmailIni();

                expect(FwAppSettings::brandName())->toBe('MyApp');

                cleanupFiles($app, $email);
            });

            it('falls back to "Garnet" when title is absent', function (): void {
                resetIniConfig();
                $app = makeAppIni([]);
                $email = makeEmailIni();

                expect(FwAppSettings::brandName())->toBe('Garnet');

                cleanupFiles($app, $email);
            });

            it('falls back to "Garnet" when title is empty string', function (): void {
                resetIniConfig();
                $app = makeAppIni(['title' => '']);
                $email = makeEmailIni();

                expect(FwAppSettings::brandName())->toBe('Garnet');

                cleanupFiles($app, $email);
            });
        });

        // -----------------------------------------------------------------------
        describe('cancellationPenaltyPercent()', function (): void {
            it('returns the stored integer value', function (): void {
                resetIniConfig();
                $app = makeAppIni(['cancellation_penalty_percent' => 25]);
                $email = makeEmailIni();

                expect(FwAppSettings::cancellationPenaltyPercent())->toBe(25);

                cleanupFiles($app, $email);
            });

            it('clamps values above 100 to 100', function (): void {
                resetIniConfig();
                $app = makeAppIni(['cancellation_penalty_percent' => 150]);
                $email = makeEmailIni();

                expect(FwAppSettings::cancellationPenaltyPercent())->toBe(100);

                cleanupFiles($app, $email);
            });

            it('clamps negative values to 0', function (): void {
                resetIniConfig();
                $app = makeAppIni(['cancellation_penalty_percent' => -10]);
                $email = makeEmailIni();

                expect(FwAppSettings::cancellationPenaltyPercent())->toBe(0);

                cleanupFiles($app, $email);
            });

            it('defaults to 0 when key is absent', function (): void {
                resetIniConfig();
                $app = makeAppIni([]);
                $email = makeEmailIni();

                expect(FwAppSettings::cancellationPenaltyPercent())->toBe(0);

                cleanupFiles($app, $email);
            });
        });

        // -----------------------------------------------------------------------
        describe('supportContacts()', function (): void {
            it('returns all three contact fields', function (): void {
                resetIniConfig();
                $app = makeAppIni([
                    'support_contact_email' => 'support@example.com',
                    'support_contact_phone' => '+1234567890',
                    'support_contact_telegram' => '@mybot',
                ]);
                $email = makeEmailIni();

                $contacts = FwAppSettings::supportContacts();

                expect($contacts['email'])->toBe('support@example.com');
                expect($contacts['phone'])->toBe('+1234567890');
                expect($contacts['telegram'])->toBe('@mybot');

                cleanupFiles($app, $email);
            });

            it('returns empty strings when keys are absent', function (): void {
                resetIniConfig();
                $app = makeAppIni([]);
                $email = makeEmailIni();

                $contacts = FwAppSettings::supportContacts();

                expect($contacts['email'])->toBe('');
                expect($contacts['phone'])->toBe('');
                expect($contacts['telegram'])->toBe('');

                cleanupFiles($app, $email);
            });
        });

        // -----------------------------------------------------------------------
        describe('smtpSettings()', function (): void {
            it('returns the smtp fields from email.ini', function (): void {
                resetIniConfig();
                $app = makeAppIni([]);
                $email = makeEmailIni([
                    'enabled' => 1,
                    'scheme' => 'smtps',
                    'host' => 'smtp.example.com',
                    'port' => 587,
                    'user' => 'user@example.com',
                    'password' => 'secret',
                    'from' => 'noreply@example.com',
                    'verify_peer' => 1,
                ]);

                $smtp = FwAppSettings::smtpSettings();

                expect($smtp['enabled'])->toBe(true);
                expect($smtp['scheme'])->toBe('smtps');
                expect($smtp['host'])->toBe('smtp.example.com');
                expect($smtp['port'])->toBe('587');
                expect($smtp['user'])->toBe('user@example.com');
                expect($smtp['password'])->toBe('secret');
                expect($smtp['from'])->toBe('noreply@example.com');
                expect($smtp['verify_peer'])->toBe(true);

                cleanupFiles($app, $email);
            });

            it('defaults enabled to false when key absent', function (): void {
                resetIniConfig();
                $app = makeAppIni([]);
                $email = makeEmailIni([]);

                expect(FwAppSettings::smtpSettings()['enabled'])->toBe(false);

                cleanupFiles($app, $email);
            });
        });

        // -----------------------------------------------------------------------
        describe('save() — validation', function (): void {
            beforeEach(function (): void {
                resetIniConfig();
                $this->app = makeAppIni(['registrations_enabled' => 1]);
                $this->email = makeEmailIni(['scheme' => 'smtp', 'port' => 465]);
            });

            afterEach(function (): void {
                cleanupFiles($this->app, $this->email);
            });

            it('returns error=invalid_scheme for unknown smtp scheme', function (): void {
                $result = FwAppSettings::save(true, ['scheme' => 'ftp', 'port' => '465']);
                expect($result)->toBe(['error' => 'invalid_scheme']);
            });

            it('returns error=invalid_port when port is non-numeric', function (): void {
                $result = FwAppSettings::save(true, ['scheme' => 'smtp', 'port' => 'abc']);
                expect($result)->toBe(['error' => 'invalid_port']);
            });

            it('returns error=invalid_port when port is 0', function (): void {
                $result = FwAppSettings::save(true, ['scheme' => 'smtp', 'port' => '0']);
                expect($result)->toBe(['error' => 'invalid_port']);
            });

            it('returns error=required_host when smtp enabled but host is empty', function (): void {
                $result = FwAppSettings::save(true, [
                    'scheme' => 'smtp',
                    'port' => '465',
                    'enabled' => true,
                    'host' => '',
                    'from' => 'x@x.com',
                ]);
                expect($result)->toBe(['error' => 'required_host']);
            });

            it('returns error=required_from when smtp enabled but from is empty', function (): void {
                $result = FwAppSettings::save(true, [
                    'scheme' => 'smtp',
                    'port' => '465',
                    'enabled' => true,
                    'host' => 'smtp.example.com',
                    'from' => '',
                ]);
                expect($result)->toBe(['error' => 'required_from']);
            });

            it('returns error=invalid_penalty_percent when penalty > 100', function (): void {
                $result = FwAppSettings::save(true, ['scheme' => 'smtp', 'port' => '465'], 101);
                expect($result)->toBe(['error' => 'invalid_penalty_percent']);
            });

            it('returns error=invalid_penalty_percent when penalty < 0', function (): void {
                $result = FwAppSettings::save(true, ['scheme' => 'smtp', 'port' => '465'], -1);
                expect($result)->toBe(['error' => 'invalid_penalty_percent']);
            });
        });

        // -----------------------------------------------------------------------
        describe('save() — success path', function (): void {
            beforeEach(function (): void {
                resetIniConfig();
                $this->app = makeAppIni(['registrations_enabled' => 1]);
                $this->email = makeEmailIni(['scheme' => 'smtp', 'port' => 465]);
            });

            afterEach(function (): void {
                cleanupFiles($this->app, $this->email);
            });

            it('returns settings key on valid save', function (): void {
                $result = FwAppSettings::save(false, [
                    'scheme' => 'smtp',
                    'port' => '25',
                ]);
                expect(isset($result['settings']))->toBe(true);
            });

            it('persists registrationsEnabled flag', function (): void {
                FwAppSettings::save(false, ['scheme' => 'smtp', 'port' => '25']);
                resetIniConfig();
                IniConfig::defineAppIni($this->app);
                IniConfig::defineEmailIni($this->email);

                expect(FwAppSettings::registrationsEnabled())->toBe(false);
            });

            it('persists cancellationPenaltyPercent', function (): void {
                FwAppSettings::save(true, ['scheme' => 'smtp', 'port' => '25'], 30);
                resetIniConfig();
                IniConfig::defineAppIni($this->app);
                IniConfig::defineEmailIni($this->email);

                expect(FwAppSettings::cancellationPenaltyPercent())->toBe(30);
            });

            it('persists supportContacts', function (): void {
                FwAppSettings::save(true, ['scheme' => 'smtp', 'port' => '25'], null, [
                    'email' => 'support@x.com',
                    'phone' => '555',
                    'telegram' => '@t',
                ]);
                resetIniConfig();
                IniConfig::defineAppIni($this->app);
                IniConfig::defineEmailIni($this->email);

                $contacts = FwAppSettings::supportContacts();
                expect($contacts['email'])->toBe('support@x.com');
                expect($contacts['telegram'])->toBe('@t');
            });
        });
    });
}

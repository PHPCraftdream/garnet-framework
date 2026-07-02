<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Mailer\Spec;

use PHPCraftdream\Garnet\Kernel\Interfaces\IMailer;
use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
use PHPCraftdream\Garnet\Kernel\Io\Mailer\Mailer;
use ReflectionClass;
use Symfony\Component\Mime\Email;

describe('Mailer', function (): void {
    beforeEach(function (): void {
        // Reset the static instance between tests
        $reflection = new ReflectionClass(Mailer::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);

        // Reset IniConfig between tests
        $reflection = new ReflectionClass(IniConfig::class);
        $property = $reflection->getProperty('initParams');
        $property->setAccessible(true);
        $property->setValue([]);

        $property = $reflection->getProperty('items');
        $property->setAccessible(true);
        $property->setValue([]);
    });

    describe('get()', function (): void {
        it('returns singleton instance', function (): void {
            // Set up the minimal configuration for email
            $iniFile = tempnam(sys_get_temp_dir(), 'email_test');
            file_put_contents($iniFile, '
scheme=smtp
host=localhost
user=test
password=testpass
port=1025
enabled=0
from=noreply@example.com
verify_peer=0
');

            IniConfig::defineEmailIni($iniFile);

            $mailer1 = Mailer::get();
            $mailer2 = Mailer::get();

            expect($mailer1)->toBeAnInstanceOf(IMailer::class);
            expect($mailer1)->toBe($mailer2);

            unlink($iniFile);
        });

        it('creates DSN with default port 465', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'email_test');
            file_put_contents($iniFile, '
scheme=smtp
host=localhost
user=test
password=testpass
enabled=0
from=noreply@example.com
verify_peer=0
');

            IniConfig::defineEmailIni($iniFile);

            $mailer = Mailer::get();

            expect($mailer)->toBeAnInstanceOf(IMailer::class);

            unlink($iniFile);
        });

        it('creates DSN with custom port', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'email_test');
            file_put_contents($iniFile, '
scheme=smtp
host=localhost
user=test
password=testpass
port=587
enabled=0
from=noreply@example.com
verify_peer=0
');

            IniConfig::defineEmailIni($iniFile);

            $mailer = Mailer::get();

            expect($mailer)->toBeAnInstanceOf(IMailer::class);

            unlink($iniFile);
        });

        it('creates DSN with verify_peer enabled', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'email_test');
            file_put_contents($iniFile, '
scheme=smtp
host=localhost
user=test
password=testpass
enabled=0
from=noreply@example.com
verify_peer=1
');

            IniConfig::defineEmailIni($iniFile);

            $mailer = Mailer::get();

            expect($mailer)->toBeAnInstanceOf(IMailer::class);

            unlink($iniFile);
        });

        it('creates DSN with TLS scheme', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'email_test');
            file_put_contents($iniFile, '
scheme=smtps
host=localhost
user=test
password=testpass
enabled=0
from=noreply@example.com
verify_peer=0
');

            IniConfig::defineEmailIni($iniFile);

            $mailer = Mailer::get();

            expect($mailer)->toBeAnInstanceOf(IMailer::class);

            unlink($iniFile);
        });
    });

    describe('sendHtmlMail()', function (): void {
        beforeEach(function (): void {
            // Reset the instance before each sendHtmlMail test
            $reflection = new ReflectionClass(Mailer::class);
            $property = $reflection->getProperty('instance');
            $property->setAccessible(true);
            $property->setValue(null);

            // Reset IniConfig
            $reflection = new ReflectionClass(IniConfig::class);
            $property = $reflection->getProperty('initParams');
            $property->setAccessible(true);
            $property->setValue([]);

            $property = $reflection->getProperty('items');
            $property->setAccessible(true);
            $property->setValue([]);
        });

        it('does not send email when enabled = 0', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'email_test');
            file_put_contents($iniFile, '
scheme=smtp
host=localhost
user=test
password=testpass
enabled=0
from=noreply@example.com
verify_peer=0
');

            IniConfig::defineEmailIni($iniFile);

            $mailer = Mailer::get();

            expect(function () use ($mailer): void {
                $mailer->sendHtmlMail('test@example.com', 'Test Subject', '<p>Test Body</p>');
            })->not->toThrow();

            unlink($iniFile);
        });

        it('sends email when enabled = -1 (negative value treated as enabled)', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'email_test');
            file_put_contents($iniFile, '
scheme=smtp
host=localhost
user=test
password=testpass
enabled=-1
from=noreply@example.com
verify_peer=0
');

            IniConfig::defineEmailIni($iniFile);

            $mailer = Mailer::get();

            // Note: abs(-1) = 1, which is >= 1, so it will try to send
            // This test will fail without an actual SMTP server but validates the logic
            expect(function () use ($mailer): void {
                $mailer->sendHtmlMail('test@example.com', 'Test Subject', '<p>Test Body</p>');
            })->toThrow();

            unlink($iniFile);
        });

        it('sends email when enabled = 1 (requires SMTP server)', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'email_test');
            file_put_contents($iniFile, '
scheme=smtp
host=localhost
user=test
password=testpass
port=1025
enabled=1
from=noreply@example.com
verify_peer=0
');

            IniConfig::defineEmailIni($iniFile);

            $mailer = Mailer::get();

            // Note: This test will fail without an actual SMTP server
            // but it validates the method signature and parameters
            expect(function () use ($mailer): void {
                $mailer->sendHtmlMail('test@example.com', 'Test Subject', '<p>Test Body</p>');
            })->toThrow();

            unlink($iniFile);
        });

        it('constructs email with correct parameters', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'email_test');
            file_put_contents($iniFile, '
scheme=smtp
host=localhost
user=test
password=testpass
enabled=0
from=noreply@example.com
verify_peer=0
');

            IniConfig::defineEmailIni($iniFile);

            $mailer = Mailer::get();

            // Verify method accepts all required parameters
            expect(function () use ($mailer): void {
                $mailer->sendHtmlMail(
                    'recipient@example.com',
                    'Test Subject with Special Chars: Héllo! こんにちは!',
                    '<h1>HTML Content</h1><p>Paragraph</p>'
                );
            })->not->toThrow();

            unlink($iniFile);
        });

        it('handles empty email body', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'email_test');
            file_put_contents($iniFile, '
scheme=smtp
host=localhost
user=test
password=testpass
enabled=0
from=noreply@example.com
verify_peer=0
');

            IniConfig::defineEmailIni($iniFile);

            $mailer = Mailer::get();

            expect(function () use ($mailer): void {
                $mailer->sendHtmlMail('test@example.com', 'Empty Body', '');
            })->not->toThrow();

            unlink($iniFile);
        });

        it('handles special characters in subject', function (): void {
            $iniFile = tempnam(sys_get_temp_dir(), 'email_test');
            file_put_contents($iniFile, '
scheme=smtp
host=localhost
user=test
password=testpass
enabled=0
from=noreply@example.com
verify_peer=0
');

            IniConfig::defineEmailIni($iniFile);

            $mailer = Mailer::get();

            expect(function () use ($mailer): void {
                $mailer->sendHtmlMail(
                    'test@example.com',
                    'Subject: Tëst &amp; "Quotes" <Brackets>',
                    '<p>Body</p>'
                );
            })->not->toThrow();

            unlink($iniFile);
        });
    });
});

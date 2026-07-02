<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\EntityHistory\Spec;

use PHPCraftdream\Garnet\Bundle\Modules\EntityHistory\SystemSettingsHistory;

describe('SystemSettingsHistory::maskSecrets()', function (): void {
    it('replaces values for known secret keys with asterisks', function (): void {
        $masked = SystemSettingsHistory::maskSecrets(
            ['smtp_password' => 'real-secret', 'smtp_host' => 'mail.example.com'],
            SystemSettingsHistory::DEFAULT_SECRET_FIELDS,
        );
        expect($masked['smtp_password'])->toBe('***');
        expect($masked['smtp_host'])->toBe('mail.example.com');
    });

    it('preserves empty/null values without masking (avoid noise on default empty fields)', function (): void {
        $masked = SystemSettingsHistory::maskSecrets(
            ['smtp_password' => '', 'api_key' => null],
            SystemSettingsHistory::DEFAULT_SECRET_FIELDS,
        );
        expect($masked['smtp_password'])->toBe('');
        expect($masked['api_key'])->toBe(null);
    });

    it('masks recursively in nested arrays', function (): void {
        $masked = SystemSettingsHistory::maskSecrets(
            ['smtp' => ['password' => 'x', 'host' => 'h']],
            SystemSettingsHistory::DEFAULT_SECRET_FIELDS,
        );
        expect($masked['smtp']['password'])->toBe('***');
        expect($masked['smtp']['host'])->toBe('h');
    });

    it('matches secret tokens by substring (case-insensitive)', function (): void {
        $masked = SystemSettingsHistory::maskSecrets(
            ['stripe_secret' => 'k1', 'Webhook_Token' => 't1', 'visible' => 'v'],
            SystemSettingsHistory::DEFAULT_SECRET_FIELDS,
        );
        expect($masked['stripe_secret'])->toBe('***');
        expect($masked['Webhook_Token'])->toBe('***');
        expect($masked['visible'])->toBe('v');
    });

    it('honours additional caller-supplied secret tokens', function (): void {
        $masked = SystemSettingsHistory::maskSecrets(
            ['custom_phrase' => 'sensitive', 'name' => 'foo'],
            ['phrase'],
        );
        expect($masked['custom_phrase'])->toBe('***');
        expect($masked['name'])->toBe('foo');
    });
});

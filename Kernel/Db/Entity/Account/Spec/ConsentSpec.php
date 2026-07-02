<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Spec;

use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
use ReflectionClass;

/**
 * Creates an Account instance without hitting the DB.
 * Sets the internal params array directly so readParam() works.
 */
function makeAccountWithParams(array $params): Account {
    $ref = new ReflectionClass(Account::class);
    /** @var Account $account */
    $account = $ref->newInstanceWithoutConstructor();

    $p = $ref->getProperty('params');
    $p->setAccessible(true);
    $p->setValue($account, $params);

    return $account;
}

describe('Account consent', function (): void {
    // -----------------------------------------------------------------------
    describe('hasConsentPd()', function (): void {
        it('returns false when consent_pd_at is not set', function (): void {
            $account = makeAccountWithParams([]);
            expect($account->hasConsentPd())->toBe(false);
        });

        it('returns false when consent_pd_at is empty string', function (): void {
            $account = makeAccountWithParams([Account::PARAM_CONSENT_PD_AT => '']);
            expect($account->hasConsentPd())->toBe(false);
        });

        it('returns false when consent_pd_at is "0"', function (): void {
            $account = makeAccountWithParams([Account::PARAM_CONSENT_PD_AT => '0']);
            expect($account->hasConsentPd())->toBe(false);
        });

        it('returns true when consent_pd_at is a valid timestamp', function (): void {
            $account = makeAccountWithParams([Account::PARAM_CONSENT_PD_AT => '1700000000']);
            expect($account->hasConsentPd())->toBe(true);
        });
    });

    // -----------------------------------------------------------------------
    describe('hasConsentMarketing()', function (): void {
        it('returns false when consent_marketing_at is not set', function (): void {
            $account = makeAccountWithParams([]);
            expect($account->hasConsentMarketing())->toBe(false);
        });

        it('returns false when consent_marketing_at is empty string', function (): void {
            $account = makeAccountWithParams([Account::PARAM_CONSENT_MARKETING_AT => '']);
            expect($account->hasConsentMarketing())->toBe(false);
        });

        it('returns true when consent_marketing_at is set and not withdrawn', function (): void {
            $account = makeAccountWithParams([Account::PARAM_CONSENT_MARKETING_AT => '1700000000']);
            expect($account->hasConsentMarketing())->toBe(true);
        });

        it('returns false when withdrawn_at equals consent_at', function (): void {
            $account = makeAccountWithParams([
                Account::PARAM_CONSENT_MARKETING_AT => '1700000000',
                Account::PARAM_CONSENT_MARKETING_WITHDRAWN_AT => '1700000000',
            ]);
            expect($account->hasConsentMarketing())->toBe(false);
        });

        it('returns false when withdrawn_at is after consent_at', function (): void {
            $account = makeAccountWithParams([
                Account::PARAM_CONSENT_MARKETING_AT => '1700000000',
                Account::PARAM_CONSENT_MARKETING_WITHDRAWN_AT => '1700000001',
            ]);
            expect($account->hasConsentMarketing())->toBe(false);
        });

        it('returns true when withdrawn_at is before consent_at (re-consented)', function (): void {
            $account = makeAccountWithParams([
                Account::PARAM_CONSENT_MARKETING_AT => '1700000010',
                Account::PARAM_CONSENT_MARKETING_WITHDRAWN_AT => '1700000000',
            ]);
            expect($account->hasConsentMarketing())->toBe(true);
        });
    });

    // -----------------------------------------------------------------------
    describe('consentPdAt()', function (): void {
        it('returns null when not set', function (): void {
            $account = makeAccountWithParams([]);
            expect($account->consentPdAt())->toBeNull();
        });

        it('returns int timestamp when set', function (): void {
            $account = makeAccountWithParams([Account::PARAM_CONSENT_PD_AT => '1700000000']);
            expect($account->consentPdAt())->toBe(1700000000);
        });
    });

    // -----------------------------------------------------------------------
    describe('consentMarketingAt()', function (): void {
        it('returns null when not set', function (): void {
            $account = makeAccountWithParams([]);
            expect($account->consentMarketingAt())->toBeNull();
        });

        it('returns int timestamp when set', function (): void {
            $account = makeAccountWithParams([Account::PARAM_CONSENT_MARKETING_AT => '1700000000']);
            expect($account->consentMarketingAt())->toBe(1700000000);
        });
    });

    // -----------------------------------------------------------------------
    describe('withdrawMarketingConsent()', function (): void {
        it('makes hasConsentMarketing() return false', function (): void {
            $account = makeAccountWithParams([Account::PARAM_CONSENT_MARKETING_AT => '1700000000']);
            expect($account->hasConsentMarketing())->toBe(true);

            $account->withdrawMarketingConsent();
            expect($account->hasConsentMarketing())->toBe(false);
        });

        it('sets the withdrawn_at param to a non-empty timestamp string', function (): void {
            $account = makeAccountWithParams([Account::PARAM_CONSENT_MARKETING_AT => '1700000000']);

            $account->withdrawMarketingConsent();

            $raw = $account->readParam(Account::PARAM_CONSENT_MARKETING_WITHDRAWN_AT);
            expect($raw)->not->toBeNull();
            expect($raw)->not->toBe('');
            expect((int)$raw)->toBeGreaterThan(0);
        });
    });

    // -----------------------------------------------------------------------
    describe('constants', function (): void {
        it('PARAM_CONSENT_PD_AT is consent_pd_at', function (): void {
            expect(Account::PARAM_CONSENT_PD_AT)->toBe('consent_pd_at');
        });

        it('PARAM_CONSENT_MARKETING_AT is consent_marketing_at', function (): void {
            expect(Account::PARAM_CONSENT_MARKETING_AT)->toBe('consent_marketing_at');
        });

        it('PARAM_CONSENT_MARKETING_WITHDRAWN_AT is consent_marketing_withdrawn_at', function (): void {
            expect(Account::PARAM_CONSENT_MARKETING_WITHDRAWN_AT)->toBe('consent_marketing_withdrawn_at');
        });
    });
});

<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Spec;

use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
use ReflectionClass;

/**
 * Unit tests for Account role predicates and setters.
 *
 * Accounts are constructed via ReflectionClass::newInstanceWithoutConstructor()
 * so no DB connection is required. All state is injected through protected
 * property access.
 */
describe('Account role flags', function (): void {
    /**
     * Build a pristine Account object with empty params/data — no DB needed.
     */
    function makeAccount(): Account {
        $ref = new ReflectionClass(Account::class);
        /** @var Account $account */
        $account = $ref->newInstanceWithoutConstructor();

        // Initialise the properties the class normally sets in __construct
        $props = ['id' => 0, 'login' => '', 'params' => [], 'data' => [],
            'setParams' => [], 'setData' => [], 'unsetData' => [],
            'readDataAsyncLinks' => []];

        foreach ($props as $name => $default) {
            $prop = $ref->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue($account, $default);
        }

        return $account;
    }

    // -------------------------------------------------------------------------
    // isAdmin
    // -------------------------------------------------------------------------
    describe('isAdmin()', function (): void {
        it('returns false by default', function (): void {
            $account = makeAccount();
            expect($account->isAdmin())->toBe(false);
        });

        it('returns true after setAdmin(true)', function (): void {
            $account = makeAccount();
            $account->setAdmin(true);
            expect($account->isAdmin())->toBe(true);
        });

        it('returns false after setAdmin(false)', function (): void {
            $account = makeAccount();
            $account->setAdmin(true);
            $account->setAdmin(false);
            expect($account->isAdmin())->toBe(false);
        });

        it('setAdmin(true) twice is idempotent', function (): void {
            $account = makeAccount();
            $account->setAdmin(true);
            $account->setAdmin(true);
            expect($account->isAdmin())->toBe(true);

            $ref = new ReflectionClass($account);
            $prop = $ref->getProperty('setData');
            $prop->setAccessible(true);
            $dirty = $prop->getValue($account);
            expect($dirty[Account::IS_ADMIN])->toBe(1);
        });
    });

    // -------------------------------------------------------------------------
    // isModerator
    // -------------------------------------------------------------------------
    describe('isModerator()', function (): void {
        it('returns false by default', function (): void {
            $account = makeAccount();
            expect($account->isModerator())->toBe(false);
        });

        it('returns true after setModerator(true)', function (): void {
            $account = makeAccount();
            $account->setModerator(true);
            expect($account->isModerator())->toBe(true);
        });

        it('returns false after setModerator(false)', function (): void {
            $account = makeAccount();
            $account->setModerator(true);
            $account->setModerator(false);
            expect($account->isModerator())->toBe(false);
        });

        it('setModerator(true) twice is idempotent', function (): void {
            $account = makeAccount();
            $account->setModerator(true);
            $account->setModerator(true);
            expect($account->isModerator())->toBe(true);
        });
    });

    // -------------------------------------------------------------------------
    // isOwner
    // -------------------------------------------------------------------------
    describe('isOwner()', function (): void {
        it('returns false by default', function (): void {
            $account = makeAccount();
            expect($account->isOwner())->toBe(false);
        });

        it('returns true after setOwner(true)', function (): void {
            $account = makeAccount();
            $account->setOwner(true);
            expect($account->isOwner())->toBe(true);
        });

        it('returns false after setOwner(false)', function (): void {
            $account = makeAccount();
            $account->setOwner(true);
            $account->setOwner(false);
            expect($account->isOwner())->toBe(false);
        });

        it('setOwner(true) twice is idempotent', function (): void {
            $account = makeAccount();
            $account->setOwner(true);
            $account->setOwner(true);
            expect($account->isOwner())->toBe(true);
        });
    });

    // -------------------------------------------------------------------------
    // isApproved
    // -------------------------------------------------------------------------
    describe('isApproved()', function (): void {
        it('returns false by default', function (): void {
            $account = makeAccount();
            expect($account->isApproved())->toBe(false);
        });

        it('returns true after setApproved(true)', function (): void {
            $account = makeAccount();
            $account->setApproved(true);
            expect($account->isApproved())->toBe(true);
        });

        it('returns false after setApproved(false)', function (): void {
            $account = makeAccount();
            $account->setApproved(true);
            $account->setApproved(false);
            expect($account->isApproved())->toBe(false);
        });

        it('setApproved(true) twice is idempotent', function (): void {
            $account = makeAccount();
            $account->setApproved(true);
            $account->setApproved(true);
            expect($account->isApproved())->toBe(true);
        });
    });

    // -------------------------------------------------------------------------
    // isDisabled
    // -------------------------------------------------------------------------
    describe('isDisabled()', function (): void {
        it('returns false by default', function (): void {
            $account = makeAccount();
            expect($account->isDisabled())->toBe(false);
        });

        it('returns true after setDisabled(true)', function (): void {
            $account = makeAccount();
            $account->setDisabled(true);
            expect($account->isDisabled())->toBe(true);
        });

        it('returns false after setDisabled(false)', function (): void {
            $account = makeAccount();
            $account->setDisabled(true);
            $account->setDisabled(false);
            expect($account->isDisabled())->toBe(false);
        });

        it('setDisabled(true) twice is idempotent', function (): void {
            $account = makeAccount();
            $account->setDisabled(true);
            $account->setDisabled(true);
            expect($account->isDisabled())->toBe(true);
        });
    });

    // -------------------------------------------------------------------------
    // Flag independence
    // -------------------------------------------------------------------------
    describe('role flag independence', function (): void {
        it('setAdmin does not affect isModerator or isOwner', function (): void {
            $account = makeAccount();
            $account->setAdmin(true);
            expect($account->isModerator())->toBe(false);
            expect($account->isOwner())->toBe(false);
        });

        it('setModerator does not affect isAdmin or isOwner', function (): void {
            $account = makeAccount();
            $account->setModerator(true);
            expect($account->isAdmin())->toBe(false);
            expect($account->isOwner())->toBe(false);
        });

        it('setOwner does not affect isAdmin or isModerator', function (): void {
            $account = makeAccount();
            $account->setOwner(true);
            expect($account->isAdmin())->toBe(false);
            expect($account->isModerator())->toBe(false);
        });

        it('all flags can be true simultaneously', function (): void {
            $account = makeAccount();
            $account->setAdmin(true);
            $account->setModerator(true);
            $account->setOwner(true);
            $account->setApproved(true);
            $account->setDisabled(true);

            expect($account->isAdmin())->toBe(true);
            expect($account->isModerator())->toBe(true);
            expect($account->isOwner())->toBe(true);
            expect($account->isApproved())->toBe(true);
            expect($account->isDisabled())->toBe(true);
        });

        it('clearing one flag does not clear others', function (): void {
            $account = makeAccount();
            $account->setAdmin(true);
            $account->setModerator(true);
            $account->setOwner(true);

            $account->setAdmin(false);

            expect($account->isAdmin())->toBe(false);
            expect($account->isModerator())->toBe(true);
            expect($account->isOwner())->toBe(true);
        });
    });

    // -------------------------------------------------------------------------
    // Dirty-tracking (setData array)
    // -------------------------------------------------------------------------
    describe('role setters dirty-tracking', function (): void {
        it('setAdmin(true) records IS_ADMIN=1 in setData', function (): void {
            $account = makeAccount();
            $account->setAdmin(true);

            $ref = new ReflectionClass($account);
            $prop = $ref->getProperty('setData');
            $prop->setAccessible(true);
            $dirty = $prop->getValue($account);

            expect($dirty[Account::IS_ADMIN])->toBe(1);
        });

        it('setAdmin(false) records IS_ADMIN=0 in setData', function (): void {
            $account = makeAccount();
            $account->setAdmin(false);

            $ref = new ReflectionClass($account);
            $prop = $ref->getProperty('setData');
            $prop->setAccessible(true);
            $dirty = $prop->getValue($account);

            expect($dirty[Account::IS_ADMIN])->toBe(0);
        });

        it('setModerator(true) records IS_MODERATOR=1 in setData', function (): void {
            $account = makeAccount();
            $account->setModerator(true);

            $ref = new ReflectionClass($account);
            $prop = $ref->getProperty('setData');
            $prop->setAccessible(true);
            $dirty = $prop->getValue($account);

            expect($dirty[Account::IS_MODERATOR])->toBe(1);
        });

        it('setOwner(true) records IS_OWNER=1 in setData', function (): void {
            $account = makeAccount();
            $account->setOwner(true);

            $ref = new ReflectionClass($account);
            $prop = $ref->getProperty('setData');
            $prop->setAccessible(true);
            $dirty = $prop->getValue($account);

            expect($dirty[Account::IS_OWNER])->toBe(1);
        });

        it('setApproved(true) records IS_APPROVED=1 in setData', function (): void {
            $account = makeAccount();
            $account->setApproved(true);

            $ref = new ReflectionClass($account);
            $prop = $ref->getProperty('setData');
            $prop->setAccessible(true);
            $dirty = $prop->getValue($account);

            expect($dirty[Account::IS_APPROVED])->toBe(1);
        });

        it('setDisabled(true) records IS_DISABLED=1 in setData', function (): void {
            $account = makeAccount();
            $account->setDisabled(true);

            $ref = new ReflectionClass($account);
            $prop = $ref->getProperty('setData');
            $prop->setAccessible(true);
            $dirty = $prop->getValue($account);

            expect($dirty[Account::IS_DISABLED])->toBe(1);
        });
    });

    // -------------------------------------------------------------------------
    // Role predicates with injected data values (covers the intval path)
    // -------------------------------------------------------------------------
    describe('role predicates with injected data values', function (): void {
        it('isAdmin returns true for string "1"', function (): void {
            $account = makeAccount();
            $ref = new ReflectionClass($account);
            $prop = $ref->getProperty('data');
            $prop->setAccessible(true);
            $prop->setValue($account, [Account::IS_ADMIN => '1']);
            expect($account->isAdmin())->toBe(true);
        });

        it('isAdmin returns false for string "0"', function (): void {
            $account = makeAccount();
            $ref = new ReflectionClass($account);
            $prop = $ref->getProperty('data');
            $prop->setAccessible(true);
            $prop->setValue($account, [Account::IS_ADMIN => '0']);
            expect($account->isAdmin())->toBe(false);
        });

        it('isAdmin returns false when key is absent from data', function (): void {
            $account = makeAccount();
            expect($account->isAdmin())->toBe(false);
        });

        it('isModerator returns true for integer 1', function (): void {
            $account = makeAccount();
            $ref = new ReflectionClass($account);
            $prop = $ref->getProperty('data');
            $prop->setAccessible(true);
            $prop->setValue($account, [Account::IS_MODERATOR => 1]);
            expect($account->isModerator())->toBe(true);
        });

        it('isOwner returns true for integer 1', function (): void {
            $account = makeAccount();
            $ref = new ReflectionClass($account);
            $prop = $ref->getProperty('data');
            $prop->setAccessible(true);
            $prop->setValue($account, [Account::IS_OWNER => 1]);
            expect($account->isOwner())->toBe(true);
        });

        it('isApproved returns true for integer 1', function (): void {
            $account = makeAccount();
            $ref = new ReflectionClass($account);
            $prop = $ref->getProperty('data');
            $prop->setAccessible(true);
            $prop->setValue($account, [Account::IS_APPROVED => 1]);
            expect($account->isApproved())->toBe(true);
        });

        it('isDisabled returns true for integer 1', function (): void {
            $account = makeAccount();
            $ref = new ReflectionClass($account);
            $prop = $ref->getProperty('data');
            $prop->setAccessible(true);
            $prop->setValue($account, [Account::IS_DISABLED => 1]);
            expect($account->isDisabled())->toBe(true);
        });
    });
});

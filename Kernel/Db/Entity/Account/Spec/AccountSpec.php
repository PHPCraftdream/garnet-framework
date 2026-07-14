<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Spec;

use Mockery;
use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\DbAccount;
use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\DbAccountData;
use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IDbMySQLiLink;
use ReflectionClass;

describe('Account', function (): void {
    beforeEach(function (): void {
        // Reset static properties between tests
        $reflection = new ReflectionClass(Account::class);
        $property = $reflection->getProperty('items');
        $property->setValue(null, []);

        $property = $reflection->getProperty('sessionAccount');
        $property->setValue(null, null);

        // Account::get() eagerly performs a real async DB read even just to
        // construct/cache an instance (see Account::readDbAsync()), so every
        // test in this file — including ones that only check
        // constructor/param logic — would otherwise need a live database.
        // Mock DbAccount's singleton so the async callback fires
        // synchronously with "no row found" (null): readDbAsync()'s own
        // `if (!empty($params))` guard already treats that as a safe no-op,
        // and it's exactly the real behavior for a login/id that was never
        // persisted — which is what every `Account::get('123')` call below
        // actually is. Real read/write round-trips against a live DB are
        // covered separately by AccountIntegrationSpec.php.
        $mockLink = Mockery::mock(IDbMySQLiLink::class);
        $mockLink->shouldReceive('isBusy')->andReturn(false);

        $dbAccountMock = Mockery::mock(DbAccount::class)->shouldIgnoreMissing();
        $dbAccountMock->shouldReceive('simpleSelectOneByFieldAsync')
            ->andReturnUsing(function (string $field, $value, ?callable $callback = null) use ($mockLink): IDbMySQLiLink {
                if ($callback) {
                    $callback(null);
                }

                return $mockLink;
            });

        // readDbAsync() also eagerly queries DbAccountData whenever $this->id
        // is already known (true even for a numeric-ID login that was never
        // persisted, since the constructor sets $id from the string itself)
        // — mock it the same way, with "no rows" (empty(...) also treats
        // that as a safe no-op).
        $dbAccountDataMock = Mockery::mock(DbAccountData::class)->shouldIgnoreMissing();
        $dbAccountDataMock->shouldReceive('simpleSelectByFieldAsync')
            ->andReturnUsing(function (string $field, $value, ?callable $callback = null) use ($mockLink): IDbMySQLiLink {
                if ($callback) {
                    $callback([]);
                }

                return $mockLink;
            });

        $dbTableReflection = new ReflectionClass(DbTable::class);
        $itemsProp = $dbTableReflection->getProperty('items');
        $items = $itemsProp->getValue();
        $items[DbAccount::class] = $dbAccountMock;
        $items[DbAccountData::class] = $dbAccountDataMock;
        $itemsProp->setValue(null, $items);
    });

    afterEach(function (): void {
        Mockery::close();

        $dbTableReflection = new ReflectionClass(DbTable::class);
        $itemsProp = $dbTableReflection->getProperty('items');
        $items = $itemsProp->getValue();
        unset($items[DbAccount::class], $items[DbAccountData::class]);

        $itemsProp->setValue(null, $items);
    });

    describe('get() and constructor', function (): void {
        it('creates account with numeric ID', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('id');

            expect($property->getValue($account))->toBe(123);
        });

        it('creates account with login string', function (): void {
            $account = Account::get('test@example.com');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('login');

            expect($property->getValue($account))->toBe('test@example.com');

            $property = $reflection->getProperty('id');

            expect($property->getValue($account))->toBe(0);
        });

        it('returns same instance for same login', function (): void {
            $account1 = Account::get('test@example.com');
            $account2 = Account::get('test@example.com');

            expect($account1)->toBe($account2);
        });

        it('returns different instances for different logins', function (): void {
            $account1 = Account::get('test1@example.com');
            $account2 = Account::get('test2@example.com');

            expect($account1)->not->toBe($account2);
        });
    });

    describe('readParam()', function (): void {
        it('returns null for non-existent param', function (): void {
            $account = Account::get('123');

            expect($account->readParam('nonexistent'))->toBeNull();
        });

        it('returns param value when set', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('params');
            $property->setValue($account, ['name' => 'John']);

            expect($account->readParam('name'))->toBe('John');
        });

        it('returns default value when param does not exist', function (): void {
            $account = Account::get('123');

            expect($account->readParam('name', 'Default'))->toBe('Default');
        });
    });

    describe('readParams()', function (): void {
        it('returns array with null values for non-existent params', function (): void {
            $account = Account::get('123');

            $result = $account->readParams(['name', 'email', 'phone']);

            expect($result)->toBe(['name' => null, 'email' => null, 'phone' => null]);
        });

        it('returns array with values for existing params', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('params');
            $property->setValue($account, ['name' => 'John', 'email' => 'john@example.com']);

            $result = $account->readParams(['name', 'email', 'phone']);

            expect($result)->toBe(['name' => 'John', 'email' => 'john@example.com', 'phone' => null]);
        });
    });

    describe('readData()', function (): void {
        it('returns null for non-existent data', function (): void {
            $account = Account::get('123');

            expect($account->readData('phone'))->toBeNull();
        });

        it('returns data value when set', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('data');
            $property->setValue($account, ['phone' => '+1234567890']);

            expect($account->readData('phone'))->toBe('+1234567890');
        });
    });

    describe('readDataParams()', function (): void {
        it('returns array with values for existing data', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('data');
            $property->setValue($account, ['phone' => '+1234567890', 'address' => 'Street 1']);

            $result = $account->readDataParams(['phone', 'address', 'bio']);

            expect($result)->toBe(['phone' => '+1234567890', 'address' => 'Street 1', 'bio' => null]);
        });
    });

    describe('setParam()', function (): void {
        it('sets param and tracks as dirty', function (): void {
            $account = Account::get('123');

            $account->setParam('name', 'John');

            expect($account->readParam('name'))->toBe('John');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('setParams');

            expect($property->getValue($account))->toBe(['name' => 'John']);
        });

        it('does not track as dirty when value unchanged', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('params');
            $property->setValue($account, ['name' => 'John']);

            $account->setParam('name', 'John');

            $property = $reflection->getProperty('setParams');

            expect($property->getValue($account))->toBe([]);
        });
    });

    describe('setParams()', function (): void {
        it('sets multiple params and tracks them as dirty', function (): void {
            $account = Account::get('123');

            $account->setParams(['name' => 'John', 'email' => 'john@example.com']);

            expect($account->readParam('name'))->toBe('John');
            expect($account->readParam('email'))->toBe('john@example.com');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('setParams');

            expect($property->getValue($account))->toBe([
                'name' => 'John',
                'email' => 'john@example.com',
            ]);
        });
    });

    describe('setData()', function (): void {
        it('sets data and tracks as dirty', function (): void {
            $account = Account::get('123');

            $account->setData('phone', '+1234567890');

            expect($account->readData('phone'))->toBe('+1234567890');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('setData');

            expect($property->getValue($account))->toBe(['phone' => '+1234567890']);
        });

        it('removes from unsetData when set', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('unsetData');
            $property->setValue($account, ['phone' => true]);

            $account->setData('phone', '+1234567890');

            expect($property->getValue($account))->toBe([]);
        });
    });

    describe('unsetData()', function (): void {
        it('removes data and tracks as unset', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('data');
            $property->setValue($account, ['phone' => '+1234567890']);

            $account->unsetData('phone');

            expect($account->readData('phone'))->toBeNull();

            $property = $reflection->getProperty('unsetData');

            expect($property->getValue($account))->toBe(['phone' => true]);
        });

        it('removes from setData when unset', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('setData');
            $property->setValue($account, ['phone' => '+1234567890']);

            $account->unsetData('phone');

            expect($property->getValue($account))->toBe([]);
        });
    });

    describe('setBoolDataArr()', function (): void {
        it('unsets false values', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('data');
            $property->setValue($account, ['phone' => '1', 'address' => '1']);

            $account->setBoolDataArr(['phone' => '0', 'address' => '0']);

            $property = $reflection->getProperty('unsetData');

            expect($property->getValue($account))->toBe(['phone' => true, 'address' => true]);
        });

        it('does not track unchanged values', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('data');
            $property->setValue($account, ['phone' => '1']);

            $account->setBoolDataArr(['phone' => '1']);

            $property = $reflection->getProperty('setData');

            expect($property->getValue($account))->toBe([]);
        });
    });

    describe('setDataArr()', function (): void {
        it('sets multiple data values', function (): void {
            $account = Account::get('123');

            $account->setDataArr(['phone' => '+1234567890', 'address' => 'Street 1']);

            expect($account->readData('phone'))->toBe('+1234567890');
            expect($account->readData('address'))->toBe('Street 1');
        });
    });

    describe('unsetDataArr()', function (): void {
        it('unsets multiple data values', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('data');
            $property->setValue($account, ['phone' => '+1234567890', 'address' => 'Street 1']);

            $account->unsetDataArr(['phone', 'address']);

            expect($account->readData('phone'))->toBeNull();
            expect($account->readData('address'))->toBeNull();

            $property = $reflection->getProperty('unsetData');

            expect($property->getValue($account))->toBe(['phone' => true, 'address' => true]);
        });
    });

    describe('setAdmin()', function (): void {
        it('sets admin flag to true', function (): void {
            $account = Account::get('123');
            $account->setAdmin(true);

            expect($account->isAdmin())->toBe(true);

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('setData');

            expect($property->getValue($account)[Account::IS_ADMIN])->toBe(1);
        });

        it('sets admin flag to false', function (): void {
            $account = Account::get('123');
            $account->setAdmin(true);
            $account->setAdmin(false);

            expect($account->isAdmin())->toBe(false);

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('setData');

            expect($property->getValue($account)[Account::IS_ADMIN])->toBe(0);
        });
    });

    describe('setModerator()', function (): void {
        it('sets moderator flag to true', function (): void {
            $account = Account::get('123');
            $account->setModerator(true);

            expect($account->isModerator())->toBe(true);
        });
    });

    describe('setApproved()', function (): void {
        it('sets approved flag to true', function (): void {
            $account = Account::get('123');
            $account->setApproved(true);

            expect($account->isApproved())->toBe(true);
        });
    });

    describe('setDisabled()', function (): void {
        it('sets disabled flag to true', function (): void {
            $account = Account::get('123');
            $account->setDisabled(true);

            expect($account->isDisabled())->toBe(true);
        });
    });

    describe('isAdmin(), isModerator(), isApproved(), isDisabled()', function (): void {
        it('returns false when flag not set', function (): void {
            $account = Account::get('123');

            expect($account->isAdmin())->toBe(false);
            expect($account->isModerator())->toBe(false);
            expect($account->isApproved())->toBe(false);
            expect($account->isDisabled())->toBe(false);
        });

        it('returns true when flag is set to 1', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('data');
            $property->setValue($account, [
                Account::IS_ADMIN => 1,
                Account::IS_MODERATOR => 1,
                Account::IS_APPROVED => 1,
                Account::IS_DISABLED => 1,
            ]);

            expect($account->isAdmin())->toBe(true);
            expect($account->isModerator())->toBe(true);
            expect($account->isApproved())->toBe(true);
            expect($account->isDisabled())->toBe(true);
        });

        it('returns false when flag is set to 0', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('data');
            $property->setValue($account, [
                Account::IS_ADMIN => 0,
                Account::IS_MODERATOR => 0,
                Account::IS_APPROVED => 0,
                Account::IS_DISABLED => 0,
            ]);

            expect($account->isAdmin())->toBe(false);
            expect($account->isModerator())->toBe(false);
            expect($account->isApproved())->toBe(false);
            expect($account->isDisabled())->toBe(false);
        });
    });

    describe('getParams()', function (): void {
        it('returns all params', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('params');
            $property->setValue($account, ['name' => 'John', 'email' => 'john@example.com']);

            expect($account->getParams())->toBe(['name' => 'John', 'email' => 'john@example.com']);
        });
    });

    describe('getData()', function (): void {
        it('returns all data', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('data');
            $property->setValue($account, ['phone' => '+1234567890', 'address' => 'Street 1']);

            expect($account->getData())->toBe(['phone' => '+1234567890', 'address' => 'Street 1']);
        });
    });

    describe('id()', function (): void {
        it('returns 0 when id not set', function (): void {
            $account = Account::get('test@example.com');

            expect($account->id())->toBe(0);
        });

        it('returns id as int when set', function (): void {
            $account = Account::get('123456');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('params');
            $property->setValue($account, ['id' => '123']);

            expect($account->id())->toBe(123);
        });
    });

    describe('readDataAsyncLinks management', function (): void {
        it('starts with empty async links', function (): void {
            $account = Account::get('123');

            $reflection = new ReflectionClass($account);
            $property = $reflection->getProperty('readDataAsyncLinks');

            expect($property->getValue($account))->toBe([]);
        });

        it('has methods for async poll management', function (): void {
            $account = Account::get('123');

            expect(method_exists($account, 'readDataAsyncPollFinishAll'))->toBe(true);
            expect(method_exists($account, 'readDataAsyncPollOnce'))->toBe(true);
        });
    });
});

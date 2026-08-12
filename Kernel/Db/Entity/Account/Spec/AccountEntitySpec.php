<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Spec;

use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\AccountEntity;

describe('AccountEntity', function (): void {
    describe('field lists do not include app-specific columns', function (): void {
        it('selectFields() does not include type or photo', function (): void {
            $entity = new AccountEntity();
            $fields = $entity->selectFields();

            expect($fields)->not->toContain('type');
            expect($fields)->not->toContain('photo');
        });

        it('manageFormFields() does not include type or photo', function (): void {
            $entity = new AccountEntity();
            $fields = $entity->manageFormFields();

            expect($fields)->not->toContain('type');
            expect($fields)->not->toContain('photo');
        });

        it('viewFields() does not include type or photo', function (): void {
            $entity = new AccountEntity();
            $fields = $entity->viewFields();

            expect($fields)->not->toContain('type');
            expect($fields)->not->toContain('photo');
        });

        it('editFields() does not include type or photo', function (): void {
            $entity = new AccountEntity();
            $fields = $entity->editFields();

            expect($fields)->not->toContain('type');
            expect($fields)->not->toContain('photo');
        });
    });

    describe('field lists include only base account columns', function (): void {
        it('selectFields() includes all base columns', function (): void {
            $entity = new AccountEntity();
            $fields = $entity->selectFields();

            $expected = ['id', 'login', 'login_type', 'name', 'time_zone', 'about',
                'reg_time', 'last_auth_time', 'last_online_time', 'token16'];
            expect($fields)->toBe($expected);
        });

        it('manageFormFields() includes only base columns', function (): void {
            $entity = new AccountEntity();
            $fields = $entity->manageFormFields();

            $expected = ['id', 'login', 'reg_time', 'last_auth_time', 'last_online_time',
                'name', 'time_zone', 'about', 'IS_ADMIN', 'IS_MODERATOR', 'IS_APPROVED', 'IS_DISABLED'];
            expect($fields)->toBe($expected);
        });

        it('viewFields() includes only base columns', function (): void {
            $entity = new AccountEntity();
            $fields = $entity->viewFields();

            expect($fields)->toBe(['id', 'name', 'about']);
        });

        it('editFields() includes only base columns', function (): void {
            $entity = new AccountEntity();
            $fields = $entity->editFields();

            expect($fields)->toBe(['id', 'login', 'name', 'time_zone', 'about']);
        });
    });
});

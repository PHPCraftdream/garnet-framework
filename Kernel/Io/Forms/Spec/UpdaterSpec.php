<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Forms\Spec;

use PHPCraftdream\Garnet\Kernel\Io\Forms\Updater;
use PHPCraftdream\Garnet\Kernel\Io\Logs\Logger;
use Throwable;

describe('Updater', function (): void {
    beforeEach(function (): void {
        // Ensure logger is set up for I18n translations
        try {
            Logger::get('ERROR_LOGGER');
        } catch (Throwable $e) {
            Logger::define(sys_get_temp_dir(), 'ERROR_LOGGER');
        }
    });

    describe('Constructor', function (): void {
        it('stores saveData and files', function (): void {
            $saveData = ['name' => 'test', 'email' => 'test@example.com'];
            $files = ['avatar' => ['tmp_name' => '/tmp/file.jpg']];

            $updater = new Updater($saveData, $files);

            expect($updater->saveData)->toBe($saveData);
            expect($updater->files)->toBe($files);
            expect($updater->errors)->toBe([]);
            expect($updater->commonErrors)->toBe([]);
            expect($updater->resultData)->toBe([]);
        });

        it('handles empty arrays', function (): void {
            $updater = new Updater([], []);

            expect($updater->saveData)->toBe([]);
            expect($updater->files)->toBe([]);
        });
    });

    describe('Error management', function (): void {
        it('adds and retrieves field errors', function (): void {
            $updater = new Updater([], []);
            $updater->addError('name', 'Name is required');

            expect(array_key_exists('name', $updater->errors))->toBe(true);
            expect($updater->errors['name'])->toBe(['Name is required']);
        });

        it('appends multiple errors for same field', function (): void {
            $updater = new Updater([], []);
            $updater->addError('email', 'Invalid format');
            $updater->addError('email', 'Already exists');

            expect($updater->errors['email'])->toBe(['Invalid format', 'Already exists']);
        });

        it('returns formatted errors with field errors', function (): void {
            $updater = new Updater([], []);
            $updater->addError('name', 'Required');
            $updater->addError('email', 'Invalid');

            $errors = $updater->getErrors();

            expect($errors)->toBe([
                'errors' => [
                    'name' => ['Required'],
                    'email' => ['Invalid'],
                ],
            ]);
        });

        it('includes commonErrors when present', function (): void {
            $updater = new Updater([], []);
            $updater->commonErrors[] = 'System error';
            $updater->addError('name', 'Required');

            $errors = $updater->getErrors();

            expect(array_key_exists('commonErrors', $errors))->toBe(true);
            expect($errors['commonErrors'])->toBe(['System error']);
        });

        it('tracks hasErrors when errors exist', function (): void {
            $updater = new Updater([], []);
            expect($updater->hasErrors())->toBe(false);

            $updater->addError('name', 'Required');
            expect($updater->hasErrors())->toBe(true);
        });
    });

    describe('Result data management', function (): void {
        it('sets and retrieves values', function (): void {
            $updater = new Updater([], []);
            $updater->set('name', 'John Doe');

            expect($updater->resultData['name'])->toBe('John Doe');
        });

        it('overwrites existing values', function (): void {
            $updater = new Updater([], []);
            $updater->set('name', 'John');
            $updater->set('name', 'Jane');

            expect($updater->resultData['name'])->toBe('Jane');
        });

        it('handles null values', function (): void {
            $updater = new Updater([], []);
            $updater->set('deleted_at', null);

            expect($updater->resultData['deleted_at'])->toBeNull();
        });

        it('handles empty strings', function (): void {
            $updater = new Updater([], []);
            $updater->set('name', '');

            expect($updater->resultData['name'])->toBe('');
        });
    });

    describe('Validation rules', function (): void {
        it('required: fails for empty string', function (): void {
            $updater = new Updater(['name' => ''], []);
            $result = $updater->required('');

            expect(is_string($result))->toBe(true);
            expect($result)->toContain('RequiredValue');
        });

        it('required: passes for non-empty value', function (): void {
            $updater = new Updater(['name' => 'John'], []);
            $result = $updater->required('John');

            expect($result)->toBe(true);
        });

        it('minLen: fails for too short value', function (): void {
            $updater = new Updater(['name' => 'ab'], []);
            $result = $updater->minLen('ab', 3);

            expect(is_string($result))->toBe(true);
            expect($result)->toContain('MinLength');
        });

        it('minLen: passes for valid length', function (): void {
            $updater = new Updater(['name' => 'John'], []);
            $result = $updater->minLen('John', 3);

            expect($result)->toBe(true);
        });

        it('maxLen: fails for too long value', function (): void {
            $updater = new Updater(['name' => 'This is very long name'], []);
            $result = $updater->maxLen('This is very long name', 10);

            expect(is_string($result))->toBe(true);
            expect($result)->toContain('MaxLength');
        });

        it('maxLen: passes for valid length', function (): void {
            $updater = new Updater(['name' => 'John'], []);
            $result = $updater->maxLen('John', 10);

            expect($result)->toBe(true);
        });

        it('in_array: fails for value not in allowed list', function (): void {
            $updater = new Updater(['role' => 'admin'], []);
            $result = $updater->in_array('admin', 'user', 'moderator');

            expect(is_string($result))->toBe(true);
            expect($result)->toContain('IncorrectValue');
        });

        it('in_array: passes for valid value', function (): void {
            $updater = new Updater(['role' => 'admin'], []);
            $result = $updater->in_array('admin', 'user', 'admin', 'moderator');

            expect($result)->toBe(true);
        });
    });

    describe('Integration: validation with error tracking', function (): void {
        it('adds error when validation fails', function (): void {
            $updater = new Updater(['name' => ''], []);
            $updater->v('name', 'required');

            expect($updater->hasErrors())->toBe(true);
        });

        it('chains multiple validations', function (): void {
            $updater = new Updater(['email' => 'ab'], []);

            $result1 = $updater->minLen('ab', 5);
            $result2 = $updater->maxLen('ab', 100);

            expect(is_string($result1))->toBe(true);
            expect($result2)->toBe(true);
        });
    });
});

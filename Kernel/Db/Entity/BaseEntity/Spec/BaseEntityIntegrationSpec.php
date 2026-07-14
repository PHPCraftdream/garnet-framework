<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity\Spec;

use Exception;
use PDO;
use PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity\BaseEntity;
use PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity\SaveEntityResult;

// Helper function to get db config path
function getDbConfigPath(): string {
    // Get absolute path to Framework directory
    // __DIR__ is Framework/Kernel/Db/Entity/BaseEntity/Spec
    // Need 5 dirname() calls to get to Framework/
    $frameworkDir = dirname(dirname(dirname(dirname(dirname(__DIR__)))));

    return $frameworkDir . '/TestsInit/TestConfig/db.ini';
}

describe('BaseEntity Integration', function (): void {
    class TestIntegrationEntity extends BaseEntity {
        public string $tableName = 'dbtest_test_entities';

        public function selectFields(): array {
            return ['id', 'name', 'email', 'age', 'created_at'];
        }

        public function manageGridFields(): array {
            return ['id', 'name', 'email', 'age'];
        }

        public function manageFormFields(): array {
            return ['id', 'name', 'email', 'age'];
        }

        public function viewFields(): array {
            return ['id', 'name', 'email'];
        }

        public function editFields(): array {
            return ['id', 'name', 'email', 'age'];
        }

        public function dataFields(): array {
            return [];
        }

        public function patchItem(array &$item): array {
            return $item;
        }

        public function getFieldsInfo(?array $fields = null): array {
            $result = [
                'id' => [
                    'name' => 'ID',
                    'readOnly' => true,
                ],
                'name' => [
                    'name' => 'Name',
                    'type' => 'input',
                    'validation' => ['len[3,50]', 'nameSymbols'],
                ],
                'email' => [
                    'name' => 'Email',
                    'type' => 'input',
                    'validation' => ['email'],
                ],
                'age' => [
                    'name' => 'Age',
                    'type' => 'input',
                    'validation' => ['int', 'min[0]', 'max[150]'],
                ],
                'created_at' => [
                    'name' => 'Created At',
                    'readOnly' => true,
                ],
            ];

            return $this->filterKeys($result, $fields);
        }
    }

    $dbAvailable = false;

    beforeAll(function () use (&$dbAvailable): void {
        // Load database configuration
        $dbConfigPath = getDbConfigPath();

        if (!file_exists($dbConfigPath)) {
            return;
        }

        $config = parse_ini_file($dbConfigPath);

        if (!isset($config['enabled']) || $config['enabled'] !== '1') {
            return;
        }

        // Create test table using QueryExPdo
        $iniConfig = \PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig::db();
        $dsn = $iniConfig->param('dsn');
        $user = $iniConfig->param('user');
        $password = $iniConfig->param('password');

        try {
            $pdo = new \PHPCraftdream\Garnet\Kernel\Db\Link\ExtPDO($dsn, $user, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $queryEx = \PHPCraftdream\Garnet\Kernel\Db\Query\QueryExPdo::get();
            $queryEx->setPDO($pdo);

            // Create test table
            $sql = '
                CREATE TABLE IF NOT EXISTS dbtest_test_entities (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(50) NOT NULL,
                    email VARCHAR(100) NOT NULL UNIQUE,
                    age INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci
            ';
            $queryEx->ex($sql, []);

            $dbAvailable = true;
        } catch (Exception $e) {
            // Database not available, tests will be skipped
        }
    });

    describe('saveOne() method', function () use (&$dbAvailable): void {
        beforeEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $queryEx = \PHPCraftdream\Garnet\Kernel\Db\Query\QueryExPdo::get();
            $queryEx->ex("DELETE FROM dbtest_test_entities WHERE email LIKE 'test_%'", []);
        });

        afterEach(function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            // Clean up test data
            $queryEx = \PHPCraftdream\Garnet\Kernel\Db\Query\QueryExPdo::get();
            $queryEx->ex("DELETE FROM dbtest_test_entities WHERE email LIKE 'test_%'", []);
        });

        it('validates and processes valid data', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $postData = [
                'name' => 'John Doe',
                'email' => 'test_john@example.com',
                'age' => 30,
            ];

            $fields = ['name', 'email', 'age'];
            $saveFiles = null;

            $result = $entity->saveOne($postData, $fields, $saveFiles);

            expect($result)->toBeAnInstanceOf(SaveEntityResult::class);
            expect($result->getParams())->not->toBeEmpty();
            expect($result->getParams()->getData())->toBe($postData);
            expect($result->getAddData())->toBeNull();
        });

        it('validates name with length constraints', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $postData = [
                'name' => 'Jo', // Too short
                'email' => 'test_short@example.com',
                'age' => 25,
            ];

            $fields = ['name', 'email', 'age'];
            $saveFiles = null;

            $result = $entity->saveOne($postData, $fields, $saveFiles);

            expect($result)->toBeAnInstanceOf(SaveEntityResult::class);
            // Should have validation errors
            expect($result->getParams()->getErrors())->not->toBeEmpty();
        });

        it('validates email format', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $postData = [
                'name' => 'Jane Doe',
                'email' => 'invalid-email',
                'age' => 28,
            ];

            $fields = ['name', 'email', 'age'];
            $saveFiles = null;

            $result = $entity->saveOne($postData, $fields, $saveFiles);

            expect($result)->toBeAnInstanceOf(SaveEntityResult::class);
            // Should have validation errors
            expect($result->getParams()->getErrors())->not->toBeEmpty();
        });

        it('validates age is integer and within range', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $postData = [
                'name' => 'Test User',
                'email' => 'test_age@example.com',
                'age' => 200, // Over max
            ];

            $fields = ['name', 'email', 'age'];
            $saveFiles = null;

            $result = $entity->saveOne($postData, $fields, $saveFiles);

            expect($result)->toBeAnInstanceOf(SaveEntityResult::class);
            // Should have validation errors
            expect($result->getParams()->getErrors())->not->toBeEmpty();
        });

        it('filters fields based on provided field list', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $postData = [
                'name' => 'Test User',
                'email' => 'test_filter@example.com',
                'age' => 35,
                'extra_field' => 'should be ignored',
            ];

            $fields = ['name', 'email']; // Only these fields should be validated
            $saveFiles = null;

            $result = $entity->saveOne($postData, $fields, $saveFiles);

            expect($result)->toBeAnInstanceOf(SaveEntityResult::class);
            $filteredData = $result->getParams()->getData();
            expect(isset($filteredData['extra_field']))->toBe(false);
        });

        it('handles null values for optional fields', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $postData = [
                'name' => 'Null Age User',
                'email' => 'test_null@example.com',
                'age' => '',
            ];

            $fields = ['name', 'email', 'age'];
            $saveFiles = null;

            $result = $entity->saveOne($postData, $fields, $saveFiles);

            expect($result)->toBeAnInstanceOf(SaveEntityResult::class);
            expect($result->getParams())->not->toBeEmpty();
        });

        it('handles missing optional fields', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $postData = [
                'name' => 'Missing Field User',
                'email' => 'test_missing@example.com',
            ];

            $fields = ['name', 'email', 'age']; // age is optional
            $saveFiles = null;

            $result = $entity->saveOne($postData, $fields, $saveFiles);

            expect($result)->toBeAnInstanceOf(SaveEntityResult::class);
            expect($result->getParams())->not->toBeEmpty();
        });

        it('returns empty result when no data provided', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $postData = [];

            $fields = ['name', 'email'];
            $saveFiles = null;

            $result = $entity->saveOne($postData, $fields, $saveFiles);

            expect($result)->toBeAnInstanceOf(SaveEntityResult::class);
            expect($result->getParams()->getData())->toBe([]);
        });
    });

    describe('getFieldsInfo() method', function () use (&$dbAvailable): void {
        it('returns all fields when no filter provided', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $fieldsInfo = $entity->getFieldsInfo();

            expect($fieldsInfo)->toBeAn('array');
            expect(isset($fieldsInfo['id']))->toBe(true);
            expect(isset($fieldsInfo['name']))->toBe(true);
            expect(isset($fieldsInfo['email']))->toBe(true);
            expect(isset($fieldsInfo['age']))->toBe(true);
            expect(isset($fieldsInfo['created_at']))->toBe(true);
        });

        it('filters fields based on provided list', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $fieldsInfo = $entity->getFieldsInfo(['name', 'email']);

            expect($fieldsInfo)->toBeAn('array');
            expect(count($fieldsInfo))->toBe(2);
            expect(isset($fieldsInfo['name']))->toBe(true);
            expect(isset($fieldsInfo['email']))->toBe(true);
            expect(isset($fieldsInfo['id']))->toBe(false);
        });

        it('returns empty array when fields do not exist', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $fieldsInfo = $entity->getFieldsInfo(['nonexistent_field']);

            expect($fieldsInfo)->toBe([]);
        });
    });

    describe('getGridInfo() method', function () use (&$dbAvailable): void {
        it('returns complete grid configuration', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $gridInfo = $entity->getGridInfo();

            expect($gridInfo)->toBeAn('array');
            expect(isset($gridInfo['idColumn']))->toBe(true);
            expect(isset($gridInfo['fields']))->toBe(true);
            expect(isset($gridInfo['gridFields']))->toBe(true);
            expect(isset($gridInfo['detailsFields']))->toBe(true);
            expect($gridInfo['idColumn'])->toBe('id');
            expect($gridInfo['gridFields'])->toBe($entity->manageGridFields());
            expect($gridInfo['detailsFields'])->toBe($entity->manageFormFields());
        });
    });

    describe('filterKeys() method', function () use (&$dbAvailable): void {
        it('returns all keys when no filter provided', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $src = ['name' => 'John', 'email' => 'john@example.com', 'age' => 30];
            $filtered = $entity->filterKeys($src);

            expect($filtered)->toBe($src);
        });

        it('filters to only specified keys', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $src = ['name' => 'John', 'email' => 'john@example.com', 'age' => 30];
            $filtered = $entity->filterKeys($src, ['name', 'email']);

            expect(count($filtered))->toBe(2);
            expect(isset($filtered['name']))->toBe(true);
            expect(isset($filtered['email']))->toBe(true);
            expect(isset($filtered['age']))->toBe(false);
        });

        it('returns empty when keys do not exist in source', function () use (&$dbAvailable): void {
            if (!$dbAvailable) {
                return;
            }

            $entity = new TestIntegrationEntity();
            $src = ['name' => 'John', 'email' => 'john@example.com'];
            $filtered = $entity->filterKeys($src, ['nonexistent']);

            expect($filtered)->toBe([]);
        });
    });
});

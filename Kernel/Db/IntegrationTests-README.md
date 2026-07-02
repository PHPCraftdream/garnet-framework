# Database Integration Tests

This directory contains integration tests that verify database operations against a real MySQL database.

## Configuration

Integration tests are controlled by `Framework/TestsInit/TestConfig/db.ini`.

To enable database tests, set:
```ini
enabled = 1
```

When `enabled != 1`, all integration tests will be skipped (marked as Pending).

## Database Requirements

The integration tests expect a MySQL database with the following settings:

- **Host:** localhost:3306
- **Database:** test
- **User:** test
- **Password:** test
- **Engine:** InnoDB
- **Charset:** UTF8 / utf8mb4_unicode_ci
- **Table Prefix:** "db"

## How It Works

1. **Database Availability Check:** Before running any test, the framework checks:
   - Does `db.ini` exist?
   - Is `enabled = 1` set in the config?
   - Can we actually connect to the database?

2. **Graceful Skipping:** If the database is unavailable (wrong credentials, server not running, etc.), tests are marked as "Pending" and skipped without failing.

3. **Table Creation:** Test tables are created dynamically before tests run and cleaned up afterward.

## Running Integration Tests

Run individual integration test files:
```bash
# DbPool tests
php vendor/bin/kahlan --spec=Framework/Db/Link/Spec/DbPoolIntegrationSpec.php

# QueryExPdo tests
php vendor/bin/kahlan --spec=Framework/Db/Query/Spec/QueryExPdoIntegrationSpec.php

# BaseEntity tests
php vendor/bin/kahlan --spec=Framework/Db/Entity/BaseEntity/Spec/BaseEntityIntegrationSpec.php

# Settings tests
php vendor/bin/kahlan --spec=Framework/Db/Entity/Settings/Spec/SettingsIntegrationSpec.php

# Session tests
php vendor/bin/kahlan --spec=Framework/Db/Entity/Session/Spec/SessionIntegrationSpec.php

# Account tests
php vendor/bin/kahlan --spec=Framework/Db/Entity/Account/Spec/AccountIntegrationSpec.php
```

Or run all integration tests:
```bash
php vendor/bin/kahlan --spec=Framework/Db/**/Spec/*IntegrationSpec.php
```

## Test Coverage

| Component | Test File | Tests | Coverage |
|-----------|-----------|-------|----------|
| DbPool | `Framework/Db/Link/Spec/DbPoolIntegrationSpec.php` | 15 | Connection pool, singleton pattern, CRUD operations, transactions |
| QueryExPdo | `Framework/Db/Query/Spec/QueryExPdoIntegrationSpec.php` | 18 | SELECT, INSERT, UPDATE, DELETE, query builder, async operations |
| BaseEntity | `Framework/Db/Entity/BaseEntity/Spec/BaseEntityIntegrationSpec.php` | 15 | saveOne(), validation, field filtering, grid info |
| Settings | `Framework/Db/Entity/Settings/Spec/SettingsIntegrationSpec.php` | 16 | getValue(), setValue(), unsetValue(), caching, flush() |
| Session | `Framework/Db/Entity/Session/Spec/SessionIntegrationSpec.php` | 16 | Session data management, persistence, token handling |
| Account | `Framework/Db/Entity/Account/Spec/AccountIntegrationSpec.php` | 18 | Account creation, params, data, flags (admin, moderator, etc.) |

**Total: 98 integration tests**

## Test Tables Created

Integration tests create the following temporary tables:

- `db_test_users` - For DbPool tests
- `db_test_products` - For QueryExPdo tests
- `db_test_entities` - For BaseEntity tests
- `db_settings` - For Settings tests
- `db_session` - For Session tests (main table)
- `db_session_data` - For Session tests (key-value data)
- `db_account` - For Account tests (main table)
- `db_account_data` - For Account tests (key-value params)

## Without Database

When `enabled != 1` or database is unavailable:
- Tests are marked as "Pending"
- No test failures occur
- CI/CD pipelines can run without needing a database
- Test suite completes quickly

## With Database

When database is available and configured:
- All integration tests execute
- Real database operations are verified
- Data is cleaned up between tests
- Comprehensive coverage of database interactions

## Troubleshooting

**Tests are marked as Pending:**
- Check `enabled = 1` in `db.ini`
- Verify MySQL is running on localhost:3306
- Confirm database 'test' exists
- Check user 'test' has proper permissions

**Connection errors:**
- Verify MySQL credentials in db.ini
- Check firewall settings
- Ensure MySQL server is accepting connections

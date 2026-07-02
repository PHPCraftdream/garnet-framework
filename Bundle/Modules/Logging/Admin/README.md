# Admin Action Log Module

## Purpose
Audit trail for administrative actions. Records who did what, to whom, when, with old/new values.

## Table Schema
- `id` -- auto-increment primary key
- `actor_id` -- account ID of the admin who performed the action
- `actor_login` -- login string (denormalized for fast display)
- `target_id` -- account ID of the affected user
- `target_login` -- login string
- `action` -- action type string (e.g. "IS_APPROVED", "IS_ADMIN")
- `old_value` -- previous value
- `new_value` -- new value
- `created_at` -- unix timestamp

Indexes: `target_id`, `actor_id`, `created_at`.

## Usage

### 1. Create app table

```php
class AdminActionLog extends FwAdminActionLog {
    protected string $tableName = 'ir_admin_action_log';
}
```

### 2. Write log entries

```php
AdminActionLog::get()->writeLog(
    actorId: $admin->id(),
    actorLogin: $admin->readParam('login'),
    targetId: $targetId,
    targetLogin: $targetLogin,
    action: 'IS_APPROVED',
    oldValue: '0',
    newValue: '1',
);
```

### 3. Query logs

```php
$logs = AdminActionLog::get()->selectAll(function (SelectInterface $q): void {
    $q->orderBy(['id DESC']);
    $q->limit(100);
});
```

## Extension Points
- Override `writeLog()` to add custom fields or side effects.
- Add custom indexes for specific query patterns.

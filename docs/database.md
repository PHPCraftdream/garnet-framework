# Database

Garnet's data layer is opinionated and small. Three primitives carry
nearly all of it:

- **`DbPool`** — the connection pool with async query semantics.
- **`DbTable`** — the abstract gateway every concrete table extends.
- **`Account`** — the Active Record / Unit-of-Work / EAV hybrid.

Plus `Session`, `Settings`, migrations, and a generic `EntityLog`.

For a directory tour see [`../Kernel/Db/README.md`](../Kernel/Db/README.md).

## Contents

- [DbPool — parallel async queries](#dbpool--parallel-async-queries)
- [DbTable — the gateway](#dbtable--the-gateway)
- [QueryBuilder integration](#querybuilder-integration)
- [Account — Active Record + EAV](#account--active-record--eav)
- [Session](#session)
- [Settings](#settings)
- [EntityLog](#entitylog)
- [Migrations](#migrations)
- [Conventions and don'ts](#conventions-and-donts)
- [Related](#related)

---

## DbPool — parallel async queries

The framework's hottest performance lever. Multiple SELECTs run
**concurrently** via `mysqli_poll`; the request waits the longest of
them, not their sum.

```php
use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;

Users::get()->selectAsync(['id' => $userId]);
Notifications::get()->countAsync(['user_id' => $userId, 'is_read' => 0]);
News::get()->selectAsync(['status' => 'published'], orderBy: 'id DESC', limit: 5);

DbPool::get()->pollFinishAll();

$user      = Users::get()->select(['id' => $userId])[0] ?? null;
$unread    = Notifications::get()->count(['user_id' => $userId, 'is_read' => 0]);
$news      = News::get()->select(['status' => 'published'], orderBy: 'id DESC', limit: 5);
```

The framework already calls `pollFinishAll()` at the end of every web
request, so for read-only data you can fire and forget — by the time
Twig reads `$news`, the pool has been drained.

Recipe: [`cookbook/parallel-mysql-queries.md`](cookbook/parallel-mysql-queries.md).

### Behind the scenes

```
DbPool
├── Link 1 → queryAsync(SQL₁) ─┐
├── Link 2 → queryAsync(SQL₂) ─┼──► mysqli_poll(read, errors, reject, 0)
└── Link 3 → queryAsync(SQL₃) ─┘     │
                                      │
                                      ▼
                                 reap_async_query()
                                 → bind result back to caller
```

`DbPool::get()->newLink()` opens additional links on demand. The pool
caps growth at a sane default to avoid hammering the DB.

## DbTable — the gateway

`Kernel/Db/Tables/DbTable.php` is the abstract every concrete table
extends. The base class exposes **the entire CRUD vocabulary**,
synchronous and async, in one stable surface area.

```php
namespace PHPCraftdream\MyApp\Common\Tables;

use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;

class Courses extends DbTable
{
    protected string $tableName = 'courses';   // bare name (no prefix)
    protected string $primaryKey = 'id';
}
```

Then use it:

```php
$id      = Courses::get()->insert(['title' => $t, 'cost' => $c]);
$row     = Courses::get()->selectOne(['id' => $id]);
$rows    = Courses::get()->select(['author_id' => $authorId], orderBy: 'created_at DESC', limit: 20);
$count   = Courses::get()->count(['is_published' => 1]);
$exists  = Courses::get()->exists(['slug' => $slug]);

Courses::get()->update(['id' => $id], ['title' => $newTitle]);
Courses::get()->delete(['id' => $id]);

// Async siblings:
Courses::get()->selectAsync([...]);
Courses::get()->countAsync([...]);
// then DbPool::get()->pollFinishAll();
```

### Why it's a black box

`DbTable` is intentionally **stable**. Its complexity is the feature —
it gives you a single, exhaustive API so apps never have to write
gateway code. Bug fixes are welcome; API changes are not.

### Prefixes

Tables live under a per-app prefix from `db.ini`:

```ini
prefix = "db_myapp"
```

Concrete subclasses set the **bare** table name (`'courses'`). The
gateway prepends the prefix and bundle infix automatically. Never
hard-code the full `'db_myapp_courses'` string — it breaks under
test-worker isolation.

## QueryBuilder integration

Need a join or a WHERE shape the simple selectors don't cover? Reach
for the per-table query builder:

```php
$select = Courses::get()
    ->newSelect()
    ->cols(['c.id', 'c.title', 'a.email AS author'])
    ->join('LEFT', 'accounts a', 'a.id = c.author_id')
    ->where('c.is_published = 1')
    ->orderBy(['c.created_at DESC'])
    ->limit(20);

$rows = Courses::get()->fetchAll($select);
```

The builder is `aura/sqlquery` under the hood. The DbTable layer is
mostly a sugar wrapper for the common 80% of queries.

## Account — Active Record + EAV

`Kernel/Db/Entity/Account/Account.php` is the authenticated-user
entity. Two patterns at once:

### Active Record + Unit of Work

```php
$account = Account::get($userId);
$account->setParam('email', $newEmail);
$account->setData('phone', $newPhone);    // EAV side
$account->flush();                         // persists only the dirty fields
```

`flush()` writes the dirty fields in one statement. `Account::get()`
caches; subsequent calls for the same id return the same object.

### EAV split

| Where | What lives there |
|---|---|
| `accounts` (core columns) | `email`, `password_hash`, `created_at`, `is_admin`, `is_approved`, … |
| `account_data` (EAV) | Anything app-specific: `time_zone`, `locale`, `phone`, `address`, `bio`, custom business attrs. |

```php
$tz = $account->readParam('time_zone');           // first the data EAV, falls back to params
$account->saveData('preferred_locale', 'ru');
```

Why EAV: business apps want to keep adding attributes without
migrations. The split keeps the core table narrow (cheap selects) and
the data table flexible (cheap schema changes).

### Subclass it in your app

```php
namespace PHPCraftdream\MyApp\Common\Entity\Account;

use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account as Base;

class Account extends Base
{
    public function isExpert(): bool   { return $this->readParam('type') === 'expert'; }
    public function avatarUrl(): string { /* … */ }
}
```

The framework's `Account` only knows generic flags (`isAdmin`,
`isOwner`, …). Business roles (`expert`, `customer`) go in the app's
subclass.

## Session

`Kernel/Db/Entity/Session/Session.php` — table-backed session, opened
once at the top of every request (`Session::readFromServer()`),
written back lazily on shutdown.

- One row in `sessions` keyed by an opaque cookie value.
- Session payload is `JSON_UNESCAPED_UNICODE`.
- Each session carries a CSRF token that's checked on every POST.
- Cookies are `SameSite=Lax` so magic-link clicks from webmail land
  authenticated.

You almost never touch `Session` directly — the framework wires it
into `GlobalReqParams` and the Auth middleware.

## Settings

`Kernel/Db/Entity/Settings/Settings.php` — a cached key-value store
backed by the `settings` table. Used for slow-changing runtime
configuration (feature flags, content tweaks, brand colour).

```php
Settings::get()->set('homepage.hero_title', 'Hello world');
$title = Settings::get()->get('homepage.hero_title', 'Default');
```

Bulk writes flush together. Reads hit an in-process cache.

## EntityLog

`Kernel/Db/Entity/Log/` — a generic audit table the framework writes
to whenever it changes an account or a setting. The
[`EntityHistory`](../Bundle/Modules/EntityHistory/README.md) bundle
covers app-level usage; the kernel piece records framework-level
actions automatically.

## Migrations

Migrations live **per app**:

```
Apps/<App>/Migrations/Items/
├── M_0001.php
├── M_0002.php
├── M_0003.php
└── …
```

Each one is a `BaseMigration` subclass with `up()` and `down()`:

```php
namespace PHPCraftdream\MyApp\Migrations\Items;

use PHPCraftdream\Garnet\Kernel\Db\Migration\BaseMigration;

class M_0007 extends BaseMigration
{
    public function up(): void
    {
        DbPool::get()->exec(
            "ALTER TABLE {$this->t('bookings')} ADD COLUMN kind VARCHAR(16) NOT NULL DEFAULT 'cancel'"
        );
    }

    public function down(): void
    {
        DbPool::get()->exec("ALTER TABLE {$this->t('bookings')} DROP COLUMN kind");
    }
}
```

`$this->t('bookings')` resolves to `db_myapp_bookings` — never
hard-code the prefix.

Commands:

```bash
php garnet migrate:status      # current vs target version
php garnet migration           # apply pending migrations
```

The runner records applied versions in a `migrations` table; running
twice is safe.

## Conventions and don'ts

- **Async by default for reads.** `selectAsync` + `pollFinishAll` is
  the right shape for any controller that needs more than one
  independent SELECT.
- **`DbTable` for everything, raw SQL only for migrations + dev
  scripts.** No `$pdo->prepare(...)` in business code.
- **Never write a prefix.** Use `DbTable::tableName()` /
  `BaseMigration::t()` so test-worker isolation can swap the prefix
  at runtime.
- **`Account` is the auth identity, not a god object.** App-side
  subclass for role helpers; per-feature services for behaviour.
- **Settings is for human-changeable values.** Build-time config goes
  in `.ini`; per-request data goes in `GlobalReqParams`.
- **Don't `UPDATE` ledger / log tables.** Append a reversing row.

## Related

- [`../Kernel/Db/README.md`](../Kernel/Db/README.md) — directory tour.
- [`architecture.md`](architecture.md) — async-DB diagram in context.
- [`cookbook/parallel-mysql-queries.md`](cookbook/parallel-mysql-queries.md) —
  recipe.
- [`known-issues.md`](known-issues.md) — `insertBatch` on MySQLi gotcha,
  `Account::name()` doesn't exist.
- [`../Bundle/Modules/Balance/README.md`](../Bundle/Modules/Balance/README.md) —
  immutable ledger pattern.
- [`../Bundle/Modules/EntityHistory/README.md`](../Bundle/Modules/EntityHistory/README.md) —
  per-entity audit.

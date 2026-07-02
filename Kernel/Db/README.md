# Kernel / Db

The data layer. Async MySQL through `mysqli_poll`, a stable
`DbTable` gateway, Active-Record `Account`, sessions and settings,
plus migrations.

## What's here

| Subdir | What it does |
|---|---|
| `Link/` | `DbPool`, `DbMySQLi`, the low-level connection wrapper. Opens `MYSQLI_ASYNC` connections and drains them with `mysqli_poll`. |
| `Tables/` | `DbTable` — the abstract gateway every concrete table extends. **Intentionally stable**: rich, exhaustive CRUD API (sync + async siblings for every operation). Don't change it. |
| `Query/` | Query builders used by `DbTable` (select, insert, update, delete, count, exists). |
| `Entity/` | Active-Record-style entities that sit on top of `DbTable`. `Account`, `Session`, `Settings`, `EntityLog` — see below. |

Plus `IntegrationTests-README.md` — how the integration suite under
`Spec/` is organised.

## Headline pieces

### `DbPool` — parallel reads

`DbPool::get()` returns the singleton. Every `DbTable::*Async` call
hands the query to the pool; `DbPool::pollFinishAll()` blocks until
every in-flight query finishes. Net effect: a request that issues N
independent reads pays `max(query_time)` instead of `sum(query_time)`.

See [`../../docs/cookbook/parallel-mysql-queries.md`](../../docs/cookbook/parallel-mysql-queries.md)
for the day-to-day pattern.

### `DbTable` — the gateway

Concrete tables (e.g. `FwAccounts extends DbTable`) declare a name,
schema, and primary key, and inherit the entire CRUD vocabulary: `select`,
`selectOne`, `count`, `exists`, `insert`, `update`, `delete`, plus an
`*Async` variant of each. Schema diffing drives `migration`.

`DbTable` is **a permanent black box**. Bug fixes are welcome; API
changes are not.

### `Account` — Active Record + EAV

`Entity/Account/Account.php` represents an authenticated user. Two
patterns at once:

- **Unit of Work** — `flush()` writes only the modified fields.
- **EAV** — core columns live in `db_accounts`; everything else
  (`time_zone`, `locale`, ad-hoc business attrs) lands in
  `db_account_data` as `(account_id, key, value)` triples.

The framework Account handles auth state, flags (`IS_ADMIN`,
`IS_OWNER`, …) and generic hooks. Business roles like `user`, `expert`,
`booking-side` belong in `<App>/Common/Entity/Account/Account.php`,
which extends the framework class.

### `Session`, `Settings`

Backed by `db_session` and `db_settings`. Session is request-scoped
and flushed on shutdown; Settings is a cached key-value store.

## Migrations

Migrations live in **each app** under `<App>/Migrations/Items/M_NNNN.php`.
The framework provides:

- The abstract `BaseMigration` they extend.
- `Kernel/Db/Migration*` — the runner driven by `php garnet migration`.
- `MigrationStatus` reporting via `php garnet migrate:status`.

## Async DB rules

- Don't fire `selectAsync` inside a Twig template — the rendering loop
  won't drain the pool. Prepare data in the controller.
- `pollFinishAll()` is idempotent — cheap to call defensively.
- Two `*Async` calls with identical args fire one query and share the
  result (the pool keys by SQL hash).

## Related

- [`../README.md`](../README.md) — kernel overview.
- [`../../docs/database.md`](../../docs/database.md) — full data-layer reference.
- [`../../docs/cookbook/parallel-mysql-queries.md`](../../docs/cookbook/parallel-mysql-queries.md) — recipe.

---

↑ Back to [Kernel](../README.md)

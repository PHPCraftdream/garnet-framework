# Entity History

A drop-in audit log: "who did what to this entity, and what changed."
Every entity in the app can carry a history table — the service
ensures the schema, records diffs, paginates the timeline.

## What's here

| File / subdir | What it does |
|---|---|
| `EntityHistoryService.php` | The single public entry point. `record()`, `list()`, `paginate()`. |
| `Tables/FwEntityHistory.php` | Abstract `DbTable` for the history store. Apps subclass it once per entity-family. |
| `SystemSettingsHistory.php` | Concrete subclass the framework uses to log changes to its own `SystemSettings` page. Doubles as a reference implementation. |
| `Controllers/` | Reusable list + detail-modal endpoints; the frontend has a matching `EntityHistoryDrawer` island. |
| `Spec/` | Kahlan specs. |

## Recording a change

```php
use PHPCraftdream\MyApp\Common\Tables\EntityHistory;
use PHPCraftdream\Garnet\Bundle\Modules\EntityHistory\EntityHistoryService;

EntityHistoryService::record(
    table:      EntityHistory::class,
    entityType: 'static_page',
    entityId:   $pageId,
    event:      'update',
    diff:       ['title' => ['Old title', 'New title']],
);
```

What happens:

1. The service lazily ensures the table exists (`SHOW TABLES … LIKE …`
   on first call per process; idempotent). **No migration required.**
2. A row is appended with `entity_type`, `entity_id`, `event`,
   `diff_json`, `account_id` (from the current session), `created_at`.
3. The current account is captured automatically — you don't pass the
   actor.

## Reading the timeline

```php
$rows = EntityHistoryService::paginate(
    table:      EntityHistory::class,
    entityType: 'static_page',
    entityId:   $pageId,
    page:       1,
);
```

Pagination uses `DEFAULT_PAGE_SIZE`. The returned rows include the
joined `account_id` → `login` lookup so the UI doesn't need a second
round-trip.

## Frontend components

The framework ships ready-to-use React islands under
`Bundle/Front/Common/Components/EntityHistory/`:

- `<EntityHistoryTable rows={…}>` — the list grid.
- `<EntityHistoryDetailModal>` — JSON-diff viewer for one row.
- `<EntityHistoryDrawer>` — a side-drawer wrapper for in-page use.
- `<EntityHistoryButton>` — a one-liner button that opens the drawer.

Apps drop `<EntityHistoryButton entityType="…" entityId={…} />` next to
the entity and get a full audit UI for free.

## When to subclass

One subclass per **entity family** — usually per app, sometimes per
bundle. The subclass's `$tableName` decides where rows land. The
framework's `SystemSettingsHistory` is the canonical reference.

## Relationship to other audit-shaped modules

| Module | Shape |
|---|---|
| **EntityHistory** | Per-entity timeline. "Page #42 was edited 5 times; here are the diffs." |
| **Logging** (`Logging/`) | App-level event log. "User #7 promoted user #42 to expert at 10:21." |
| **Balance** (`Balance/`) | Immutable financial ledger, never updated, never deleted. |

You'll often use all three in the same app — they answer different
questions and live at different layers.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../Logging/README.md`](../Logging/README.md) — sibling audit shape.
- [`../SystemSettings/README.md`](../SystemSettings/README.md) — uses this module for its own change log.

---

↑ Back to [Bundle / Modules](../../README.md)

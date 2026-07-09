# Logging

App-level activity log with channels, an admin viewer, and special
sub-loggers for mail and request traces.

## What's here

| Subdir | What it does |
|---|---|
| `Admin/` | Controllers + React island for the in-app log viewer (`/admin/logs/`). |
| `Mail/` | The `MailLog` channel — every queued email gets a row with subject, recipient, status. |
| `Request/` | Optional request-trace logger (URL, status, duration, controller). |
| `Viewer/` | Shared React components used by the admin pages. |
| `Spec/` | Kahlan specs for log writers and filters. |

## The underlying `Logger`

Logging is two-layered:

1. **`Kernel/Io/Logs/Logger`** — the low-level file-journal writer.
   Three built-in channels:
   - `SYSTEM_LOGGER` — framework noise (benchmark, OPcache reset).
   - `ERROR_LOGGER` — uncaught exceptions.
   - `APP_LOGGER` — anything the app emits via `Log::write(...)`.
2. **This bundle** — adds a DB-backed channel (`db_log_entries`) and an
   admin UI for browsing it. Sits alongside the file journal, not
   replacing it.

## Write a log line

```php
use PHPCraftdream\Garnet\Bundle\Modules\Logging\LogWriter;

LogWriter::write(
    category: 'user',
    event:    'set_type',
    refId:    $user->id(),
    details:  ['from' => 'user', 'to' => 'expert', 'by' => $admin->id()],
);
```

- `category` — high-level grouping (e.g. `user`, `booking`, `auth`).
- `event` — verb-like identifier of the operation.
- `refId` — the primary id of the affected entity (so the admin viewer
  can link to it).
- `details` — free-form JSON-safe payload.

## Admin viewer

`/admin/logs/` shows a paginated grid with filters (category, event,
free-text, date range), a per-row detail modal, and links to the
referenced entity. Behaviour:

- Pagination uses the framework-wide `DEFAULT_PAGE_SIZE`.
- Filters persist in the URL so admins can share links.
- Details modal renders the JSON via a tree view, not a JSON dump.

## Special sub-loggers

- **MailLog** (`Mail/`) — every `FwEmailQueueService::queue()` writes
  an entry as side-effect. The viewer lets the admin see what went out
  and to whom, with the rendered body on demand.
- **RequestLog** (`Request/`) — opt-in. Logs URL, status, duration,
  controller, account id. Useful for production load profiling; expensive
  to leave on permanently.

## Don't

- Don't log secrets (tokens, passwords, full session cookies). The
  viewer renders details verbatim.
- Don't use `LogWriter` from inside a hot path; the write is sync to the
  primary DB. If you need bursty logging, queue to the file journal
  via `Logger::get(...)` instead.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../../../Kernel/Io/Logs/Logger.php`](../../../Kernel/Io/Logs/Logger.php) — the file-journal layer.
- [`../EntityHistory/README.md`](../EntityHistory/README.md) — per-entity audit log (complementary, not a replacement).

---

↑ Back to [Bundle / Modules](../../README.md)

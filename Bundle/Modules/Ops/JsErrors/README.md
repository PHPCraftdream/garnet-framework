# JS Errors

Public endpoint that receives uncaught browser JS errors and stores
them deduped by signature. The browser's `window.onerror` ships the
report; the framework keeps the table compact via a rolling dedupe
window.

## What's here

| Subdir | What it does |
|---|---|
| `Controllers/` | The single public POST endpoint. Accepts the report, dedupes by signature, writes. |
| `Tables/FwJsErrors.php` | The error store: `signature`, `message`, `file`, `line`, `column`, `stack`, `user_agent`, `url`, `account_id`, `count`, `first_seen_at`, `last_seen_at`. |
| `Spec/` | Kahlan specs for dedupe + anti-storm. |

## Wire it up

```php
// MyApp.php → init()
JsErrorsController::setTableClass(MyApp\Common\Tables\JsErrors::class);

// runWebApp()
$router->add('/js-errors/~report', [JsErrorsController::class, [], '~report']);
```

The endpoint is intentionally **CSRF-exempt** — the report can fire
before page hydration finishes, when no CSRF token has been seen.

## Browser-side reporter

A small JS island registered in the framework's main bundle calls the
endpoint when `window.onerror` or `unhandledrejection` fires. The
client batches deduplicate within a session (don't report the same
error 50 times during a render loop).

## Dedupe semantics

Signature: `sha256(message | file | line)`. On insert:

```
1. Compute signature.
2. If a row with that signature exists in the table:
   a. If last_seen_at is within ANTI_STORM_WINDOW_SEC → drop the
      report on the floor (silent throttle).
   b. Otherwise UPDATE count = count + 1, last_seen_at = NOW().
3. If no row exists → INSERT.
```

This gives you a "top errors by frequency" view in the admin grid
without the table blowing up when one page-reload-loop ships a million
of the same error.

## Admin view

The [Logging](../Logging/README.md) bundle's admin viewer surfaces JS
errors as one of its channels, with filters by URL, user-agent fragment,
and date range. Click-through to the full stack trace.

## Don't

- **Don't log secrets in error messages.** Anything you `throw new
  Error(...)` lands in the table verbatim. Sanitise message strings.
- **Don't use this endpoint for performance traces.** It's for
  uncaught errors. Trace data goes to a different store (or to a
  third-party APM).
- **Don't enable verbose source maps in production without thinking.**
  The stack column will include framework-internal paths if the build
  ships them. Fine for the admin viewer; bad if the data leaves the
  server.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../Logging/README.md`](../Logging/README.md) — admin viewer for the JS-error channel.

---

↑ Back to [Bundle / Modules](../../README.md)

# Kernel / Exceptions

The framework's exception hierarchy.

## Headline classes

- `CommonException` — root of the hierarchy. Every framework-raised
  exception extends it (directly or transitively).
- `IniConfigException` — config file missing / malformed / accessed
  before `defineXxxIni()`.
- `DbException` — surfaced by `DbPool` / `DbTable` for connection
  failures, query errors, schema-drift detection.
- `RouterException` — duplicate route, unknown method, malformed
  `~method` suffix.
- `AuthException` — magic-link state machine surfacing an error to the
  client (invalid code, expired session, rate-limited).
- Smaller domain-specific ones (`SessionException`,
  `FileUploadException`, …) under the same root.

## Conventions

- **Errors are exceptions.** No silent fallbacks, no `false`-returning
  APIs. Let `ErrorCatcher` and `FrameworkController::internal_error_500`
  catch them at the request boundary.
- Subclass `CommonException` (or one of its children), don't extend
  `\Exception` directly.
- Constructor: `new FooException(string $message, ?Throwable $previous = null)`.
  Codes are not used.
- Include enough context in the message that a log line is
  self-describing — IDs, paths, the operation that failed. The stack
  trace will tell the developer where; the message tells them what.

## Catching at boundaries

The two outermost catches are:

- `IoRunWeb::run` → renders an HTML error page via
  `FrameworkController::internal_error_500`.
- `IoRunConsole::run` → prints a one-line red error + a `--debug`
  hint, exits non-zero.

Bundle code should not blanket-catch and swallow — re-raise if you
don't know how to handle.

## Related

- [`../README.md`](../README.md) — kernel overview.

---

↑ Back to [Kernel](../README.md)

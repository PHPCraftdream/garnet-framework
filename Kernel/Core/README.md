# Kernel / Core

Cross-cutting primitives. Nothing here knows about the database or the
outside world — these are the value objects, helpers and base classes
the rest of the kernel composes.

## What's here

| Subdir / file | What it does |
|---|---|
| `AppInit/` | `BaseAppInit` — the contract every app's main class extends. Boots bundles, registers routes, runs middleware chains. |
| `BaseTest/` | Common testing helpers reused by `Spec/` directories. |
| `Benchmark/` | `BenchmarkLog` — the wall-time tracer that emits `benchmark` events when a request crosses the slow threshold. |
| `Env/` | `Env` — dev / prod / test detection plus directory probes (`Env::isDevDir()`). |
| `Event/` | Lightweight pub/sub. Bundles emit/listen without touching each other directly. |
| `FrameworkController.php` | Base class every controller extends. Owns `renderTwig`, `renderIsland`, `json`, `internal_error_500` and the request-state plumbing. |
| `GlobalReqParams/` | Typed value object built from `$_SERVER`/`$_GET`/`$_POST`/`$_COOKIE`/`$_FILES`. Passed by reference through the request lifecycle. |
| `GlobalVars/` | A tiny key-value registry for cross-cutting flags (`phpRunCmd`, `ErrorCatcherTestEnabled`). Used by tests and bootstrap. |
| `HCalendar/` | Hebrew calendar helpers — date conversions, chag detection. Used by date-aware slot filters. |
| `Tools/` | Static helpers — `FsTools`, `DateTools`, string/array utilities. |
| `Spec/` | Kahlan specs for everything above. |

## Conventions

- Everything here is **app-agnostic**. No business types, no
  brand-specific behaviour. If you find yourself wanting to add one,
  expose an extension point and override it inside the app.
- Tests for files in `Core/Foo/Bar.php` live next to the code at
  `Core/Foo/Spec/BarSpec.php`. Run via `composer test:kernel`.

## Related

- [`../README.md`](../README.md) — kernel overview.
- [`../Db/README.md`](../Db/README.md) — the data layer that consumes these primitives.
- [`../Io/README.md`](../Io/README.md) — the IO layer that does too.
- [`../../docs/architecture.md`](../../docs/architecture.md) — request lifecycle in depth.

---

↑ Back to [Kernel](../README.md)

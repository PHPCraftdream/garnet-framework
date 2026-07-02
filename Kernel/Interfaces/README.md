# Kernel / Interfaces

The framework's public contracts. Anything an app or a bundle is
expected to implement lives here.

## What's here

Browse the directory — every file is a small interface or a typed
contract:

- `IEntityConfig` — declarative entity specification driving the
  reusable CRUD controllers (`FwAccountsController`, etc.).
- `IIniConfig` — the typed-reader contract `IniConfig::db()`,
  `IniConfig::app()` etc. return.
- `IBundleInit` — what every bundle's main class must look like
  (`init()`, `namespace()`, `bundleDir()`).
- `II18n` — the i18n runtime contract; both PHP-side `Tr` and the
  generated TS classes satisfy it.
- `IRequestState`, `IRouter`, `ILogger`, … — the rest of the small,
  composable contracts the runners depend on.

## Why interfaces are first-class

The framework leans on `IEntityConfig` / `IBundleInit` heavily: the
Template Method pattern makes the reusable controllers possible
because the **specification** is a class implementing an interface,
not a static array. Apps can swap implementations per-tenant /
per-feature without touching the controller.

## Adding an interface

- Keep them small. One responsibility, two-to-five methods.
- Document the lifecycle if there is one (when does the framework
  call this method, and in what order with the others).
- If multiple implementations will live side by side, add a `Spec/`
  next door that exercises the contract abstractly — that's how
  `IEntityConfig` is locked down.

## Related

- [`../README.md`](../README.md) — kernel overview.

---

↑ Back to [Kernel](../README.md)

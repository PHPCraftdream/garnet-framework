# Kernel

The framework's engine. Everything that has to be there before any
bundle can run lives here. No business logic, no concrete app concepts.

> **Strict rule.** Code in `Kernel/` must not know about a specific
> application (no `MyApp`, no business roles like `expert`, `booking`,
> brand names). If you need an app-specific behaviour, expose an
> extension point and override it in the app.

## What's here

| Subdir | What it covers |
|---|---|
| [`Core/`](Core/README.md) | Cross-cutting primitives: env detection, benchmarks, events, global request params, the `I18n` interface, the typed framework controller base. |
| [`Db/`](Db/README.md) | Async MySQL: `DbPool`, `DbTable`, query builders, `Account` Active Record, `Session`, `Settings`, migrations, entity logging. |
| [`Io/`](Io/README.md) | Everything that touches the outside world: router, CLI, Twig, INI config, logger, cache, emitter, mailer, file uploads, cookies. |
| [`Exceptions/`](Exceptions/README.md) | The framework's exception hierarchy. |
| [`Interfaces/`](Interfaces/README.md) | Public interfaces other parts implement (e.g. `IBundleInit`, `IEntityConfig`). |

## Boot order

`run_web.php` and `run_cmd.php` set the stage, then call the app's
`*Init` class. That class iterates `bundles()` and lets each register
its routes, services and templates. Inside the kernel itself the order
is:

1. **Composer autoload** — loads vendor + framework + app namespaces.
2. **`ErrorCatcher::init`** — installs fatal-error and exception hooks.
3. **`GlobalReqParams::from`** — captures `$_SERVER`, `$_GET`, `$_POST`,
   `$_COOKIE`, `$_FILES` into a typed value object that's passed by
   reference through the rest of the request.
4. **`DbPool::newLink`** — opens the async MySQL connection (only if the
   DB is enabled in `db.ini`).
5. **App `webInit()`** — registers bundles, routes, middlewares.
6. **`IoRunWeb::run`** — runs middleware chain → router → controller →
   emitter.

## Don't

- Don't add app-specific constants, methods or properties to any class
  here. Subclass the framework class inside the app instead — see
  `Bundle/Modules/.../Fw*.php` for the convention.
- Don't write HTML in PHP — use Twig.
- Don't change `DbTable` without a very good reason. It's intentionally
  a stable black box.

## Related

- [`../Bundle/README.md`](../Bundle/README.md) — reusable modules built on top of this kernel.
- [`../docs/architecture.md`](../docs/architecture.md) — full layered overview.
- [`../docs/database.md`](../docs/database.md) — the data layer in depth.
- [`../docs/io.md`](../docs/io.md) — the IO subsystem in depth.

---

↑ Back to [Framework root](../README.md)

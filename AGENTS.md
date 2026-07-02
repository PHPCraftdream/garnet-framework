# Agent onboarding — Garnet Framework

You're an AI agent (or a new developer) starting work on **Garnet
Framework**. This file is your map. Read it before touching files;
prefer it over guessing from filenames.

## TL;DR

- **Garnet** is a high-performance, opinionated PHP 8 web framework. App
  authors compose it from `Bundle/` modules, register routes in code, and
  let the framework handle routing, async DB, codegen-typed assets, auth,
  Twig templating, and React-island hydration.
- The framework lives in **two top-level trees**: `Kernel/` (the engine —
  router, DB, IO, CLI) and `Bundle/` (reusable modules — auth, CRUD,
  i18n, files, comments, support, …). Apps live in their own repo and
  pull this package via Composer.
- Source of truth is **code**, not config. Routes are PHP arrays; entity
  configs are PHP classes implementing `IEntityConfig`; field validation
  rules live in PHP controllers and are auto-converted to Zod for the
  React forms.

## Namespace layout

```
PHPCraftdream\Garnet\
├── Kernel\        → ./Kernel/      (engine: no business logic)
│   ├── Core       → benchmarks, env, events, i18n interface, globals
│   ├── Db         → DbPool, DbTable, Account, Session, Settings, migrations
│   ├── Io         → Router, IniConfig, Twig, Logger, Cache, Emitter, CLI
│   └── Exceptions
└── Bundle\        → ./Bundle/      (reusable modules; opt-in)
    ├── BaseBundleInit  → contract every bundle implements
    ├── Modules\        → Auth, Balance, Comments, Cron, Dashboard,
    │                     Files, IM, Logs, Notifications, StaticPages,
    │                     Support, SystemSettings, Tickets, Users, …
    ├── Front\          → shared frontend (React islands, hooks, CSS,
    │                     utilities) consumed by every app
    ├── TwigTemplates\  → Layout/, Email/, Components/, …
    ├── Controllers\    → reusable CRUD controllers (FwAccountsController, …)
    └── Utils, Filters, Modules…
```

**Strict separation.** Code under `Kernel/` and `Bundle/` must not know
about any concrete app (no `MyApp`, no business roles like `expert`,
`booking`). Business code extends framework classes inside the app
(`Apps/<App>/`) — see `Bundle/Modules/.../Fw*.php` for the abstract /
extension-point convention.

## Where things live

| You want to… | Look at |
|---|---|
| Add a CLI command | `Kernel/Io/GarnetCli/Garnet*Command.php` + `Kernel/Io/GarnetCli/GarnetRunner.php` |
| Extend the kernel HTTP pipeline | `Kernel/Core/FrameworkController.php`, `Kernel/Io/IoRun/IoRunWeb.php` |
| Add or change a DB primitive | `Kernel/Db/Tables/DbTable.php` (don't), `Kernel/Db/Link/DbPool.php`, `Kernel/Db/Entity/Account/Account.php` |
| Add a bundle (auth, comments, …) | `Bundle/Modules/<Name>/` — model on an existing module |
| Add a Twig template | `Bundle/TwigTemplates/<scope>/<name>.twig`; register through your bundle's `BaseBundleInit::init()` |
| Add a React island | `Bundle/Front/Islands/<Name>/` for framework-level, or in the app for business |
| Touch the asset bridge | `Kernel/Io/GarnetCli/GarnetBuildCommand.php`, `FrontBuilder/build/PhpClassGeneratorPlugin.ts` |
| Add specs | `<area>/Spec/*Spec.php`, kahlan-style (`describe`/`it`/`expect`) |

## Local development conventions

### PHP

- **PSR-4, `declare(strict_types=1)` at the top of every file.**
- **Always use imports** (`use Foo\Bar\Baz;`) — never `\Full\Namespace\Class::method()` inline.
- **Async DB by default.** Reads that don't need to block should use `selectAsync` / `pollFinishAll`. The `DbTable` API exposes async siblings for every CRUD shape.
- **HTML never lives in PHP.** Markup belongs in Twig (`Bundle/TwigTemplates/`). PHP prepares typed arrays; Twig auto-escapes by default.
- **Errors are exceptions.** No silent fallbacks — let `ErrorCatcher` and `FrameworkController::internal_error_500` handle the boundary.

### Frontend (under `Bundle/Front/`)

- **No TypeScript errors.** Use type guards, handle `undefined`.
- **React islands lazy-load.** `createIsland({lazy: () => import(...)})`. No sync imports in entry points. Wrap every island in `ErrorBoundary`.
- **Single source of validation truth = PHP.** Frontend converts PHP `fieldsInfo` to Zod via `Bundle/Front/Common/Utils/zodFromFieldsInfo.ts`. Never rewrite validators by hand.
- **Time is `unixtime` everywhere.** Display via `formatTs` from `@common/Utils/DateUtils` with the user's timezone (`window.__GARNET_USER__.timezone`).

### Tests (kahlan, under `<area>/Spec/`)

- Kernel specs (`Kernel/**/Spec/`) are DB-free. Run via `composer test:kernel`.
- Bundle specs (`Bundle/**/Spec/`) may touch MySQL. Run via `composer test:bundle` (needs `localhost:3306` matching `TestsInit/TestConfig/db.ini`).
- CI runs both behind `composer test` (matrix on PHP 8.1/8.2/8.3).

## Common operations

| Operation | Command |
|---|---|
| Install dependencies | `composer install` |
| Static analysis | `composer phpstan` |
| Code style check / fix | `composer cs:check` / `composer cs:fix` |
| Run all specs | `composer test` |
| Run kernel-only specs | `composer test:kernel` |
| Run bundle specs (needs MySQL) | `composer test:bundle` |
| TS typecheck | `cd FrontBuilder && npm run typecheck` |
| Lint everything before pushing | `composer ci` |

## Strict separation: framework vs business

This rule keeps the framework reusable. Every time you reach for MyApp
(or any concrete app) inside `Kernel/` or `Bundle/`, **stop**. Move the
specific bit into the app's tree (`Apps/<App>/Common/…`) and extend the
framework's abstract / `Fw*` class in the app.

Known leakage we accept for v0.x and plan to generalise:

- `Bundle/Modules/Balance/Tables/FwBalanceLedger.php` — string enum
  values still spell `booking_invoice`, `booking_payment`,
  `booking_refund`. v1.0 will rename to generic `tx_*`.
- `Bundle/Modules/SystemSettings/FwAppSettings.php` —
  `cancellation_penalty_percent` is a booking-domain setting; v1.0 will
  move it to the app layer.

If you're adding new framework code: **don't add more.** Use a generic
name or an extension point.

## Don't

- Don't add business terms (`expert`, `booking`, `slot`, brand names) to
  files under `Kernel/` or `Bundle/`.
- Don't write HTML strings inside PHP — use Twig.
- Don't use `window.confirm` / `alert` / `prompt` — use `useConfirm()` and
  `ConfirmModal`.
- Don't write raw SQL outside dev-only scripts; use `DbTable` / QueryBuilder.
- Don't bump dependency or framework versions without an explicit request.
- Don't `git commit` / `git push` without an explicit request.

## Pointers to deeper docs

- `README.md` — the user-facing pitch and limitations.
- `CONTRIBUTING.md` — how to send a PR.
- `docs/architecture.md` — layered architecture, request lifecycle, async DB.
- `docs/bundle.md` — how bundles are written.
- `docs/cli.md` — every CLI command.
- `docs/database.md` — DbPool, DbTable, async patterns.
- `docs/frontend.md` — React islands, codegen, asset bridge.
- `docs/i18n.md` — i18n keys, `%s` interpolation rules.
- `docs/dev-workflow.md` — develop framework + app side by side with a path repo.
- `docs/quickstart.md` — scaffold a new app from the template.
- `docs/known-issues.md` — sharp edges to be aware of.

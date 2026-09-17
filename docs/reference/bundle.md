# Bundles

This document covers two things that share the word "bundle":

1. **The framework's `Bundle/` tree** — the reusable modules every app
   inherits (auth, comments, cron, email, …).
2. **The bundle contract for apps** — how a bundle inside an app
   registers itself, ships its assets, and plugs into the request
   lifecycle.

For the **deploy bundle** (`php garnet bundle`) see
[`deploy.md`](deploy.md) — same word, different thing.

## Contents

- [What's in `Framework/Bundle/`?](#whats-in-frameworkbundle)
- [The `BaseBundleInit` contract](#the-basebundleinit-contract)
- [Reusable controllers](#reusable-controllers)
- [Module reference](#module-reference)
- [Writing your own bundle](#writing-your-own-bundle)
- [Related](#related)

---

## What's in `Framework/Bundle/`?

```
Bundle/
├── Modules/        ← Self-contained feature modules. Each is a directory
│                     with its own README.md. See "Module reference" below.
│   ├── Auth/
│   ├── Balance/
│   ├── Comments/
│   ├── Cron/
│   ├── Dashboard/
│   ├── Email/
│   ├── EntityHistory/
│   ├── Idempotency/
│   ├── Invite/
│   ├── JsErrors/
│   ├── Logging/
│   ├── Messaging/
│   ├── News/
│   ├── StaticPages/
│   ├── Support/
│   └── SystemSettings/
│
├── Front/          ← Shared frontend (React + CSS) — see Bundle/Front/README.md
├── TwigTemplates/  ← Shared layouts, emails, components — see Bundle/TwigTemplates/README.md
├── Controllers/    ← Reusable abstract controllers (FwAccountsController, …)
├── Middlewares/    ← Cross-cutting middlewares (CSRF, WorkerScope, …)
├── Utils/          ← HTML layout helper, prefetch logic, …
├── Filters/        ← Twig filter extensions
├── I18n/           ← Framework-level translations (Common_*, Action_*, Pagination_*)
└── Framework.php   ← Bundle entry-point class extending BaseBundleInit
```

Top-level summary in [`../Bundle/README.md`](../Bundle/README.md) —
one paragraph per module with links to the per-module READMEs.

## The `BaseBundleInit` contract

A bundle is any class that extends `BaseBundleInit`. The contract is
deliberately small:

```php
namespace PHPCraftdream\MyApp\MyBundle;

use PHPCraftdream\Garnet\Bundle\BaseBundleInit;

class MyBundle extends BaseBundleInit
{
    public function namespace(): string  { return __NAMESPACE__; }
    public function bundleDir(): string  { return __DIR__; }

    public function init(): void
    {
        parent::init();
        // Optional: register services, event listeners, etc.
    }
}
```

What `parent::init()` does automatically when invoked from the app's
main class:

| Action | What it does |
|---|---|
| **Twig path registration** | If `bundleDir()/TwigTemplates/` exists, it's added to the Twig loader so `Twig::get()->render('Foo/bar.twig', …)` resolves to your bundle. |
| **Asset registration** | If `bundleDir()/Front/` exists, its entry points become rspack entries during the build. |
| **i18n registration** | If `bundleDir()/I18n/*I18nData{Ru,En}.php` exists, the keys are merged into the framework's translation map. |
| **Migration discovery** | If `bundleDir()/Migrations/Items/M_*.php` exists, the migration runner picks them up. |

Apps register their bundles in the main app class:

```php
// MyApp.php → bundles()
protected function bundles(): array
{
    return [
        // The framework auto-registers Framework/Bundle/Framework.php
        // (the central FrameworkBundle).
        \PHPCraftdream\MyApp\Foreground\ForegroundBundle::class,
        \PHPCraftdream\MyApp\Dashboard\DashboardBundle::class,
        \PHPCraftdream\MyApp\MyBundle\MyBundle::class,
    ];
}
```

The framework iterates that array on boot and calls `init()` on each.

## Reusable controllers

`Bundle/Controllers/` holds **abstract** controllers that implement
common patterns. The app provides a concrete subclass that pins the
entity, routes, and role gate.

| Class | Pattern |
|---|---|
| `FwAccountsController` | CRUD grid + edit modal for `accounts` table. Concrete subclasses override `entityConfig()` to return an `IEntityConfig`. |
| `FwCmsController` | CMS-style content listing on top of [StaticPages](../Bundle/Modules/StaticPages/README.md). |

Each one uses the Template Method pattern: the controller declares the
skeleton (list → detail → save → delete), and the subclass plugs in
the specifics via an `IEntityConfig` implementation.

### `IEntityConfig` in one paragraph

A class that describes a data entity declaratively:

- Which fields to show on the grid, in the form, in API responses.
- Validation rules for each field (same `fieldsInfo` shape that
  becomes Zod on the frontend — see
  [`cookbook/add-validation-rules.md`](cookbook/add-validation-rules.md)).
- Field types (`'string'`, `'photo'`, `'map'`, …) the grid renderer
  understands.
- Role gates (`isAllowed`, `canEdit`, `canDelete`).

The whole point is that the heavy CRUD plumbing (selects, paging,
sorting, saving, validation) lives in the abstract controller and
isn't repeated per entity.

The same split shows up at the module level: some `Fw*` modules (e.g.
Auth) ship a reference frontend under `Bundle/Front/`, while others
(e.g. Support, Messaging, News) are backend-only extension points —
controllers, tables, and validation with no bundled UI or wired
routes — that the consuming app completes.

## Module reference

Per-module READMEs cover scope, public API, conventions, and "don't"
lists. Browse the [Bundle index](../Bundle/README.md) or jump:

| Module | Capsule |
|---|---|
| [Auth](../Bundle/Modules/Auth/README.md) | Passwordless email magic-link auth. CSRF + brute-force mitigations. |
| [Balance](../Bundle/Modules/Balance/README.md) | Immutable transaction ledger + cached account balance. |
| [Comments](../Bundle/Modules/Comments/README.md) | Generic "comments on anything" store. |
| [Cron](../Bundle/Modules/Cron/README.md) | Scheduled tasks via `php garnet cron`. |
| [Dashboard](../Bundle/Modules/Dashboard/README.md) | Admin-panel scaffold with menus + role gate. |
| [Email](../Bundle/Modules/Email/README.md) | `symfony/mailer` + queue table + cron drainer. |
| [EntityHistory](../Bundle/Modules/EntityHistory/README.md) | Per-entity audit log with lazy schema. |
| [Idempotency](../Bundle/Modules/Idempotency/README.md) | Server-side idempotency keys for client retries. |
| [Invite](../Bundle/Modules/Invite/README.md) | Invite-token registration with CAS consume. |
| [JsErrors](../Bundle/Modules/JsErrors/README.md) | Browser `window.onerror` collector with dedupe. |
| [Logging](../Bundle/Modules/Logging/README.md) | App-event log + admin viewer + mail/request sub-loggers. |
| [Messaging](../Bundle/Modules/Messaging/README.md) | User-to-user direct messages. |
| [News](../Bundle/Modules/News/README.md) | News / announcement feed. |
| [StaticPages](../Bundle/Modules/StaticPages/README.md) | Block-based static page editor. |
| [Support](../Bundle/Modules/Support/README.md) | Support tickets with attachments + internal notes. |
| [SystemSettings](../Bundle/Modules/SystemSettings/README.md) | Owner-only runtime settings (brand, SMTP, contacts, SEO). |

## Writing your own bundle

Walk-through in [`cookbook/add-a-bundle.md`](cookbook/add-a-bundle.md).
The short version:

1. Pick a namespace under your app, e.g.
   `PHPCraftdream\MyApp\Reports`.
2. Create `Reports/Reports.php` extending `BaseBundleInit`.
3. Add `Reports/Common/Tables/`, `Reports/Foreground/Controllers/`,
   `Reports/Front/Islands/`, `Reports/TwigTemplates/` as needed.
4. Register `Reports\Reports::class` in `MyApp::bundles()`.
5. Add routes for the bundle's controllers in `MyApp::runWebApp()`.

Sub-conventions:

- **Common/** holds services / table gateways usable from both
  Foreground and Dashboard.
- **Foreground/Controllers/** for public-facing routes.
- **Dashboard/Controllers/** for admin-only routes (always behind
  `moderatorOnly` / `adminOnly` middleware).
- **Migrations/Items/M_NNNN.php** for schema changes (numbered).
- **Front/Islands/<Name>/** for React islands the bundle hydrates.

## Related

- [`../Bundle/README.md`](../Bundle/README.md) — module index.
- [`architecture.md`](architecture.md) — request lifecycle.
- [`frontend.md`](frontend.md) — island model.
- [`cookbook/add-a-bundle.md`](cookbook/add-a-bundle.md) — start-to-finish recipe.
- [`cookbook/add-a-route.md`](cookbook/add-a-route.md) — routing.
- [`database.md`](database.md) — `DbTable` and migrations.

# Bundle

The framework's reusable, opt-in modules. Each is a self-contained
piece of functionality — auth, comments, file upload, cron, etc. —
that a host application includes via `bundles()` in its main class.

> **Convention.** Framework-level classes carry a `Fw` prefix
> (`FwAccountsController`, `FwBalanceLedger`, `FwAppSettings`). Apps
> extend those for business-specific behaviour without polluting the
> framework.

## Modules

| Module | What it does |
|---|---|
| [`Modules/Auth/`](Modules/Auth/README.md) | Passwordless email magic-link authentication with CSRF, brute-force mitigation. |
| [`Modules/Balance/`](Modules/Balance/README.md) | Account balance + transaction ledger with paid/refund flows. |
| [`Modules/Comments/`](Modules/Comments/README.md) | Threaded comments attached to any entity. |
| [`Modules/Cron/`](Modules/Cron/README.md) | Cron task registry and runner. |
| [`Modules/Dashboard/`](Modules/Dashboard/README.md) | Admin dashboard scaffold (`/admin/*`). |
| [`Modules/Email/`](Modules/Email/README.md) | Mail rendering + queueing on top of `symfony/mailer`. |
| [`Modules/EntityHistory/`](Modules/EntityHistory/README.md) | Per-entity audit log with diff rendering. |
| [`Modules/Idempotency/`](Modules/Idempotency/README.md) | Idempotency keys for POST handlers. |
| [`Modules/Invite/`](Modules/Invite/README.md) | Invite-token registration. |
| [`Modules/JsErrors/`](Modules/JsErrors/README.md) | Browser → server JS error reporting endpoint. |
| [`Modules/Logging/`](Modules/Logging/README.md) | Channelled log writers with admin UI. |
| [`Modules/Messaging/`](Modules/Messaging/README.md) | User-to-user direct messaging (`IM`). |
| [`Modules/News/`](Modules/News/README.md) | News/announcement publishing. |
| [`Modules/StaticPages/`](Modules/StaticPages/README.md) | CMS-style static pages with block editor. |
| [`Modules/Support/`](Modules/Support/README.md) | User-to-admin support tickets. |
| [`Modules/SystemSettings/`](Modules/SystemSettings/README.md) | Runtime app settings (brand, SMTP, support contacts, SEO). |

## Other top-level dirs

| Dir | What's inside |
|---|---|
| `Front/` | Shared frontend: React islands, hooks, Tailwind utilities, datepicker, table, dialog — see [`Front/README.md`](Front/README.md). |
| `TwigTemplates/` | Layouts (`Layout/HtmlLayout.twig`), email components, reusable partials. |
| `Middlewares/` | Cross-cutting middlewares (CSRF, worker-scope, dev-only checks). |
| `Utils/` | Helpers used by multiple modules (HTML layout helper, prefetch, etc.). |
| `I18n/` | Framework-level translation strings (`I18nFramework`). |
| `Controllers/` | Reusable abstract controllers (`FwAccountsController`, `FwCmsController`, …). |

## Adding your own bundle

See the recipe [`../docs/cookbook/add-a-bundle.md`](../docs/cookbook/add-a-bundle.md).

## Related

- [`../Kernel/README.md`](../Kernel/README.md) — the engine these modules sit on.
- [`../docs/bundle.md`](../docs/bundle.md) — bundle architecture in depth.

---

↑ Back to [Framework root](../README.md)

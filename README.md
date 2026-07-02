# Garnet Framework

High-performance, opinionated PHP 8 web framework — O(1) routing,
parallel async MySQL, type-safe asset bridge, Twig templating, React
islands.

[![CI](https://github.com/PHPCraftdream/garnet-framework/actions/workflows/ci.yml/badge.svg)](https://github.com/PHPCraftdream/garnet-framework/actions/workflows/ci.yml)
[![License: MIT OR Apache-2.0](https://img.shields.io/badge/license-MIT%20OR%20Apache--2.0-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-2a5d9c.svg?logo=php&logoColor=white)](phpstan.neon)
[![Code style: php-cs-fixer](https://img.shields.io/badge/code%20style-php--cs--fixer-46a2f1.svg)](.php-cs-fixer.php)
[![Tests: kahlan](https://img.shields.io/badge/tests-kahlan%20%E2%9C%93%202619-31c653.svg)](https://kahlan.github.io/)
[![Twig](https://img.shields.io/badge/Twig-3-8bc34a.svg?logo=twig&logoColor=white)](https://twig.symfony.com/)
[![React](https://img.shields.io/badge/React-18-61DAFB.svg?logo=react&logoColor=black)](https://react.dev/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1.svg?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![rspack](https://img.shields.io/badge/bundler-rspack-f93920.svg)](https://rspack.dev/)
[![Playwright](https://img.shields.io/badge/e2e-Playwright-2EAD33.svg?logo=playwright&logoColor=white)](https://playwright.dev/)
[![PRs welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)

<!-- Activate after Packagist publication:
[![Latest Version](https://img.shields.io/packagist/v/phpcraftdream/garnet-framework.svg)](https://packagist.org/packages/phpcraftdream/garnet-framework)
[![Total Downloads](https://img.shields.io/packagist/dt/phpcraftdream/garnet-framework.svg)](https://packagist.org/packages/phpcraftdream/garnet-framework)
-->

```bash
# 1. Get the framework. `composer install` also wires the frontend
#    toolchain (npm + node_modules) via the bundled `garnet setup` hook.
git clone https://github.com/PHPCraftdream/garnet-framework
cd garnet-framework && composer install

# 2. Scaffold an app from the bundled template. It's born ready —
#    vendor, node deps, Playwright and a working build, zero manual config.
php garnet app:create MyApp
cd ../Apps/MyApp

# 3. Build assets and run it (port 8001).
php garnet build
php garnet serve
```

That's the whole quickstart — no separate frontend install step. Re-run the
installer any time with `php garnet setup`. The rest of this README explains
what you just installed and where to go next.

---

## Why Garnet

Garnet started as the engine of a production booking platform and was
extracted into a standalone framework. It optimises for three things,
in this order:

1. **Performance** — every architectural decision is weighed against its
   cost. The router is O(1) (path used as a hash-map key, no regex on
   dispatch). MySQL queries can fan out in parallel (`mysqli_poll`,
   total latency = `max(query_time)`, not the sum). Assets are pre-hashed
   and referenced through codegen-typed PHP classes.

2. **Developer experience** — repetitive work is automated. `php garnet
   prepare` generates type-safe `*Gen.php` accessors for every asset.
   `IEntityConfig` drives generic grid/form controllers via the
   Template Method pattern. The CLI is a single `php garnet <command>`
   surface for everything: build, serve, migrate, deploy, cache.

3. **Explicit control** — no auto-discovery magic. Routes are PHP
   arrays. Bundle bootstrap is a method call. Dependencies are listed in
   `composer.json`, not divined at runtime.

If you're building a CRUD-heavy app with custom business logic and you
want fewer dependencies, fewer mystery files, and a tight `php garnet`
loop, this might fit.

## Feature highlights

- **O(1) router** — direct hash-map dispatch with `/path/~method` syntax.
- **Parallel async MySQL** — multiple queries via `mysqli_poll`; total
  wait time bounded by the slowest query.
- **Type-safe asset bridge** — `*Gen.php` classes give IDE-autocompletable,
  cache-busted URLs for every JS/CSS chunk.
- **Bundle architecture** — self-contained modules (auth, CRUD, i18n,
  uploads, comments, support, IM, notifications) that auto-register
  templates, assets and services.
- **`IEntityConfig` CRUD** — declarative entity specs drive generic
  grid/form controllers with validation, field types, role gates.
- **Passwordless auth** — built-in email magic-link state machine with
  CSRF, brute-force mitigation, single-page UX.
- **Twig templating** — strict separation of markup (Twig) from logic
  (PHP); auto-escaping by default.
- **React island frontend** — lazy-loaded React components in
  server-rendered pages; ErrorBoundary-wrapped; no full SPA required.
- **CLI tooling** — single `php garnet` entry point for build, serve,
  deploy, migrations, codegen.

## Installation

### As a dependency

```bash
composer require phpcraftdream/garnet-framework
```

You typically don't `require` Garnet directly — you create an app from
the bundled template (`php garnet app:create MyApp`), which pins the
dependency and wires the composer path-repo for you.

### Requirements

- PHP **8.1+**
- Extensions: `mbstring`, `json`, `pdo`, `mysqli`, `intl`
- MySQL **8.0+** or MariaDB **10.6+** (optional — only if you use the DB)
- Node.js **18+** (for the frontend build)
- Composer **2.x**

## What you get in an app

Once `app:create` finishes you have a working app that:

- Boots `php garnet serve` on `http://localhost:8001`.
- Has passwordless email auth and a wired admin console.
- Has CRUD scaffolding ready to plug into via `IEntityConfig`.
- Has a `php garnet build` frontend pipeline (rspack) that watches and
  rebuilds on save.

```
my-app/
├── garnet              # local CLI wrapper
├── composer.json
├── package.json
├── Application.php     # main app class
├── Common/             # shared services
├── Foreground/         # public-facing controllers + Twig
├── Dashboard/          # admin panel (optional)
├── Front/              # business-specific React + CSS
├── Migrations/         # DB schema migrations
└── WorkDir/            # runtime: config, caches, logs (gitignored)
```

See `docs/quickstart.md` for the step-by-step.

## Architecture, in one paragraph

A request hits `public/index.php` → `IoRunWeb::run` builds the global
request state → middlewares run in order → the O(1) router dispatches
to a controller method (`get__foo`, `post__bar`) → the controller
prepares a typed array and hands it to Twig → Twig renders the
response, which is emitted by `Emitter`. CLI commands take the same
shape via `IoRunConsole` and `php garnet`. DbPool wraps `mysqli` with
async support, so any controller can fan out reads concurrently. Bundles
register themselves at boot, contributing routes, services, templates
and asset roots without auto-discovery.

For the full story see [`docs/architecture.md`](docs/architecture.md).

## Documentation

- [`docs/quickstart.md`](docs/quickstart.md) — scaffold a new app
- [`docs/dev-workflow.md`](docs/dev-workflow.md) — develop framework + app together
- [`docs/architecture.md`](docs/architecture.md) — layers, request lifecycle, async DB
- [`docs/bundle.md`](docs/bundle.md) — writing your own bundle
- [`docs/cli.md`](docs/cli.md) — every CLI command
- [`docs/database.md`](docs/database.md) — DbPool, DbTable, async patterns
- [`docs/frontend.md`](docs/frontend.md) — React islands, codegen, asset bridge
- [`docs/i18n.md`](docs/i18n.md) — translation pipeline
- [`docs/deploy.md`](docs/deploy.md) — production deploy via `garnet bundle` / `deploy:diff`
- [`docs/known-issues.md`](docs/known-issues.md) — sharp edges
- [`AGENTS.md`](AGENTS.md) — onboarding for AI agents / new devs

## Known limitations (v0.x)

Garnet evolved out of a booking platform, and a few framework primitives
still carry domain names from that lineage. They work fine, but they
look booking-shaped from the outside:

- `FwBalanceLedger` entry types are stored as string values
  `booking_invoice`, `booking_payment`, `booking_refund`. v1.0 will
  rename them to generic equivalents (`tx_invoice`, `tx_payment`,
  `tx_refund`) with a migration path.
- `FwAppSettings::cancellationPenaltyPercent` is a booking-domain
  setting in the framework's settings module; v1.0 will move it to the
  application layer.
- A handful of CRUD-action i18n keys reference `booking_*` operations
  (e.g. `AdminAction_booking_cancel`). They'll be generalised the same way.

Until v1.0 the framework is the most natural fit for booking /
scheduling / appointment-style apps, but it works for plain CRUD or
content apps too — these booking-shaped pieces are isolated to a few
classes you can ignore if you don't need them.

## Development

```bash
git clone https://github.com/PHPCraftdream/garnet-framework
cd garnet-framework
composer install
composer ci             # phpstan + cs:check + kahlan
```

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for the contribution flow.

## Security

If you discover a security issue, please **do not** open a public
issue. See [`SECURITY.md`](SECURITY.md) for the responsible-disclosure
contact and policy.

## License

Garnet Framework is **dual-licensed under MIT or Apache-2.0**, at your
option. Pick whichever fits your project — both texts ship with the
repo:

- [LICENSE-MIT](LICENSE-MIT)
- [LICENSE-APACHE](LICENSE-APACHE)

Unless you explicitly state otherwise, any contribution submitted for
inclusion shall be dual-licensed as above, without any additional
terms or conditions.

© PHPCraftdream and contributors.

# Quickstart

Create and run a new Garnet application — zero manual configuration.

## Requirements

- PHP 8.1+
- Composer 2.x
- Node.js 18+ (for the frontend build and Playwright e2e)
- MySQL 8.0+ / MariaDB 10.6+ (optional, only if you use the DB)

## Install the framework

Cloning + `composer install` is the whole setup. `composer install` runs a
bundled post-install hook (`php bin/garnet setup`) that installs the FrontBuilder
node toolchain and links `node_modules` at the framework root — so rspack,
tsgo and oxlint work immediately, with no extra steps.

```bash
git clone https://github.com/PHPCraftdream/garnet-framework
cd garnet-framework
composer install            # composer deps + npm + node_modules junction
```

> Re-run the installer any time with **`php bin/garnet setup`**. It is idempotent
> (already-installed steps are a no-op) and degrades gracefully when npm is
> absent (the PHP half still completes). If you cloned before installing
> Node.js, just run `php bin/garnet setup` once Node is on your PATH.

## Scaffold an app

```bash
php bin/garnet app:create MyApp
cd MyApp
```

The app name must start with an uppercase letter and use PascalCase
(`^[A-Z][A-Za-z0-9_]+$`) — e.g. `MyApp`, `DemoShop`, **not** `my_app` or
`my-app`.

`app:create` does everything for you:

1. copies the bundled template and substitutes the app name,
2. wires the composer path-repo to the framework (relative when on the same
   drive, absolute across drives so Windows multi-drive setups work),
3. writes `.env` (`APP_NAME`) and runs `composer install`,
4. that `composer install` fires the app's own post-install hook
   (`php bin/garnet setup`) — installing the app's node deps and Playwright, so
   the new app is born with vendor, `node_modules`, e2e and a working build.

Keep each app in its own git repository.

## Project structure

A fresh app contains:

```
MyApp/
├── garnet                  # local CLI wrapper — `php garnet <command>`
├── composer.json           # depends on phpcraftdream/garnet-framework
├── package.json            # @types/web for IDE / tsgo
├── autoload.php            # require vendor/autoload.php
├── Public/                 # web docroot — index.php boots the app
├── run_web.php             # web request flow
├── run_cmd.php             # CLI entry point
├── MyApp.php               # main app class — registers Bundles + routes
├── .env.example            # copy to .env, fill in
├── Common/                 # shared services, table gateways, entities
├── Foreground/             # public-facing controllers + Twig templates
├── Dashboard/              # admin panel controllers (optional)
├── Front/                  # frontend sources (TSX/CSS) for this app
├── Migrations/             # DB schema migrations
├── Tests/                  # Playwright end-to-end specs
└── WorkDir/                # runtime: config, caches, logs (gitignored)
```

The app's frontend is built by the **framework's** FrontBuilder (rspack), so
an app carries no bundler of its own — only the `@types/web` types for editor
support and the Playwright deps under `Tests/`.

## First run

```bash
cp .env.example .env        # fill in APP_NAME, DB credentials, etc.
php garnet config:init      # seed WorkDir/Config/ from templates
php garnet migration        # run DB migrations (skip if you have no DB yet)
php garnet build            # build frontend assets
php garnet serve            # Node front-server + PHP worker pool (port 8001)
```

Open <http://localhost:8001/> in your browser.

## Re-cloning an existing app

A teammate cloning your app repo runs a single command — the post-install hook
sets up the node side automatically:

```bash
git clone <your-app-repo> && cd <app>
composer install            # vendor + node deps + Playwright, via `garnet setup`
```

## Daily development cycle

| Command | What it does |
|---|---|
| `php garnet serve:watch` | dev server + rspack watcher (live frontend reload) |
| `php garnet build` | production frontend build |
| `php garnet admin:build` | build the `/__garnet/` admin panel assets |
| `php garnet migration` | apply pending migrations |
| `php garnet cache` | clear Twig + file caches |
| `composer test:e2e` | run the Playwright suite (`Tests/`) |
| `composer ci` | cs-fixer + phpstan (quality gate) |
| `php garnet help` | full command list |

## Where to put what

- **Backend logic** → `Foreground/Controllers/`, `Common/Services/`, `Common/Tables/`.
- **Frontend (React islands)** → `Front/Islands/<Feature>/<Component>.tsx`. The framework lazy-loads them; see `docs/frontend.md`.
- **Templates** → `Foreground/TwigTemplates/`. No HTML in PHP — see project AGENTS.md / coding standards.
- **DB migrations** → `Migrations/Items/M_NNNN.php`. Pattern: incrementing numbered files.
- **Translations** → `Foreground/I18n/ForegroundI18nDataRu.php` + `…En.php`. TS files are generated; never hand-edit them.

## Next steps

- See [dev-workflow.md](dev-workflow.md) — how to develop the framework and your app side by side.
- See [architecture.md](architecture.md) — request lifecycle, router, bundles.
- See [database.md](database.md) — DbPool, DbTable, async queries.
- See [frontend.md](frontend.md) — React islands, codegen, asset bridge.

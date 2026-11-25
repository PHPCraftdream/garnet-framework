# Getting Started

## Prerequisites

The same toolchain on every platform — no bundled web-server binary:

| Tool | Why | Install |
|---|---|---|
| **PHP 8.1+** (CLI) with `pdo`, `mbstring`, `mysqli` | Runs the app + the dev-server worker pool (`php -S`). | Windows: php.net build · macOS: `brew install php` · Linux: `apt install php-cli php-mysql` |
| **Node 24+** | The dev server (`tooling/server/garnet-serve.mjs`), frontend build, and React Carburetor tooling. | [nodejs.org](https://nodejs.org) · `brew install node` · `apt install nodejs` |
| **Composer 2** | PHP dependencies. | [getcomposer.org](https://getcomposer.org) |
| **MySQL 8.0+ / MariaDB 10.6+** | Database. | platform package |

There is **no nginx requirement** — the Node dev server serves static
files and reverse-proxies dynamic requests to the PHP worker pool. For
production you front the app with whatever web server you already run
(nginx, Apache, Caddy) → PHP-FPM.

## Installing the Framework

Clone and `composer install` — that is the entire setup. A bundled
post-install hook runs `php bin/garnet setup`, which installs the FrontBuilder
node toolchain and links `node_modules` at the framework root, so rspack /
tsgo / oxlint work out of the box:

```bash
git clone https://github.com/PHPCraftdream/garnet-framework
cd garnet-framework
composer install            # PHP deps + npm + node_modules junction
```

`php bin/garnet setup` is the explicit, idempotent installer behind that hook —
run it any time to (re)install. It degrades gracefully when npm is missing
(the PHP half still completes; finish the node half later with the same
command once Node.js is on your PATH).

The FrontBuilder installs `@react-carburetor/core` and the native
`@react-carburetor/lint` at 0.3.0. Both the Carburetor recommended and
Garnet house-style presets are enabled as errors in oxlint and standalone
`carburetor-lint`; lint runs with `--deny-warnings`, with no suppressions.
`carburetor/no-react-hooks-in-components` is an error everywhere: framework
components are hook-free `AntiHookComponent` classes, and the application
template's `.oxlintrc.json` enables the same rule for new apps. Its
`npm run check` also runs `lint:carburetor` (the framework's native linter over
`Front`, `Common` and `Dashboard`, configured by the template's
`.carburetorrc.json`). `npm --prefix FrontBuilder run lint:carburetor` scans
`Bundle/Front` and the CLI admin front (the vendored `Bundle/Front/ThirdParty/**`
is ignored in `.carburetorrc.json` and `.oxlintrc.json`). The functional
stylesheets (`Styles/modules/carburetor-*.css`) are imported by `framework.less`;
their Garnet roles bridge existing theme variables so custom and dark themes
keep working. The older global component stylesheets
(`Styles/modules/components/*.css`) stay as a compatibility layer for
applications until they migrate — see `docs/reference/legacy-styles-audit.md`
for which classes the application still uses.

DurationSelect accepts `variant="compact"` instead of an arbitrary `className`;
its default and compact styles follow the framework's `form-select` and
`form-select-sm` rules. AsyncIconButton accepts `variant="muted-outline"` or
`variant="accent-outline"` instead of `className`, and is unstyled by default.
Applications must migrate callers and any app-specific selector styling.
For attachment actions, `attachment-add-button` provides the framework's
outline-secondary button appearance, including the glass skin.

## Creating a New App

Apps are scaffolded from the template that ships inside the framework
(`Templates/Application/`) by the framework's own CLI:

```bash
php bin/garnet app:create MyApp
```

This copies the skeleton, substitutes the app name, wires the composer
path-repo to the framework, and runs `composer install` — whose own
post-install hook then installs the app's node deps and Playwright. The new
app is born ready (vendor, `node_modules`, e2e, a working build) and is a
standalone project depending on `phpcraftdream/garnet-framework` — keep it in
its own repository.

> There is no separate `composer create-project` template package — the
> framework and the app skeleton live in one repo, and `app:create` is the
> generator. Your apps are the things that get their own repos.

## App Structure

```
MyApp/
├── garnet                  # CLI entry point (thin wrapper)
├── composer.json           # requires phpcraftdream/garnet-framework
├── MyApp.php              # App class (extends BaseAppInit)
├── run_web.php            # HTTP entry point
├── run_cmd.php            # CLI entry point (internal)
├── Common/                # Shared services, entities, helpers
├── Foreground/            # Public-facing controllers + templates
├── Dashboard/             # Admin controllers + templates
├── Front/                 # Frontend source (TSX, CSS)
├── Public/                # Document root (the dev server serves from here)
│   └── assets/            # Built assets (generated)
├── Migrations/            # Database migrations
├── WorkDir/               # Runtime (gitignored)
│   ├── Config/            # app.ini, db.ini, email.ini
│   └── Cache/             # Twig cache, etc.
└── .env                   # APP_NAME, env flags
```

## Initial Setup

1. Configure your database in `WorkDir/Config/db.ini`:
   ```ini
   host = "localhost"
   port = 3306
   dbname = "my_app"
   user = "root"
   password = ""
   prefix = "db_myapp"
   ```

2. Run migrations:
   ```bash
   php garnet migration
   ```

3. Start the dev server:
   ```bash
   php garnet serve
   ```

## Dev Workflow

| Command              | Purpose                                      |
|----------------------|----------------------------------------------|
| `php garnet serve`   | Start the Node dev server + PHP worker pool  |
| `php garnet build`   | Build frontend assets (production)           |
| `php garnet build:watch` | Rebuild on file change                   |
| `php garnet prepare` | Regenerate asset maps, app-info JSON, i18n   |
| `php garnet migration` | Run pending database migrations            |

## Frontend

Frontend source lives in `Front/`. The framework uses a React-island model:

- Each island is a lazy-loaded React component embedded in a Twig-rendered page.
- Build is powered by Rspack (configured in the framework package).
- Shared components and utilities come from `vendor/phpcraftdream/garnet-framework/Bundle/Front/Common/`.

Edit TSX/CSS in `Front/`, then run `php garnet build` (or `build:watch` for HMR).

## Backend

- Controllers go in `Foreground/Controllers/` (public) or `Dashboard/Controllers/` (admin).
- Templates go in corresponding `TwigTemplates/` directories.
- Business logic goes in `Common/` (services, table classes, helpers).
- Database tables extend `DbTable` from the framework.

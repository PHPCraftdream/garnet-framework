# Garnet CLI

Single entry point — the `garnet` script in the repo root. Invoke as
`php garnet <command> [args]`. The CLI serves one application at a
time (the **active** one); switch with `app:use`.

## Contents

- [Basics](#basics) — active app, configs, work dir
- [Commands](#commands) — reference, grouped
  - [Server & dev environment](#server--dev-environment)
  - [Build & frontend](#build--frontend)
  - [Database & migrations](#database--migrations)
  - [Configuration](#configuration)
  - [Deploy & SSH](#deploy--ssh)
  - [Admin panel](#admin-panel)
  - [App-level commands](#app-level-commands)
- [Notes](#notes)

---

## Basics

### Active app

```bash
php garnet app           # show current
php garnet app:list      # everything under Apps/
php garnet app:use Name  # switch + run prepare
php garnet app:create Name  # scaffold a new one from the template
```

The active app's name lives in `Framework/.active-app`. Every other
command reads config from `Apps/<Active>/WorkDir/Config/` (or
`WorkDir/ConfigDev/` in dev).

### Configs

```
Apps/<App>/WorkDir/
├── ConfigExample/      # templates in the repo — DO NOT edit
│   ├── app.ini
│   ├── db.ini
│   ├── email.ini
│   ├── ssh.ini
│   └── deploy.ini
├── ConfigDev/          # your local values (gitignored)
│   └── …
└── Config/             # prod configs (on the server)
    └── …
```

Seed a working copy from the templates:

```bash
php garnet config:init           # ConfigExample/ → Config/
php garnet config:init --dev     # ConfigExample/ → ConfigDev/
```

The command is non-destructive — existing files are not overwritten.

### Help

```bash
php garnet                       # list all commands
php garnet help                  # same thing
php garnet <cmd>:help            # detailed per-command help
```

E.g. `deploy:diff:help`, `bundle:help`, `ssh:help`.

---

## Commands

### Setup & scaffolding

| Command | What it does |
|---|---|
| `setup` | One-shot installer. From the framework: `composer install` + FrontBuilder `npm install` + a `node_modules` junction at the framework root. From inside an app: `composer install` + the app's node deps + Playwright browsers. Idempotent; degrades gracefully when npm is absent. Also runs automatically via each `composer.json`'s `post-install-cmd` hook, so a plain `composer install` is enough. Flags: `--skip-composer`, `--skip-npm`, `--skip-junction`, `--skip-playwright`, `--soft` (failures → warnings). |
| `app:create <Name>` | Scaffold a new app from `Templates/Application/`: copy + rename, wire the composer path-repo (relative same-drive, absolute cross-drive), write `.env`, `composer install`, normalise code style. The new app is born ready (vendor, node deps, Playwright, working build). |

### Server & dev environment

| Command | What it does |
|---|---|
| `serve` | Starts a Node front server + a pool of 32 `php -S` workers (port 8001 by default). Node serves static; the PHP workers only handle dynamic requests. For SPA testing, Playwright e2e. |
| `serve:watch` | Same + rspack in watch mode (HMR-style frontend rebuilds). |
| `serve:debug` | Same + a Browser MCP token for AI-agent debugging. |
| `prepare` | Runs `PrepareParams` — materialises runtime dirs, assets, `*Gen.php` files (the app-info JSON the frontend build reads). Usually invoked by `serve` and `bundle`. |
| `maintenance on/off/status` | Maintenance-mode flag (`WorkDir/maintenance.flag`). The proxy only lets IPs in `allow_ips` from `app.ini` through. |

The dev server is `tooling/server/garnet-serve.mjs` (Node, no npm deps). It
owns static-file serving and reverse-proxies dynamic requests to the
`php -S` worker pool, pinning Playwright's `X-Test-Worker` requests to a
fixed worker for per-worker DB isolation. No nginx binary is bundled —
Node is already required for the rspack frontend build, and works the same
on Windows, macOS and Linux.

Options for `serve`:

- `--port=8001` — change the port.
- `--workers=32` — size of the `php -S` worker pool (min 1).
- `--debug` — swap `php` for `phpd` (Xdebug enabled).

### Build & frontend

| Command | What it does |
|---|---|
| `build` | Production rspack build (minified, content-hashed names). |
| `build:watch` | Dev build with watch (no minification, source maps). |
| `build:check` | Verifies that every `*Gen.php` hash points to a file that exists under `public/<App>/assets/`. |

After a build, the repo updates `Framework/Bundle/FrameworkJsGen.php`,
`Framework/Bundle/FrameworkCssGen.php`,
`Apps/<App>/Foreground/ForegroundJsGen.php`, and
`Apps/<App>/Foreground/ForegroundCssGen.php` — content-hashed asset
maps. Commit them alongside the frontend changes.

### Database & migrations

| Command | What it does |
|---|---|
| `migration` | Applies pending migrations (`Apps/<App>/Migrations/Items/M_*.php`). |
| `migrate:status` | Reports current DB version vs. target. |
| `db:wipe` | Drops EVERY table with the prefix from `db.ini`. Requires typed-token confirmation. |
| `db:backup` | Logical SQL dump of all tables into `WorkDir/Backups/<auto>.sql.gz`. Flags: `--out=<file>`, `--list`. |
| `db:restore <file>` | Restore from a dump (auto-backs-up the live DB first). `--no-backup` to skip the safety snapshot. |
| `sql "<query>"` | Run a single SQL query against the active app's DB (text output). Also reads from stdin. |

For the migration file structure see [`database.md`](database.md).

### Configuration

| Command | What it does |
|---|---|
| `config:init` | Seeds `Config/` (or `ConfigDev/` with `--dev`) from `ConfigExample/`. Doesn't overwrite existing files. |
| `perms:fix` | `chmod` write permissions on `WorkDir/{LogJournal,TwigCache,Upload,FileCache}`. |
| `uninstall` | Removes a deployed bundle from the host (via ssh). Bundle-deploys only. |

### Deploy & SSH

| Command | What it does |
|---|---|
| `deploy` | Local: maintenance ON → migrations → cache clear → maintenance OFF. Does not push files. |
| `bundle` | Builds 4 sibling directories (`public/`, `framework/`, `app/`, `runtime/`) for a from-scratch deploy. |
| `deploy:diff` | Pushes the file delta between two commits over SSH/SCP. See [`deploy.md`](deploy.md). |
| `ssh "<cmd>"` | Runs a shell command on the host (host + key from `ssh.ini`). |
| `ssh:put <local> [remote]` | Upload a file/directory. |
| `ssh:get <remote> [local]` | Download a file/directory. |
| `ssh:test` | Connection sanity check. |
| `snapshot:pull` | Orchestrator: SSH → collect → pack → download. Gets a full business-data snapshot (DB dump, config, logs) from the deployed host. |
| `snapshot:collect` | Runs on the server: gathers DB dump, config, logs into a staging dir. `--with-uploads` to include user files. |
| `snapshot:pack` | Runs on the server: tar+gzip a collected staging dir into one `.tar.gz`. |
| `test:remote` | Run Playwright e2e tests against a remote (prod/staging) box from the local machine. Provisions a test scope via SSH, runs tests, tears down. |

Full SSH-config and command reference: [`ssh.md`](ssh.md).

### Admin panel

| Command | What it does |
|---|---|
| `admin` | Generates a one-shot admin login token (prints the URL). |
| `admin:build` | Builds the admin-panel assets (separate rspack config). |
| `admin:logout` | Clears the admin session (dev). |

### App-level commands

Beyond the CLI core, the active app can register its own commands:

```bash
php garnet cron          # Apps/<App>/Common/Commands/CMDCron.php
php garnet seed          # Apps/<App>/Common/Commands/CMDSeed.php
php garnet <any>:help    # help for an individual app command
```

Registration: `Apps/<App>/<App>.php → wireCommands()`. The command
contract lives at `Framework/Kernel/Io/Command/ICommand.php`.

---

## Notes

- Every command reads its environment from
  `Apps/<App>/WorkDir/ConfigDev/` (if present) or `Config/`. The
  `GARNET_APP_DIR` env var (set on prod from the runtime `.env`)
  overrides the path.
- The root `garnet` script installs a friendly exception handler —
  typical typos in command names print a single red line. Pass
  `--debug` or `-v` for the full stack trace.
- In a prod bundle the CLI lives in the runtime directory
  (`garnet-runtime-<app>/garnet`), not the repo root. Invocation is the
  same: `php garnet <cmd>`.

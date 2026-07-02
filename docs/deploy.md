# Deploy

Three commands cover the entire deploy lifecycle:

| Command | When |
|---|---|
| `php garnet bundle` | First-time deploy / building a dist tree from scratch |
| `php garnet deploy:diff` | Incremental push of changes between commits |
| `php garnet deploy` | Local (or prod after `bundle`) full release: maintenance ON → migrate → cache clear → maintenance OFF |

This document describes the day-to-day "commit → push" flow. Per-app
guides for the first-time install live in `Apps/<App>/docs/deploy.md`.

## Contents

- [Host layout](#host-layout)
- [First-time deploy: `bundle`](#first-time-deploy-bundle)
- [Incremental deploy: `deploy:diff`](#incremental-deploy-deploydiff)
- [Database migrations](#database-migrations)
- [Case studies](#case-studies)
- [Related docs](#related-docs)

---

## Host layout

After a `bundle` deploy the host carries **four sibling directories**
under `remote_path` from `deploy.ini`:

```
/var/www/u1234567/data/www/
├── example.com/                        ← public_dir (docroot)
│   ├── index.php                          → require 'runtime/_shared_index.php'
│   └── assets/<public_name>/…             ← content-hashed assets
│
├── garnet-framework-2026-05-21/         ← framework_dir (immutable framework sources)
│   ├── Bundle/
│   ├── Kernel/
│   └── vendor/
│
├── garnet-app-myapp/                    ← app_dir (immutable app sources)
│   ├── Common/
│   ├── Foreground/
│   ├── Migrations/
│   └── docs/
│
└── garnet-runtime-myapp/                ← runtime_dir (mutable state)
    ├── garnet                             ← CLI (path-patched for the prod layout)
    ├── _shared_index.php                  ← bootstrap used by docroot/index.php
    ├── .env                               ← BUNDLE_* paths point at the siblings
    └── WorkDir/
        ├── Config/                        ← real configs (db.ini, app.ini, …)
        ├── LogJournal/                    ← request / error logs
        ├── TwigCache/
        ├── FileCache/
        ├── Upload/
        └── .deploy-sha                    ← marker of the last successful deploy:diff
```

Principle: **rwd ⊆ runtime/**, everything else is append-only. This
lets you swap `framework_dir` / `app_dir` atomically without downtime
(via symlink swap or `rsync`-next-to-it + rename).

Names are configured in `deploy.ini`:

```ini
remote_path   = "/var/www/u1234567/data/www"
public_dir    = "example.com"
public_name   = "myapp"
framework_dir = "garnet-framework-2026-05-21"
app_dir       = "garnet-app-myapp"
runtime_dir   = "garnet-runtime-myapp"
```

`public_name` is the URL-asset rebrand segment: locally files live at
`public/MyApp/assets/myapp/…`; on the host at `example.com/assets/myapp/…`.
Both `deploy:diff` and `bundle` apply the same `PublicPathRebrander`.

---

## First-time deploy: `bundle`

`php garnet bundle` assembles a dist tree locally, ready to copy onto
the host.

```bash
# Full build into dist/
php garnet bundle --no-phar --keep-dir

# What you get
ls dist/MyApp/
# garnet-runtime-myapp/
# garnet-framework-2026-05-21/
# garnet-app-myapp/
# example.com/
```

Inside `bundle`:

1. Runs `prepare` (generates `*Gen.php`, asset maps, app-info JSON).
2. `build` — production rspack build for the active app.
3. Copies `Framework/`, `Apps/<App>/`, `public/<App>/` into dist with
   the right topology.
4. **Path-patches** `garnet` and `_shared_index.php` for the runtime
   folder's location on the host.
5. Applies `PublicPathRebrander`: `assets/myapp/` → `assets/<public_name>/` etc.

Push to the host:

```bash
# First-time upload (one-off install)
php garnet ssh:put dist/MyApp/garnet-runtime-myapp             garnet-runtime-myapp             --cd-remote
php garnet ssh:put dist/MyApp/garnet-framework-2026-05-21      garnet-framework-2026-05-21      --cd-remote
php garnet ssh:put dist/MyApp/garnet-app-myapp                 garnet-app-myapp                 --cd-remote
php garnet ssh:put dist/MyApp/example.com                      example.com                      --cd-remote
```

Flags for `bundle`:

- `--no-phar` — don't pack into a `.phar` (default is to pack). For
  most shared hosting it's easier to ship the source tree as-is.
- `--keep-dir` — keep the intermediate directories under `dist/`
  (needed for follow-up `ssh:put`s).
- `--public-dir=NAME`, `--framework-dir=NAME`, `--app-dir=NAME`,
  `--runtime-dir=NAME`, `--public-name=NAME` — override values from
  `deploy.ini`.

Remove a deployed bundle from the host: `php garnet uninstall`.

---

## Incremental deploy: `deploy:diff`

Once the bundle is on the host, ordinary changes ride over
`deploy:diff`. The command takes the diff between two git commits,
maps local paths to remote paths, and pushes only the delta.

### Standard flow

```bash
# 1. Locally: make commit(s)
git commit -am "feat(foo): something"
git push

# 2. Preview what will go out (DRY-RUN by default)
php garnet deploy:diff

# 3. Apply (uploads + bumps the deploy-sha marker on the host)
php garnet deploy:diff --apply
```

With no selector, the command reads the remote marker
`garnet-runtime-<app>/WorkDir/.deploy-sha` and auto-sets
`--after=<marker>` — i.e. catches exactly "what's new since the last
deploy".

### Typed-token confirmation

Before pushing, `--apply` shows a 4-letter random token and waits for
`Type XXXX to confirm:`. It guards against accidental Enter; for CI /
repeated hot-patches there's `--yes` / `-y`. If you ever push the
wrong thing, push a fix commit and run `deploy:diff` again — git
history is the real safety net.

`php garnet uninstall` (full bundle removal from the host) has **no**
bypass — the operation is destructive, the token is mandatory.

### Commit selectors

| Selector | Meaning |
|---|---|
| (empty) | auto-resume from remote `.deploy-sha` |
| `--since=DATE` | `git log --since=…` — `"2 days ago"`, `"yesterday"`, `"2026-05-18"` |
| `--from=SHA` | SHA included and everything after |
| `--after=SHA` | strictly after SHA |
| `--range=A..B` | git-range (A excluded, B included) |
| `--commit=SHA` | a single commit. Repeatable: `--commit=abc --commit=def` |
| `--branch=NAME` | `$(merge-base master NAME)..NAME` — feature branch off master |
| `--file=PATH` | a specific working-tree file (git is ignored). Repeatable. |
| `--files=A,B,C` | comma-separated alias for `--file=` |
| `--full-public` | re-send the whole `public/<App>/` (recovery after a wipe) |

### Path mapping

| Local | On the host |
|---|---|
| `Framework/<rest>` | `<framework_dir>/<rest>` |
| `Apps/<App>/WorkDir/<rest>` | `<runtime_dir>/WorkDir/<rest>` |
| `Apps/<App>/<rest>` | `<app_dir>/<rest>` |
| `public/<App>/<rest>` | `<public_dir>/<rest>` *(with public_name rebrand applied)* |
| `garnet`, `_shared_index.php` | **SKIPPED** — patched by `bundle`, see below |
| `tests/`, `docs/`, `tooling/`, `FrontBuilder/`, … | SKIPPED |

### Frontend auto-build

When frontend sources change, `deploy:diff` runs `build` before the
upload.

Triggers:

- `FrontBuilder/**` (TS/TSX/SCSS, rspack config)
- `Framework/Bundle/Front/**` (shared islands and JS/CSS)
- `Apps/<App>/Front*/**` (per-app islands)
- `Apps/<App>/**/I18nDataRu.php`, `I18nDataEn.php` (regenerate the TS i18n)

Flow:

1. Snapshot `public/<App>/` BEFORE.
2. `php garnet build`.
3. Snapshot AFTER.
4. The wire only carries **the delta** (content-hashed names mean
   unchanged chunks are reused).

Control flags:

- `--frontend` — force a rebuild even if no triggers fired.
- `--no-frontend` — skip the rebuild (you already built locally).

### Bundle-patched files

`garnet` and `_shared_index.php` are NEVER shipped raw. `bundle`
patches their paths for the prod layout (framework folder name,
autoload path, etc.). If `deploy:diff` tries to ship them, it prints
a yellow callout:

```
! patched-by-bundle: garnet — run `php garnet bundle` and `ssh:put` the result.
```

If you genuinely updated `garnet` or `_shared_index.php` (rare),
rebuild the bundle and ship the file pointwise via
`ssh:put dist/<App>/<runtime>/garnet …`.

### Deploy-sha marker

After a successful `--apply` the host gets
`garnet-runtime-<app>/WorkDir/.deploy-sha` with the last shipped SHA.
The next `deploy:diff` without selectors reads it and auto-sets
`--after=<sha>`.

If the marker is stale or the SHA isn't present locally (e.g. after a
`force-push`), the command refuses and asks for an explicit selector.

### File mode (`--file`)

Hotfixes without a commit:

```bash
# Ship two PHP files exactly as they are on disk (modifications)
php garnet deploy:diff \
    --file=Apps/MyApp/Foreground/Controllers/Foo.php \
    --file=Apps/MyApp/Foreground/Controllers/Bar.php \
    --apply
```

Notes:

- Git selectors are ignored.
- No auto-rebuild of the frontend (trusts what you built locally).
- The `.deploy-sha` marker is NOT advanced — a point-deploy isn't a
  "new last state".
- For a file under `public/<App>/`, the four `*Gen.php` are pulled in
  automatically.
- Deletes are not supported — use git mode for those.

### OPcache

Most hosts keep `opcache.validate_timestamps = 1` (default). New files
get picked up on the next request automatically.

If you've disabled timestamp checks:

```bash
php garnet deploy:diff --apply --reset-opcache
```

The flag runs `ssh "php -r 'opcache_reset();'"` after a successful
`--apply`. Only works when the FPM pool shares OPcache with CLI
(unusual on shared hosting, where `opcache.enable_cli=0` is the
default). Otherwise — restart the FPM pool by hand.

### Useful flags

- `--apply` — actually push (default is dry-run).
- `--yes`, `-y` — skip the typed-token prompt (for CI / repeated hot
  patches; the main safety net is the revertable git history).
- `--no-delete` — don't delete remote files even if they were removed locally.
- `--exclude=GLOB` — fnmatch against the local path, repeatable.
- `--limit=N` — raise the 200-file safety cap.
- `--verbose`, `-v` — print every `ssh`/`scp` argv (debug).
- `--strict` — fail if anything ended up in the skipped list.

Full help: `php garnet deploy:diff:help`.

---

## Database migrations

`deploy:diff` does NOT run migrations. By policy: code ships in one
motion; schema is a separate, controlled step.

Standard order:

```bash
# 1. Push the code (including new Apps/<App>/Migrations/Items/M_NNNN.php if any)
php garnet deploy:diff --apply

# 2. Run the migration on prod
php garnet ssh "cd /var/www/u…/data/www/garnet-runtime-myapp && php garnet migration"
```

Alternative: `php garnet deploy` runs
`maintenance ON → migration → cache clear → maintenance OFF` locally.
The prod equivalent is the same via `ssh`:

```bash
php garnet ssh "cd /var/www/u…/data/www/garnet-runtime-myapp && php garnet maintenance on && php garnet migration && php garnet maintenance off"
```

Migration internals: [`database.md`](database.md).

---

## Case studies

### Plain code deploy (1-3 commits)

```bash
git push
php garnet deploy:diff --apply
```

If there's no migration in the commit, this is enough.

### Code + migration

```bash
git push
php garnet deploy:diff --apply
php garnet ssh "cd <runtime> && php garnet migration"
```

### Frontend-only change

`deploy:diff` notices the triggers, runs `build`, and ships only the
new content-hashed chunks.

### PHP hotfix without a commit

```bash
php garnet deploy:diff \
    --file=Apps/MyApp/Foreground/Controllers/Foo.php \
    --apply
```

The marker doesn't move — record the fix as a commit and run a normal
`deploy:diff` afterward.

### Recovery: assets disappeared from the host

```bash
php garnet deploy:diff --full-public --apply --reset-opcache
```

Force-ships the entire `public/<App>/` + `*Gen.php`. Safe after, say,
a manual `rm -rf assets/` on the host.

### Rolling back a bad commit

`deploy:diff` doesn't do rollback by itself. Use git:

```bash
git revert <bad-sha>
git push
php garnet deploy:diff --apply
```

Or pointwise:

```bash
git checkout <good-sha> -- path/to/file.php
php garnet deploy:diff --file=path/to/file.php --apply
git checkout HEAD -- path/to/file.php   # restore locally
```

### Full frontend rebuild + push (no source change)

```bash
php garnet deploy:diff --commit=HEAD --frontend --apply
```

`--frontend` forces rspack even if nothing in the frontend sources
changed.

### Renaming the layout (new dated `framework_dir`)

1. Ship the new `garnet-framework-YYYY-MM-DD` via `bundle` + `ssh:put`.
2. Update `BUNDLE_FRAMEWORK_DIR` in `garnet-runtime-<app>/.env`
   (`ssh:put` the new .env).
3. Smoke test: `curl -I https://<host>/`.
4. Remove the old `garnet-framework-XXXX-XX-XX`.

---

## Related docs

- [`cli.md`](cli.md) — overview of every `php garnet` command.
- [`ssh.md`](ssh.md) — `ssh.ini` config and the `ssh*` commands.
- [`database.md`](database.md) — migration structure.
- [`bundle.md`](bundle.md) — **FrameworkBundle** (the framework's code
  module), not to be confused with deploy-bundle.
- `Apps/<App>/docs/deploy.md` — per-app first-time-install guide.

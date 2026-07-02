# E2E testing

Kahlan specs (see [`testing.md`](testing.md)) cover `Kernel/` and
`Bundle/` in isolation — no browser, no real HTTP round-trip. Playwright
covers the other half: does the actual page render, does the actual
form submit, does the actual admin flow work end to end against a real
running server and a real (isolated) database. This guide covers how
that's structured, how per-worker DB isolation works, and how a
scaffolded app's own CI runs its e2e suite.

## Contents

- [Structure: `Tests/specs/` and `Tests/helpers/`](#structure-testsspecs-and-testshelpers)
- [Per-worker DB isolation](#per-worker-db-isolation)
- [How a scaffolded app's CI runs e2e](#how-a-scaffolded-apps-ci-runs-e2e)
- [Running the framework's own e2e suite locally](#running-the-frameworks-own-e2e-suite-locally)
- [The framework's own CI also runs a subset of these specs](#the-frameworks-own-ci-also-runs-a-subset-of-these-specs)

---

## Structure: `Tests/specs/` and `Tests/helpers/`

An app's Playwright suite lives under `Tests/` at the app root, split
into `specs/` (the actual `*.spec.ts` files, grouped by role/area —
e.g. `admin/`, `user/`, `expert/`, `cross-role/`) and `helpers/` (shared
fixtures, login flows, DB access, everything specs import instead of
reimplementing). The `Templates/Application` scaffold ships (or is
gaining, as this doc is written) the same `Tests/helpers/` foundation —
this section describes the general pattern, grounded in IRabi's
`Tests/helpers/` as the stable reference implementation.

### The `scoped-test` fixture

Every spec imports `test`/`expect` from `helpers/scoped-test.ts`, never
from `@playwright/test` directly. This one file is what makes the rest
of the isolation story (below) actually apply to every test without
each spec having to opt in by hand. It provides:

- **`dbPrefix` / `workerIndex` fixtures** — worker-scoped, resolved
  once per worker process, so a spec doing a direct MySQL assertion can
  target the right tables without hardcoding a prefix.
- **A shared `BrowserContext` per worker** — reused across that
  worker's tests instead of paying `browser.newContext()`'s ~150-250ms
  setup cost per test; the `page` fixture resets cookies/localStorage
  back to the project's `storageState` between tests so nothing leaks
  across specs in the same worker.
- **Per-role page fixtures** (`adminPage`, `expertPage`, `userPage`,
  `moderatorPage`, `ownerPage`) — each backed by its own worker-scoped
  `BrowserContext`, lazily created only if a test actually requests
  that role, loaded from a per-worker `storageState` file
  (`.auth/{role}_w{idx}.json`). This is the auth-storage-state pattern:
  a role logs in once (via `roleLogin`, see below) and the resulting
  cookies are cached to disk so subsequent tests/workers don't repeat
  the login flow.
- **`newScopedContext` / `newScopedPage`** — for specs that need to
  open their own extra `BrowserContext` (cross-role flows, secondary
  tabs). Plain `browser.newContext()` does **not** inherit
  `extraHTTPHeaders` from the `use:` block in `playwright.config.ts`,
  so a spec that bypasses this helper silently loses the worker-scope
  header and its requests fall back to the legacy/shared table prefix
  — always go through `newScopedContext`, never call
  `browser.newContext()` raw.
- **`tn(name)`** — composes a fully-qualified table name
  (`${prefix}_${name}`) for direct-SQL assertions, resolved at call
  time so isolation on/off doesn't require recompiling specs.

### Auth helpers

Unlike an app with its own custom account roles, the bare scaffold
only has two identities: the framework's built-in admin panel and a
generic authenticated account. `helpers/role-login.ts` exposes exactly
those two paths — `generateAdminToken()` + `adminLogin(page, token?)`
(the one-shot CLI-token flow behind `/__garnet/`, same as `php garnet
admin`) and `accountLogin(page, login)` (drives the real email-code
flow via `helpers/auth.ts`'s `registerAccount`/`loginAccount`). There
is no `roleLogin(page, role)`/dev-login-shortcut abstraction here —
that's an app-level convenience an app with its own role model (like
the reference app IRabi) builds on top once it has more than one kind
of account to test as.

`helpers/auth.ts` carries the lower-level building blocks
(`tickPdConsent`, `registerAccount`, `loginAccount`, `clearTestData`)
that `accountLogin` and the auth-flow specs build on — it drives the
framework's real `EmailAuthMiddleware` state machine (request code →
read the code back from `session_data` → verify), not a test-only
shortcut, so what passes here is what a real user's flow does.

Both helpers assume `EmailAuthMiddleware::authOnly` is wired onto
`PW_AUTH_PATH` (default `/account`) — see "Running the framework's own
e2e suite locally" below for what's wired by default and why `/`
itself stays public.

### Per-test isolation

Beyond the DB-prefix isolation (next section), the fixture layer keeps
tests from bleeding into each other within a worker: `page` resets to
the project's `storageState` before every test, role contexts are
reset the same way before handing out a role page, and an
auto-fixture (`__consoleGuard`) fails any test whose page produced an
uncaught browser exception or console error during the run — so a spec
can't pass while quietly leaving broken JS behind for the next one.

## Per-worker DB isolation

Playwright runs specs in parallel across multiple workers. Multiple
workers hitting the same `db_*` tables would race — two workers
registering `testuser_x` at the same moment, one worker's `DELETE`
wiping rows another worker is mid-assertion on. The framework solves
this with a per-request table-prefix override, driven entirely by one
HTTP header.

### `X-Test-Worker` and `WorkerScopeMiddleware`

`Bundle/Middlewares/WorkerScopeMiddleware.php` runs on (almost) every
request and rewrites the active DB prefix for that request's duration:

1. It always clears any prior runtime prefix override first — no stale
   value from a previous request can leak into one that doesn't send
   the header.
2. It checks two independent authorization paths:
   - **Dev context** — `app.ini` has `env=dev` *and* the runtime is
     physically inside a dev directory (`Env::isDevDir()`). Both must
     hold; either alone is treated as untrusted. In this context the
     middleware honors the full per-worker fan-out: `X-Test-Worker: N`
     rewrites the prefix to `test_worker_N`, and a special value
     `template` rewrites it to `test_worker_template` (used once by
     Playwright's `globalSetup` to seed a template schema that gets
     cloned per worker).
   - **Token context** — `TestScope::isActive()`
     (`Kernel/Core/Env/TestScope.php`). This is the *only* way the
     prefix can flip outside a dev directory, i.e. on a deployed
     target. It requires a secret token file (`.allow_tests`) to exist
     on disk in the active app directory *and* the request to prove
     knowledge of that token via the `run-test-garnet-team` header
     (compared with `hash_equals`, constant-time). When active, this
     path pins to a single fixed scope, `test_worker_0` — no
     per-worker fan-out, no `template`, because a shared/production
     target must not fan out into N parallel schemas.
3. If neither context is authorized, the middleware does nothing
   further — the request runs against the normal (`db_*`) tables, and
   a raw/unauthenticated attempt to send the header has no effect at
   all.

Every `DbTable` instance resolves its prefix from `IniConfig::db()` at
query time, not at construction time, so the override applies
automatically to every table lookup made during that request — no
per-table opt-in.

On the Playwright side, `getDbPrefix()` in `helpers/scoped-test.ts`
mirrors this: it reads the base prefix out of `db.ini` (so an
app-specific suffix like `db_ir` doesn't need to be hardcoded into
specs) and, unless `PW_WORKER_ISOLATION=0`, returns
`test_worker_${TEST_PARALLEL_INDEX}` — dropping the original suffix
entirely, matching the dev-context fan-out on the server side. The
`scopeHeaders(workerIndex)` helper builds the header set every context
must carry (`X-Test-Worker`, plus `run-test-garnet-team` when running
against a prod-like target via `PW_PROD=1`), and it's threaded through
every context-creation path in `scoped-test.ts` so no spec can
accidentally create a context that talks to the wrong (or live) tables.

Direct SQL assertions in specs use `tn('accounts')` /
`${workerPrefix}_accounts`-style composition (never a literal `db_ir_*`
string) for the same reason: the literal breaks the moment isolation is
on, because there's no `db_ir_*` table under a `test_worker_N` schema.

## How a scaffolded app's own CI runs e2e

`Templates/Application/.github/workflows/ci.yml` includes a `App E2E —
Playwright smoke` job, separate from the framework's own `quality`
(kahlan/phpstan/cs) job. It runs on every scaffolded app's CI out of
the box:

1. Checks out both the app repo and the framework repo (private,
   authenticated via `GARNET_FRAMEWORK_TOKEN`) side by side, since the
   app's Composer setup path-repos the framework.
2. Sets up Node + PHP toolchains, then runs the framework's own
   frontend setup (`php bin/garnet setup --skip-composer`) so the app's
   asset build can resolve through the framework's `FrontBuilder`.
3. Installs the app's Composer and Node dependencies (including
   Playwright's browsers — this step deliberately does **not** pass
   `--skip-playwright`, since e2e needs them) and builds assets
   (`php garnet build`).
4. **Serves the app and runs Playwright in a single CI step.** This is
   intentional, not incidental: a background process started in one
   GitHub Actions step does not survive into the next step on
   GitHub-hosted runners, so starting `php garnet serve` in the
   background, polling `curl` until it responds, and then running
   `npm --prefix Tests test` all have to happen in the same shell
   invocation. The job kills the server process and propagates
   Playwright's exit code either way.
5. **No MySQL service, no migrations.** The smoke suite that runs here
   is deliberately DB-free — the scaffold's `db.ini` ships with
   `enabled = 0`, so there's no `config:init` / migration step in this
   job at all. This is a *smoke* check (does the app boot and serve a
   page), not the full per-role/per-worker-isolated suite described
   above — that heavier suite is what you'd run locally or in a
   dedicated job once your app actually needs a database for its e2e
   coverage.

## Running the framework's own e2e suite locally

`Templates/Application/Tests/specs/framework-bundle/` ships a set of
e2e specs ported from a real app's test suite, exercising genuine
framework behaviour (auth chrome, `garnet cron`, `<html lang>`, an
import-boundary regression guard) against a **bare scaffolded app** —
no business logic, no seeded data. Run them with:

```bash
composer test:e2e
```

This calls `tooling/scripts/test-e2e.mjs`, which scaffolds a throwaway
app via `garnet app:create`, builds it, serves it, runs the safe
subset of `framework-bundle/*.spec.ts` against it, and deletes the
throwaway app afterwards — so a contributor never has to scaffold,
serve, or clean up by hand. Pass `--keep` to skip the final cleanup
(useful when a spec fails and you want to poke at the app that
produced the failure):

```bash
composer test:e2e -- --keep
```

Only a subset of the ported specs runs by default (`test-e2e.mjs`'s
`SAFE_SPECS` list). The scaffold wires `EmailAuthMiddleware::authOnly`
onto `/account` (`AccountController` — see `Application.php`'s
`runWebApp()`; `/` itself stays public by default), so the 8 specs
that exercise that flow (`auth-email-preserved-on-error`,
`auth-magic-link-defer`, `magic-link`, `registration-gate`,
`consent-csrf`, `email-auth`, `email-link-csrf`, `cookie-samesite`)
are no longer blocked by missing auth wiring. They're excluded from
the *default* `composer test:e2e` run for a narrower reason: they need
a real MySQL database (they read/write `accounts`, `session_data`,
`mail_log` directly via `mysql2`), and the default run is deliberately
DB-free so it works with zero local setup. To run them, point
`PW_AUTH_PATH` at your gated route (defaults to `/account`, matching
the scaffold) and run Playwright directly against an app whose `db.ini`
has `enabled = 1` and a migrated database:

```bash
PW_AUTH_PATH=/account npx playwright test specs/framework-bundle/magic-link.spec.ts
```

`test-e2e.mjs`'s `SAFE_SPECS` list and the CI job's spec list are meant
to move together; wiring a MySQL service into either is a separate
follow-up (see the comment above the CI job's Playwright step).

## The framework's own CI also runs a subset of these specs

`.github/workflows/ci.yml`'s `e2e` job (separate from
`kernel-tests`/`bundle-tests`/`frontend`/`zero-config`) reproduces the
same scaffold → build → serve → Playwright cycle as `composer
test:e2e` above, so every PR is checked against a real running app,
not just static analysis. See the comment above that job's Playwright
step for the current excluded-spec list and why.

## Related

- [Testing](testing.md) — the kahlan side: running/writing unit specs,
  and where the kahlan/e2e line sits.
- [Add an admin entity](cookbook/add-an-admin-entity.md) — the kind of
  admin CRUD flow role-based e2e specs typically exercise.
- [`architecture.md`](architecture.md) — request lifecycle and
  middleware pipeline that `WorkerScopeMiddleware` plugs into.

---

↑ Back to [Documentation index](README.md)

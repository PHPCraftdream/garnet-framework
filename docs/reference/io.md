# IO

Everything that touches the outside world: HTTP router, CLI dispatch,
Twig, INI config, logging, caching, the emitter, the mailer, file
uploads, cookies, rate limits, SSH.

For a directory tour see [`../Kernel/Io/README.md`](../Kernel/Io/README.md).

## Contents

- [Two runners that meet in the middle](#two-runners-that-meet-in-the-middle)
- [Router](#router)
- [IniConfig](#iniconfig)
- [Logger](#logger)
- [Twig](#twig)
- [Cache](#cache)
- [Emitter](#emitter)
- [FileUpload](#fileupload)
- [Cookies + Session integration](#cookies--session-integration)
- [RateLimit](#ratelimit)
- [Mailer](#mailer)
- [Ssh](#ssh)
- [Cron](#cron)
- [GarnetCli](#garnetcli)
- [Related](#related)

---

## Two runners that meet in the middle

```
Web request                  CLI invocation
   │                             │
   ▼                             ▼
IoRunWeb                    IoRunConsole
   │                             │
   ▼                             ▼
GlobalReqParams             GarnetRunner / cmd dispatcher
   │                             │
   ▼                             ▼
Router          ─────►      Controller / Command
   │                             │
   ▼                             ▼
Twig                         echo / passthru
   │
   ▼
Emitter
```

Shared underneath:

- `IniConfig` — typed config (`app`, `db`, `email`, `ssh`).
- `Logger` channels (`SYSTEM_LOGGER`, `ERROR_LOGGER`, `APP_LOGGER`).
- `Twig` (for emails in CLI, pages in Web).
- `Cache` (filesystem-backed).
- `DbPool` (from `Kernel/Db/`).

Neither pipeline cares which it is — the same controller can be
invoked from a web request and from a CLI fake harness.

## Router

`Kernel/Io/Router/Router.php`. O(1) hash-map dispatch.

```php
$router->add('/about',                     [AboutController::class, [], '']);
$router->add('/user~{user}',              [UserController::class, [], '']);
$router->add('/user~{user}/~profile',     [UserController::class, [], '~profile']);
$router->add('/admin/dashboard',          [DashboardController::class,
                                            [[AdminOnlyMiddleware::class, 'process']],
                                            '']);
```

### Path syntax

- `/about` — fixed route.
- `/user~{user}` — `/user~42` → controller sees `params['user'] = '42'`.
- `/path/~method` — calls `HTTPMETHOD__method` on the controller
  (`get__profile`, `post__delete`).

### Dispatch

The path is the key. `Router::dispatch` does `$routes[$path]` — no
regex, no priority sort. Dynamic param routes are normalised to
`/user~{user}` at registration time and looked up by the normalised
shape.

### Middleware

A middleware is a `[Class::class, 'staticMethod']` callable. Each
returns either `null` (let the controller run) or a `ResponseInterface`
(short-circuit).

```php
class AdminOnlyMiddleware
{
    public static function process(GlobalReqParams $g, RouterUriParams $u): ?ResponseInterface
    {
        return Account::current()?->isAdmin()
            ? null
            : FrameworkController::forbidden();
    }
}
```

Recipe: [`cookbook/add-a-route.md`](cookbook/add-a-route.md).

## IniConfig

`Kernel/Io/IniConfig/IniConfig.php` — the typed reader over the four
INI files in `WorkDir/Config/`:

- `app.ini` — `title`, `base_url`, `time_zone`, brand colours, …
- `db.ini` — credentials and prefix.
- `email.ini` — SMTP.
- `ssh.ini` — host, identity, deploy layout.

```php
$brand = IniConfig::app()->paramString('title', 'Garnet');
$port  = IniConfig::db()->paramInt('port', 3306);
$on    = IniConfig::email()->paramBool('enabled', false);
```

Each call goes through a typed accessor (`paramString`, `paramInt`,
`paramBool`, `paramArray`, `paramFloat`) with a default. Misspellings
fail in the call, not silently as empty strings.

Boot order at the top of `run_web.php` calls `defineAppIni()`,
`defineDbIni()`, `defineEmailIni()` once. Reads after that are cheap.

## Logger

`Kernel/Io/Logs/Logger.php` — file-journal channels:

```php
use PHPCraftdream\Garnet\Kernel\Io\Logs\Logger;

Logger::get(Logger::SYSTEM_LOGGER)->write('benchmark', $payload);
Logger::get(Logger::APP_LOGGER)->append('signup', $email);
Logger::get(Logger::ERROR_LOGGER)->write('uncaught', $trace);
```

Channels and files:

| Channel | File pattern | What goes there |
|---|---|---|
| `SYSTEM_LOGGER` | `WorkDir/LogJournal/System/YYYY-MM-DD/SYSTEM_LOGGER-<cat>.log` | framework noise (benchmark, OPcache reset, deploy markers) |
| `ERROR_LOGGER` | `WorkDir/LogJournal/Errors/YYYY-MM-DD.log` | uncaught exceptions / fatals |
| `APP_LOGGER` | `WorkDir/LogJournal/App/YYYY-MM-DD/APP_LOGGER-<cat>.log` | anything the app emits via `Log::write` |

Each entry is `\n\n`-separated, so a follower (`tail -f` or
`php garnet log-tail`) can read sane multi-line records.

The [`Logging`](../Bundle/Modules/Logging/README.md) bundle layers an
additional DB-backed channel + admin viewer on top of this.

## Twig

`Kernel/Io/Twig/Twig.php` — `Twig::get()` returns the configured
environment.

What it knows about:

- Template paths registered by each bundle's `init()` (most importantly
  `Bundle/TwigTemplates/`).
- `Tr` filter for `{{ t.Key() }}` access.
- Custom filters in `Bundle/Filters/`.
- Render cache under `WorkDir/TwigCache/`.

Autoescaping is on by default. `| raw` is for content the PHP side
already trusts; each use should be commented with the reason.

## Cache

`Kernel/Io/Cache/` — filesystem-backed cache with per-call memoisation:

```php
$result = Cache::remember('reports.last_month', 300, function () {
    return ReportsService::build('last_month');
});
```

- TTL in seconds.
- Key namespacing by colon: `reports.last_month`.
- Writes are atomic (tmp file + rename).
- Use the `Logging` bundle, not the cache, if what you want is a
  changelog of writes.

The [Email queue's flush state](../Bundle/Modules/Email/README.md) and
the Twig render cache both ride on this primitive.

## Emitter

`Kernel/Io/Emitter/Emitter.php` — `Emitter::emit($response)` turns a
`Psr\Http\Message\ResponseInterface` into actual headers + body
output. The end of every web request goes through it.

You almost never call `Emitter` directly — controllers return a
`ResponseInterface` and `IoRunWeb::run` emits it.

## FileUpload

`Kernel/Io/FileUpload/FileUploadManager.php` plus
`SecureFileServing.php`. Two-phase upload: pending → commit. Recipe:
[`cookbook/upload-a-file.md`](cookbook/upload-a-file.md).

- Stash phase validates mime + size, writes to
  `WorkDir/Uploads/Pending/<token>/`, returns a token.
- Commit phase moves the file into the final directory and returns
  the relative path.
- Read-back goes through `SecureFileServing::serve(file:, accessCheck:)`
  for per-request authorisation.

## Cookies + Session integration

`Kernel/Io/Cookies/` — typed cookie writer with `SameSite=Lax`,
`Secure` (when HTTPS), and `HttpOnly` defaults. `Session` is what
calls into it. Hand-writing `setcookie` in business code is a smell.

## RateLimit

`Kernel/Io/RateLimit/` — token-bucket primitives. Used by the Auth
bundle to gate magic-link send + verify attempts; reusable for any
per-IP / per-account rate-limited endpoint.

```php
$bucket = RateLimit::for("signup:{$ip}", capacity: 5, refillSec: 60);
if (!$bucket->take()) {
    return $this->jsonError('rate-limited', 429);
}
```

## Mailer

`Kernel/Io/Mailer/` is a thin wrapper around `symfony/mailer`. The
queue / retry loop lives in the
[Email bundle](../Bundle/Modules/Email/README.md); the kernel piece is
the raw "send this message right now" call.

## Ssh

`Kernel/Io/Ssh/SshClient.php` — driven by `ssh.ini`. Powers `ssh*`
commands, `deploy:diff`, `snapshot:pull`. Full reference:
[`ssh.md`](ssh.md).

## Cron

`Kernel/Io/Cron/` — the runtime side of cron-scheduled CLI commands.
The Cron *bundle* (`Bundle/Modules/Cron/`) adds the `cron_log` table
and admin viewer. Recipe:
[`cookbook/add-a-cli-command.md`](cookbook/add-a-cli-command.md).

## GarnetCli

`Kernel/Io/GarnetCli/` is the home of every `Garnet<Name>Command`
class:

| Command | Class |
|---|---|
| `php garnet build / build:watch / build:check` | `GarnetBuildCommand` |
| `php garnet serve / serve:watch / serve:debug` | `GarnetServeCommand` |
| `php garnet prepare` | `GarnetPrepareCommand` |
| `php garnet deploy:diff` | `GarnetDeployDiffCommand` |
| `php garnet bundle` | `GarnetBundleCommand` |
| `php garnet ssh` / `ssh:put` / `ssh:get` / `ssh:test` | `GarnetSshCommand` |
| `php garnet cache / cache:twig / cache:file` | `GarnetCacheCommand` |
| `php garnet maintenance` / `maintenance:remote` | `GarnetMaintenance*Command` |
| `php garnet snapshot:pull / snapshot:collect / snapshot:pack` | `GarnetSnapshotCommand` |
| `php garnet app / app:list / app:use / app:create` | `GarnetAppCommand` |
| `php garnet admin / admin:build / admin:logout` | `GarnetAdminCommand` |
| `php garnet setup` | `GarnetSetupCommand` |
| `php garnet config:init` | `GarnetConfigCommand` |
| `php garnet db:backup / db:restore` | `GarnetDbBackupCommand` |
| `php garnet db:wipe` | `GarnetDbWipeCommand` |
| `php garnet sql` | `GarnetSqlCommand` |
| `php garnet perms:fix` | `GarnetPermsCommand` |
| `php garnet uninstall` | `GarnetUninstallCommand` |
| `php garnet build:check` | `GarnetBuildCheckCommand` |
| `php garnet deploy` | `GarnetDeployCommand` |
| `php garnet test:remote` | `GarnetTestRemoteCommand` |
| `php garnet migrate:status` | `GarnetMigrateStatusCommand` |

The dispatch lives in `GarnetRunner::main()`. Full CLI reference:
[`cli.md`](cli.md).

## Related

- [`../Kernel/Io/README.md`](../Kernel/Io/README.md) — directory tour.
- [`architecture.md`](architecture.md) — request lifecycle.
- [`cli.md`](cli.md) — every CLI command.
- [`ssh.md`](ssh.md) — the SSH config and commands.
- [`deploy.md`](deploy.md) — `bundle` and `deploy:diff`.
- [`frontend.md`](frontend.md) — how the islands get their assets.
- [`i18n.md`](i18n.md) — the `t.Key()` runtime.
- [`cookbook/`](cookbook/) — every recipe ultimately uses Io.

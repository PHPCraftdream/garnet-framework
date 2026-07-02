# Kernel / Io

Everything that touches the outside world: HTTP router, CLI, Twig,
INI config, logger, cache, emitter, mailer, file uploads, cookies.

## What's here

| Subdir | What it does |
|---|---|
| `Bootstrap/` | The minimal shared bootstrap loaded by `run_web.php` and `run_cmd.php` (composer autoload + autoloader install + global error handler). |
| `Cache/` | File-backed cache primitives used by Twig render cache and per-call memoisation. |
| `Command/` | `BaseCmdRun` — the contract every app CLI command extends. |
| `Cookies/` | Typed cookie writer with `SameSite=Lax`/`Secure` defaults; used by `Session` and the auth flow. |
| `Cron/` | Cron task registry, scheduling, runner. Driven by `php garnet cron`. |
| `Emitter/` | `Emitter::emit($response)` — turns a `Psr\Http\Message\ResponseInterface` into actual headers + body output. |
| `ErrorCatcher/` | Fatal-error and uncaught-exception hooks that route to `FrameworkController::internal_error_500`. |
| `FileUpload/` | `FileUploadManager` + `SecureFileServing` — the pending → commit upload pattern and gated read-back. |
| `Forms/` | Server-side form validation. The frontend converts the same `fieldsInfo` to Zod via `zodFromFieldsInfo`. |
| `GarnetCli/` | Every `php garnet <command>` lives here as `Garnet<Name>Command`. `GarnetRunner` dispatches them. |
| `HtmlMinify/` | Lightweight HTML compression for production responses. |
| `I18n/` | `Tr` — the runtime that powers `t.Key()` calls in PHP and the generated TS files. |
| `IniConfig/` | `IniConfig` — parsed `app.ini`, `db.ini`, `email.ini`, `ssh.ini`. Defined by `defineAppIni()` / `defineDbIni()` etc. at boot. |
| `IoRun/` | The two top-level runners: `IoRunWeb` (request → router → controller → emit) and `IoRunConsole` (CLI dispatch). |
| `IoTools.php` | `pr()` / `varDump()` — dev-only debug printers (deliberately raw — used when Twig itself is broken). |
| `Logs/` | `Logger` channels (`SYSTEM_LOGGER`, `ERROR_LOGGER`, `APP_LOGGER`) with file-journal storage. |
| `Mailer/` | `symfony/mailer` wrapper plus the email queue table runner. Used by `Bundle/Modules/Email`. |
| `PSR4Autoload/` | The framework's own autoloader (composer's is the primary, this is the fallback for the few bootstrap-time loads). |
| `RateLimit/` | Token-bucket primitives behind the auth + signup brute-force gates. |
| `Router/` | The O(1) router — path used as a direct hash-map key. `RouterUriParams` parses `/path/~method` and `/x~123` syntaxes. |
| `Ssh/` | `SshClient` driven by `ssh.ini` for `deploy:diff`, `remote-cache`, `snapshot:pull`, etc. |
| `Twig/` | `Twig::get()` — the configured environment, template path stack, render cache. |
| `Spec/` | Kahlan specs for everything above. |

## Two pipelines that meet in the middle

```
Web request          CLI invocation
    │                      │
    ▼                      ▼
IoRunWeb             IoRunConsole
    │                      │
    ▼                      ▼
GlobalReqParams      GarnetRunner / cmd
    │                      │
    ▼                      ▼
Router          ──►  Controller / Cmd
    │                      │
    ▼                      ▼
Twig                  echo / passthru
    │
    ▼
Emitter
```

The middle layers — `IniConfig`, `Logger`, `Twig`, `Cache`, `DbPool` —
are shared between the two pipelines and oblivious to which one
invoked them.

## Conventions

- New CLI commands go to `GarnetCli/Garnet<Name>Command.php` and are
  registered in `GarnetRunner::main`'s `match`. **For app-level
  commands, do it in the app**, not here — see
  [`../../docs/cookbook/add-a-cli-command.md`](../../docs/cookbook/add-a-cli-command.md).
- Logger channels: `SYSTEM_LOGGER` for framework noise, `APP_LOGGER`
  for app events, `ERROR_LOGGER` for the uncaught-exception sink.
- All HTML rendering goes through `Twig`. No string concatenation of
  tags inside PHP — see the strict rule in [`../../AGENTS.md`](../../AGENTS.md).

## Related

- [`../README.md`](../README.md) — kernel overview.
- [`../../docs/cli.md`](../../docs/cli.md) — every CLI command in full.
- [`../../docs/io.md`](../../docs/io.md) — IO subsystem reference.
- [`../../docs/architecture.md`](../../docs/architecture.md) — request lifecycle.

---

↑ Back to [Kernel](../README.md)

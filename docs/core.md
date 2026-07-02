# Core

Kernel-level primitives the rest of the framework composes. Nothing
here knows about the database, HTTP, or any business concept.

This page covers the **concepts** and **conventions**. For a
file-by-file tour, start at [`../Kernel/Core/README.md`](../Kernel/Core/README.md);
each subdirectory has its public methods documented in PHPDoc next to
the code.

## Contents

- [Env — execution environment detection](#env--execution-environment-detection)
- [Tools — strings, arrays, filesystem](#tools--strings-arrays-filesystem)
- [Benchmark — wall-time profiling](#benchmark--wall-time-profiling)
- [Event — pub-sub](#event--pub-sub)
- [GlobalVars — typed cross-cutting registry](#globalvars--typed-cross-cutting-registry)
- [GlobalReqParams — typed request snapshot](#globalreqparams--typed-request-snapshot)
- [HCalendar — Hebrew calendar](#hcalendar--hebrew-calendar)
- [AppInit — boot contract](#appinit--boot-contract)
- [FrameworkController — controller base](#frameworkcontroller--controller-base)

---

## Env — execution environment detection

`Kernel/Core/Env/Env.php` answers three questions:

- **Am I running in CLI or Web?** `Env::isCmd()` / `Env::isWeb()`.
- **Is this a dev checkout?** `Env::isDevDir()` — looks for `.idea`,
  `.vscode`, `.git`, etc. in the path. Used to gate destructive
  dev-only operations (test seeding, dev-login bypass) so they
  cannot run on prod.
- **Does this class implement that interface?** Reflection-cached
  variant — the framework calls this on every bundle init.

Reflection results are cached. Calling `Env::classImplements()` in a
tight loop is cheap.

## Tools — strings, arrays, filesystem

`Kernel/Core/Tools/` holds the dependency-free helpers nothing else
fits into:

| Class | Examples |
|---|---|
| `StrTools` | `camelToSnake`, `slugify`, `truncateMid`, `randomToken`, … |
| `ArrayTools` | `groupBy`, `pluck`, `assocFlatten`, … |
| `FsTools` | `copyDirectory`, `rrmdir`, `prefixedJoin`, … |
| `DateTools` | Date primitives independent of the HCalendar branch. |

When you find yourself reaching for a tiny "I need this everywhere"
function, look here first; if it isn't here, add it.

## Benchmark — wall-time profiling

`Kernel/Core/Benchmark/BenchmarkLog.php` records named timestamps from
the moment the request starts:

```php
BenchmarkLog::init('GET: /admin/dashboard');
// … framework work …
BenchmarkLog::log('config_done');
// … bundle init …
BenchmarkLog::log('db_connected');
// … controller …
BenchmarkLog::log('output_done');

// At end of request:
if (BenchmarkLog::last() > 0.5) {
    Logger::get(Logger::SYSTEM_LOGGER)->append('benchmark', BenchmarkLog::printItems());
}
```

The framework instruments the request lifecycle in `run_web.php`
already — slow requests log a breakdown without you having to think.
Add your own `BenchmarkLog::log('foo_done')` calls where it'd help.

## Event — pub-sub

`Kernel/Core/Event/Event.php` is a tiny in-process pub-sub. Bundles
emit and listen without a direct reference to each other.

```php
$event->on('account.created', function (Account $a): void {
    // welcome email, audit log, etc.
});

// elsewhere:
$event->emit('account.created', $newAccount);
```

Convention: event names are lowercased `dotted.style.strings`. There
is no schema for the payload; document it next to the emitter.

## GlobalVars — typed cross-cutting registry

A small key-value store for things that are *not* per-request: the
PHP binary to spawn for sub-processes, the test-mode marker, the
ErrorCatcher flag. Used by tests and the bootstrap.

```php
GlobalVars::set('phpRunCmd', 'php');
$cmd = GlobalVars::get('phpRunCmd', 'php');
```

Don't use this for request-scoped state — that's `GlobalReqParams`'s
job (below).

## GlobalReqParams — typed request snapshot

The framework constructs a single `GlobalReqParams` value object from
`$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES` at the very top
of `run_web.php` and passes it by reference through every middleware
and controller. **Never** read the superglobals directly inside
business code — use the value object.

```php
public function post__save(GlobalReqParams $g, RouterUriParams $u): ResponseInterface
{
    $email = $g->post()['email'] ?? '';
    $ua    = $g->server()['HTTP_USER_AGENT'] ?? '';
    // …
}
```

Two reasons: it's typed, and it gives tests a single seam — they
construct a fake `GlobalReqParams` and feed it through.

## HCalendar — Hebrew calendar

`Kernel/Core/HCalendar/` is the framework's date branch for Jewish
calendar conversions and chag detection (`Pesach`, `Rosh HaShana`,
fast days, post-Shabbat deferrals). Used by booking-style apps to
mask out unavailable days.

The framework only ships the primitives; business rules ("which days
my service runs on") belong in the app.

If your app doesn't need Jewish calendar awareness, ignore this
subdir — nothing else in the kernel depends on it.

## AppInit — boot contract

`Kernel/Core/AppInit/BaseAppInit.php` is the abstract every app's main
class extends. It owns the bundle iteration loop and the request
lifecycle hooks:

```php
class MyApp extends BaseAppInit
{
    protected function bundles(): array { return [/* … */]; }

    public function runWebApp(GlobalReqParams $g, RouterUriParams $u): ResponseInterface
    {
        $router = $this->router();
        $router->add('/about', [AboutController::class, [], '']);
        // …
        return $router->dispatch($g, $u);
    }
}
```

`BaseAppInit::init()` boots bundles in order. `runWebApp()` runs
inside `IoRunWeb::run`, which sandwiches it with session restore,
async DB poll, error handling, and emit.

## FrameworkController — controller base

`Kernel/Core/FrameworkController.php` is the abstract every controller
extends. It owns the verbs your controller methods use:

| Method | What it does |
|---|---|
| `renderTwig($template, $params)` | Render a Twig template inside `HtmlLayout`. |
| `renderIsland($islandName, $props)` | Render a page that's just one React island plus the layout shell. |
| `json($data, $status = 200)` | Build a JSON response. |
| `jsonError($message, $status = 400)` | Build a JSON error response in the standard shape. |
| `internal_error_500(...)` | Static error handler the kernel installs. |

Convention: a controller is a thin layer that

1. unpacks `GlobalReqParams` / `RouterUriParams` into typed values,
2. delegates to a service in `Common/Services/`,
3. assembles a typed array, and
4. calls one of the verbs above.

Business logic in the controller is a smell — extract a service.

---

## Related

- [`../Kernel/Core/README.md`](../Kernel/Core/README.md) — directory tour.
- [`architecture.md`](architecture.md) — how Core fits with Io, Db, App.
- [`io.md`](io.md) — the layer that consumes most of Core.
- [`database.md`](database.md) — the layer Core is independent of.

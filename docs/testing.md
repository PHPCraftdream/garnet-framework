# Testing

The framework ships [kahlan](https://kahlan.github.io/) spec files with
2619 passing expectations (1924 kernel + 695 bundle), covering
`Kernel/` and `Bundle/`. This guide covers
how to run them, how to write a new one, and where the line sits between
framework-level unit specs and application-level e2e tests.

## Contents

- [Running the suite](#running-the-suite)
- [Writing a kahlan spec](#writing-a-kahlan-spec)
- [Mocking](#mocking)
- [The generic contract spec pattern](#the-generic-contract-spec-pattern)
- [kahlan vs e2e — which to write](#kahlan-vs-e2e--which-to-write)

---

## Running the suite

```bash
composer test           # kernel + bundle specs
composer test:kernel    # Kernel/ only
composer test:bundle    # Bundle/ only
```

These map directly to the scripts in `composer.json`:

```json
"test": ["@test:kernel", "@test:bundle"],
"test:kernel": "php vendor/bin/kahlan --spec=Kernel --pattern=*Spec.php",
"test:bundle": "php vendor/bin/kahlan --spec=Bundle --pattern=*Spec.php"
```

`composer ci` runs `cs:check`, `phpstan`, then `test` — the same sequence CI
runs on every push. See [Dev workflow](dev-workflow.md) for developing the
framework against a side-by-side app checkout.

## Writing a kahlan spec

**File naming and location.** A spec for `Kernel/Db/Entity/Session/Session.php`
lives in a sibling `Spec/` directory as `SessionSpec.php` — i.e.
`<ClassName>Spec.php` inside a `Spec/` subdirectory next to the code it
tests. This holds across the codebase:

```
Kernel/Db/Entity/Session/Session.php
Kernel/Db/Entity/Session/Spec/SessionCsrfSpec.php

Bundle/Modules/Balance/Tables/FwAccountBalance.php
Bundle/Modules/Balance/Spec/FwBalanceSpec.php
```

One class can have several spec files if it's easier to split by concern
(`SessionCsrfSpec.php` for CSRF behavior, a separate file for cookie
persistence, etc.) — kahlan's `--pattern=*Spec.php` picks up all of them.

**Namespace.** The spec's namespace mirrors the directory it lives in, e.g.
`PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Spec` for a file under
`Kernel/Db/Entity/Session/Spec/`.

**Shape.** kahlan uses the same `describe()` / `it()` / `expect()` API as
PHPSpec/RSpec-style frameworks — no test classes, no `assert*` methods:

```php
describe('FwAccountBalance', function (): void {
    describe('getBalance()', function (): void {
        beforeEach(function (): void {
            [$this->ledger, $this->balance] = setupBalanceTables();
        });

        it('returns 0 when no balance row exists for the account', function (): void {
            $result = TestAccountBalance::getBalance(42);
            expect($result)->toBe(0);
        });
    });
});
```

Nest `describe()` blocks by class → method; use `beforeEach()`/`afterEach()`
for setup/teardown scoped to the enclosing block. `expect($x)->toBe($y)`,
`->toBeA('integer')`, `->toBeGreaterThan($n)`, `->toContain($item)`,
`->toBeAnInstanceOf(Foo::class)` cover most assertions you'll need — see
`Kernel/Db/Entity/Session/Spec/SessionCsrfSpec.php` and
`Bundle/Modules/Balance/Spec/FwBalanceSpec.php` for real examples of both
styles.

**Testing without a real DB.** Framework tables (`DbTable` subclasses) are
usually tested against a concrete test-only subclass that overrides
`insert()`/`selectAll()`/etc. with in-memory arrays, rather than mocking the
DB driver. `Bundle/Modules/Balance/Spec/FwBalanceSpec.php` is a good
reference: it defines `TestBalanceLedger extends FwBalanceLedger` and
`TestAccountBalance extends FwAccountBalance`, each backed by a `public
array $rows` and `insertCalls`/`updateCalls` logs the spec asserts against.
`DbTable`'s static singleton registry is reset via reflection between tests
(`resetDbTableSingletons()`) so each spec starts from a clean slate.

## Mocking

`composer.json` lists two mocking libraries under `require-dev`:
`janmarek/mockista` and `mockery/mockery`. **In practice, Mockery is what
spec authors reach for** — it's used directly in spec files
(`Kernel/Db/Entity/Session/Spec/SessionCsrfSpec.php`,
`Bundle/Modules/Idempotency/Spec/IdempotencyMiddlewareSpec.php`). Mockista
only shows up internally, wrapped by `Kernel/Core/BaseTest/BaseTest.php`
(a legacy test helper) — no spec file calls it directly.

A real example from `SessionCsrfSpec.php`, mocking a cookie interface:

```php
use Mockery;

$mockCsrfCookie = Mockery::mock(ICookie::class);
$mockCsrfCookie->allows('getValue')->andReturn($existingToken !== '' ? $existingToken : null);
$mockCsrfCookie->allows('setValue')->andReturnSelf();

// ...

afterEach(function (): void {
    Mockery::close();
});
```

Prefer `allows()` over `expects()` unless the spec specifically needs to
assert a method *was* called — `expects()` turns into a hard failure at
`Mockery::close()` if the call didn't happen, which is easy to get wrong
when a code path has multiple branches.

## The generic contract spec pattern

Most classes under `Bundle/Modules/*` are **abstract framework base
classes** — `Fw*Controller` and `Fw*Table` — that an application subclasses
to supply a concrete table name, DI hooks, and a real database. The
framework itself never wires a concrete instance, so there's no meaningful
"happy path" to unit-test at this layer.

Instead of one `describe('ClassName', ...)` block per class, the framework
uses a **reflection-based contract spec**: a single spec file holds a
hardcoded list of fully-qualified class names and runs the same checks over
all of them in a loop. See:

- `Bundle/Modules/Spec/BundleTablesContractSpec.php` — every `Fw*Table`
- `Bundle/Modules/Spec/BundleControllersContractSpec.php` — every
  `Fw*Controller`
- `Kernel/Db/Entity/Spec/KernelEntityContractSpec.php` — the kernel-level
  entities (`DbAccount`, `SessionTable`, `SettingsTable`, etc.)

For tables, the contract checks:

1. The class exists in the documented namespace.
2. It extends `DbTable` (directly or transitively).
3. It declares a public static `init(): ITableBuilderDriver`.

For controllers, the contract checks the class exists, is either abstract
or exposes a static-setter DI surface (`set*`/`register*`), extends
`FrameworkController`, and declares at least one abstract method or DI
setter — i.e. it genuinely cannot be wired with zero application config.

**What this deliberately does not verify**: real behavior. It doesn't call
`init()` and check the resulting schema, and it doesn't exercise a
controller's actual request/response cycle. That's intentional — these
classes need a concrete subclass, a real database connection, and
dependency-injected collaborators to behave correctly, and assembling all
of that inside the framework's own kahlan suite would mean faking an
entire application. That kind of exercise belongs at the app layer, against
a real running app with a real database — see the e2e testing guide.

## kahlan vs e2e — which to write

| | kahlan spec | e2e spec |
|---|---|---|
| Scope | Pure logic, or I/O behind an interface you can mock/fake | Full request lifecycle: real browser, real HTTP, real DB |
| Speed | Fast (in-process, no network/DB) | Slower (spins up a server, browser) |
| Where it lives | `Kernel/`, `Bundle/Modules/*/Spec/` — inside the framework | Application layer, against a running app |
| What it's good for | Table/entity logic with test doubles, abstract-class contracts, calendar/env/benchmark utilities, CSRF/session token logic | Admin panel flows, auth end-to-end, anything that depends on real DI wiring or a concrete app's routes/views |
| Example | `FwBalanceSpec.php` asserting `recalculate()` inserts the right row | Logging in, navigating the dashboard, submitting a form and checking the DB row landed |

If the class you're testing is abstract and only becomes real once an app
subclasses it, write (or extend) a contract spec here, and leave behavioral
coverage to the app's e2e suite.

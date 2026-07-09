# Architecture

A detailed walk-through of the Garnet framework's architecture.

## Contents

- [Overall architecture](#overall-architecture) — layered design and inter-layer dependencies
- [Request lifecycle](#request-lifecycle) — handling Web and CLI requests
- [Asynchronous architecture](#asynchronous-architecture) — parallel MySQL execution
- [Design patterns](#design-patterns) — Table Gateway, Active Record, EAV, Registry, …
- [Diagrams](#diagrams) — class and database overviews
- [Data flows](#data-flows) — authentication, account save, async loading

---

## Overall architecture

### Layered design

| Layer | Components |
|---|---|
| **Application Layer** | Controllers, Middlewares, Templates |
| **Io Layer** | Router, Cache, Logger, Mailer, Forms, Cookies, Twig, Emitter, IniConfig, Command |
| **Core Layer** | Env, Tools, Event, I18n, Benchmark |
| **Database Layer** | DbMySQLi, DbPool, DbTable, Account, Session, Settings, Migration, EntityLog |

### Dependencies between layers

```
Application ───uses──> Io ───uses──> Core ───uses──> Database
      │                   │              │              │
      └───────────────────┴──────────────┴──────────────┘
                         (PSR-7 interfaces)
```

---

## Request lifecycle

### Web request

```
1. Browser
   │ HTTP Request
   ▼

2. Entry point (run_web.php)
   ├── ErrorCatcher::init()
   ├── IniConfig::defineAppIni() / defineDbIni()
   └── DbPool::get()->newLink()
   ▼

3. IoRunWeb::run()
   ├── Session::readFromServer()
   ├── Session::readDataAsync()
   ├── RouterUriParams::fromGlobals()
   ├── DbPool::poll()
   ├── $init($globals, $uriParams)
   ├── Response normalisation
   ├── Session::patchResponse()
   ├── flushAppData()
   ├── Emitter::emit()
   └── DbPool::pollFinishAll()
   ▼

4. Middlewares
   └── AuthMiddleware, RegMiddleware, etc.
   ▼

5. Router
   └── O(1) hash-table lookup: $routes[$uri]
   ▼

6. Controller
   └── GET__main() / POST__save() / etc.
   ▼

7. Database
   └── Async queries via DbMySQLiLink
   ▼

8. Template
   └── Twig rendering
   ▼

9. Response (PSR-7 ResponseInterface)
   ▼

10. Emitter
    └── Headers + body output
    ▼

11. Browser
```

### CLI request

```
1. Terminal
   │ php run_console.php command arg1 arg2
   ▼

2. IoRunConsole::run()
   ├── Parse $argv
   ├── CommandClasses::get($command)
   └── $commandClass::run($args, $context, $stdio)
   ▼

3. Command
   └── CMDMigration, CMDHelp, etc.
   ▼

4. Output
```

---

## Asynchronous architecture

### MySQL async

Garnet uses `mysqli_poll()` for non-blocking query execution:

**Traditional (sequential):**
- Query 1 → 10 ms → Result
- Query 2 → 10 ms → Result
- **Total: 20 ms**

**Async (parallel):**
- Query 1 → ─
- Query 2 → ─→ 10 ms → Results
- **Total: ~10 ms**

### Implementation

```php
// DbMySQLiLink.php
public function queryAsync(string $sql, array $params = [], callable $callback = null): IDbMySQLiLink {
    $stmt = $this->prepare($sql, $params);
    $stmt->send_long_data(0, 0); // Force async
    $this->asyncQueries[] = [$stmt, $callback];
    return $this;
}

public function pollOnce(): bool {
    $links = $this->asyncLinks;
    $errors = $read = $reject = [$links];

    $poll = mysqli_poll($read, $errors, $reject, 0);

    if ($poll > 0) {
        foreach ($read as $link) {
            $stmt = $link->reap_async_query();
            // Handle the result
        }
    }

    return count($this->asyncQueries) > 0;
}

public function pollFinishAll(): void {
    while ($this->pollOnce()) {
        // Wait for completion
    }
}
```

### DbPool

A connection pool for parallel queries across separate connections:

```
DbPool
├── Link 1 → Query 1 ─┐
├── Link 2 → Query 2 ─┼──→ pollFinishAll()
└── Link 3 → Query 3 ─┘
```

**Usage:**

```php
// Fire queries in parallel
$link1->queryAsync("SELECT * FROM users WHERE id = 1");
$link2->queryAsync("SELECT * FROM settings WHERE user_id = 1");
$link3->queryAsync("SELECT * FROM profile WHERE user_id = 1");

// Wait for all queries to finish
DbPool::get()->pollFinishAll();
```

---

## Design patterns

### Table Gateway

`DbTable` is the Table Gateway:

```php
abstract class DbTable {
    // CRUD operations
    public function insert(array $data): string;
    public function selectOne(string $id): ?array;
    public function selectAll(): array;
    public function update(string $id, array $data): bool;
    public function delete(string $id): bool;

    // Query-builder integration
    public function newSelect(): SelectInterface;
    public function newUpdate(): UpdateInterface;
    public function newInsert(): InsertInterface;
    public function newDelete(): DeleteInterface;
}
```

### Active Record + Unit of Work

`Account` combines both:

```php
class Account {
    // Active Record: data is held on the object
    protected array $params = [];
    protected array $data   = [];

    public function setParam(string $name, mixed $value): void;
    public function readParam(string $name): mixed;

    // Unit of Work: flush() persists every change at once
    public function flush(): void {
        // Only the dirty fields
        $dirty = $this->getDirtyParams();
        $this->update($dirty);
        $this->resetDirty();
    }
}
```

### EAV (Entity-Attribute-Value)

Flexible attribute system:

**accounts (core data):**

| id | name | email |
|----|------|-------|
| 1 | John | john@example.com |

**account_data (extra data):**

| account_id | name | value | bool |
|------------|------|-------|------|
| 1 | phone | 1234567 | 1 |
| 1 | address | Street 1 | 1 |

### Registry

Singleton service access:

```php
class Logger {
    protected static array $loggers = [];

    public static function define(string $dir, string $name): void;
    public static function get(string $name): ILogger;
}
```

### Observer / Event

Simple pub-sub:

```php
class Event {
    protected array $listeners = [];

    public function on(string $event, callable $callback): void;
    public function emit(string $event, mixed $data = null): void;
    public function off(string $event): void;
}
```

### Factory

Object construction:

```php
class DbPool {
    public function newLink(...): IDbMySQLiLink;
}

class QueryTools {
    public static function factory(): QueryTools;
}
```

### Strategy

Pluggable processing:

```php
interface ITableBuilderDriver {
    public function addColumn(...);
    public function addIndex(...);
}

class TableBuilderMySQL implements ITableBuilderDriver { }
class TableBuilderPostgreSQL implements ITableBuilderDriver { }
```

---

## Diagrams

### Class diagram (simplified)

```
IoRunWeb / IoRunConsole
    ↓
Router (autorun, dispatch, addGetRoute)
    ↓
Controller (GET__main, POST__save)
    ↓
DbTable (insert, selectOne, update, delete)
    ↓
DbMySQLiLink (queryAsync, pollFinishAll)
```

### Database diagram

#### Table `accounts`

Passwordless by design — there is no password column. Identity is
proven by a one-time email code, then held via `token16`/`token32`.

| Column | Type | Description |
|---|---|---|
| id | PK | Primary key |
| login | varchar | Email (or username, see `login_type`) |
| login_type | varchar | `email` or `username` |
| token16 / token32 | varchar | Session/verification tokens |
| reg_time | int | Registration unix timestamp |
| last_auth_time | int | Last successful auth unix timestamp |

#### Table `account_data` (EAV)

| Column | Type | Description |
|---|---|---|
| account_id | FK → accounts.id | Account id |
| name | varchar | Parameter name |
| value | text | Value |
| bool | tinyint | Parameter active flag |

**Relationship:** accounts (1) ↔ (N) account_data

#### Table `sessions`

| Column | Type | Description |
|---|---|---|
| id | varchar | Session id (PK) |
| account_id | FK → accounts.id | Account id |
| data | text | Session payload |
| csrf_token | varchar | CSRF token |

**Relationship:** accounts (1) ↔ (N) sessions

#### Table `entity_log`

| Column | Type | Description |
|---|---|---|
| id | PK | Primary key |
| entity_id | varchar | Entity id |
| entity_type | varchar | Entity type |
| account_id | FK → accounts.id | Account id |
| action | varchar | Action (create/update/delete) |
| diff | text | Changes (JSON) |
| created_at | timestamp | Record time |

**Relationship:** accounts (1) ↔ (N) entity_log

#### Table `settings`

| Column | Type | Description |
|---|---|---|
| name | varchar | Key (PK) |
| value | text | Value |

#### Table `migrations`

| Column | Type | Description |
|---|---|---|
| version | varchar | Migration version (PK) |
| applied_at | timestamp | Applied at |

### Io component diagram

```
Main flow:
IoRunWeb → Request → Router → Controller → Response

Parallel dependencies:
              ↓           ↓          ↓
Session      Router      Logger     Cache
              ↓           ↓          ↓
Account    Controller  FileSystem  FsCache
```

---

## Data flows

### Authentication

```
INPUT_EMAIL → SENT_CODE → INPUT_CODE → DONE
```

### Account save

```
POST data → Updater → Validation → Errors?

Yes → JSON error
No  → Account::flush → EntityLog (diff logging)
```

### Async loading

```
Parallel kickoff:
  Account::get(1)->readDbAsync()
  Account::get(2)->readDbAsync()
  Account::get(3)->readDbAsync()
  ... other work ...

DbPool::pollFinishAll() → data is available
```

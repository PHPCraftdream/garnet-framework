# SQL Escaping Hardening Review — Async DB Query Path

**Scope:** `Kernel/Db/Query/QueryTools.php` (`escapeSqlParam()`, `buildSql()`,
`fieldVal()`, `fieldValIn()`) and its callers on the async path
(`Kernel/Db/Link/DbPool.php::queryAsync()`, `Kernel/Db/Link/DbMySQLiLink.php::queryAsync()`).

**Status:** research only. No source was modified. This document is advisory.
Every payload result below was produced by **actually running the real functions
from this repo** under PHP 8.1.7 (the local CLI), not by reasoning alone. Where a
claim about MySQL server behavior could not be executed here (no live MySQL was
driven for these specific mode tests), it is explicitly flagged as **believed,
not independently verified in this environment**.

**Design decision honored:** the escaping-based approach is *kept*. mysqli's
`MYSQLI_ASYNC` mode has no prepared-statement execution
(`mysqli_stmt::execute()` cannot run asynchronously — only `mysqli::query()` on a
raw string can), so the async path *must* interpolate escaped values into a raw
string. The recommendations below harden that design; none of them proposes
ripping it out.

---

## 1. Executive summary

The current escaping is **fundamentally sound for the common case** and delegates
to `mysqli_real_escape_string()` against a `utf8mb4` connection on the real async
path — which is the correct, charset-aware primitive. `mb_check_encoding(..., 'UTF-8')`
correctly rejects invalid UTF-8 and classic multi-byte (GBK/Big5/SJIS) smuggling
byte sequences (verified: both return `""`).

Ranked by real-world exploitability:

| # | Issue | Applies? | Severity | Exploitability |
|---|---|---|---|---|
| **F1** | **Cross-pass placeholder re-substitution in `buildSql()`** — a value substituted by the `?` pass that contains a `:name` colliding with a later named arg key gets **re-expanded** by the second (`:name`) pass, breaking the string-literal boundary and emitting attacker-influenced text **outside** quotes. | **YES** | **High** | Conditional but real: needs a positional value whose content contains `:someKey` where `someKey` is also a named arg in the same call. Mixed positional+named calls with user-controlled string values are the trigger. |
| **F2** | `sql_mode` is never set/enforced on connect; `NO_BACKSLASH_ESCAPES` and `ANSI_QUOTES` are left to server default. | Conditionally (depends on server config) | Medium–High if a deployment enables either mode | Not exploitable on default MySQL, but the code silently *depends* on the default and has no guard. |
| **F3** | Values are wrapped in **double quotes** (`"..."`), which breaks (or changes meaning) under `ANSI_QUOTES`. | Conditionally | Medium (breakage) / High (if it lets a value parse as an identifier) | Same as F2 — server-config-dependent. |
| **F4** | Numeric fast-path returns **unescaped** values for `is_numeric()` inputs — leading/trailing whitespace and `"1e309"` pass through. | YES (behavioral) | Low | All numeric values are double-quoted in the final SQL (buildSql always wraps in `"`...`"`), so the unescaped/verbatim behavior is not an injection vector. The `"1e309"` → `INF` robustness issue affects only direct float-to-string coercion, not the string numeric path used in production. |
| **F5** | The no-`$link` fallback escaper assumes an ASCII-safe connection charset. | YES (by design) | Low | The real async path always passes `$link`; `fieldVal()`/`fieldValIn()` accept an optional `$link` param but have zero production callers passing it (only test code). |
| GBK / multi-byte smuggling | **Does not apply** — `mb_check_encoding` + guaranteed `utf8mb4` connection. | No | — | UTF-8 is self-synchronizing; verified `""` return. |
| NULL / control chars | Handled correctly (fallback table mirrors mysqli's). | No issue | — | Verified. |

**The single most important finding is F1** — it is the only item that is a
*latent injection/corruption vector in the code as written*, independent of server
configuration.

---

## 2. Findings

### F1 — Cross-pass placeholder re-substitution in `buildSql()` (HIGH)

**Applies: YES.** `buildSql()` (QueryTools.php:277-318) runs **two sequential
`preg_replace_callback` passes on the same string**: first `/[?]/` (positional),
then `/:([a-zA-Z_][a-zA-Z0-9_]*)/` (named). The second pass re-scans the **entire
string produced by the first pass**, including the text that the first pass just
interpolated. If a positionally-substituted value contains a `:name` substring
that collides with a named argument key present in the same `$args` array, the
`:name` pass replaces that substring — **inside an already-closed string literal**
— with the named value, wrapped in its own `"..."`. This breaks the literal
boundary and emits attacker-influenced content as raw SQL outside quotes.

**Real execution (from this repo's actual `buildSql`):**

```
buildSql("SELECT ? , :id", [0 => "inject :id here", "id" => 99])
  => SELECT "inject "99" here" , "99"
```

The `:id` inside the first value was rewritten to `"99"`, yielding
`"inject "99" here"` — the `99` sits **outside** the string literal. With an
attacker-controlled named value (rather than the integer `99`) this is a
string-break primitive. A second reproduction:

```
buildSql("WHERE a = ? AND b = :role", [0 => ":role injected", "role" => "SECRET"])
  => WHERE a = ""SECRET" injected" AND b = "SECRET"
```

Here `""SECRET" injected"` — `SECRET` escaped the intended literal.

**Why `?`-in-a-value is safe but `:name`-in-a-value is not:** the `?` pass uses a
stateful `$index` counter and only reacts to literal `?` characters *present in
the template at scan time*; `preg_replace_callback` does not re-scan its own
replacement output within a single call, so a `?` inside a substituted value is
inert (verified: `buildSql("WHERE a = ? AND b = ?", ["has?mark","second"])` →
`WHERE a = "has?mark" AND b = "second"`). The vulnerability is specifically the
**second, separate** pass re-reading the first pass's output. A `:name` that
appears in a value with **no matching key** stays literal (verified:
`"text :role more"` with no `role` key → unchanged), so the trigger requires a
key collision.

**Severity: High** — string-literal escape is the whole point of the escaper; F1
defeats it for a specific, reachable input shape. It is *conditional* (needs mixed
positional+named args where a positional string value contains `:existingKey`),
which is why it is not "critical/unconditional", but it is a genuine
injection/corruption vector present in the code today.

**Hardening (keeps the escaping design):** make the two passes non-re-entrant so
neither pass can act on the other's output. Concretely, do a **single tokenizing
pass** over the *original* template that recognizes `?` and `:name` placeholders
and substitutes each exactly once, appending literal spans and substituted values
to an output buffer — instead of two chained `preg_replace_callback` calls over
the mutating string. (`patchArgsIndexed()` already uses a single combined
`#(\?)|(:(\w+))#` regex in one pass and does not have this defect — the same
structure should be applied to `buildSql()`.) This is a behavior-preserving
refactor for all non-colliding inputs.

### F2 — `sql_mode` never enforced (`NO_BACKSLASH_ESCAPES`) (MEDIUM–HIGH, config-dependent)

**Applies: conditionally.** Grep across `Kernel/Db/**` and every `db.ini`
(framework templates, `TestsInit/TestConfig`) found **no `sql_mode`,
no `SET SESSION sql_mode`, and no `NO_BACKSLASH_ESCAPES`**
anywhere. `DbPool::newLink()` (DbPool.php:110-131) sets only `set_charset('utf8mb4')`
and optionally runs `options[MYSQL_ATTR_INIT_COMMAND]`, which in every config is
`SET NAMES 'utf8mb4'` — never a `sql_mode` change.

**The hazard (believed, per MySQL docs; not driven against a live server here):**
under `NO_BACKSLASH_ESCAPES`, MySQL does **not** treat `\` as an escape character
inside string literals. `mysqli_real_escape_string()` (and the fallback table)
escape a quote as `\'`. Under `NO_BACKSLASH_ESCAPES` the server reads `\'` as a
literal backslash followed by an **unescaped** quote, which closes the string
early — turning escaped input into an injection. This is the classic reason
escaping is only safe when backslash-escaping is active on the server.

**Assessment for this codebase:** on a default MySQL/MariaDB install
`NO_BACKSLASH_ESCAPES` is **off**, so the code is safe *by default*. But nothing
in the code *guarantees* it — a hardened corporate MySQL, a managed DB with a
non-default `sql_mode`, or an `init_command` in a future config could enable it
and silently make the escaper bypassable.

**Hardening:** on connection init in `DbPool::newLink()`, explicitly assert the
mode. Either (a) run `SET SESSION sql_mode = REPLACE(@@sql_mode, 'NO_BACKSLASH_ESCAPES', '')`
(and likewise strip `ANSI_QUOTES`, see F3) right after `set_charset`, or (b) read
`@@session.sql_mode` once and **throw a `DbException`** if either dangerous mode is
present, refusing to run rather than silently emitting bypassable SQL. Option (b)
is lower-risk (no behavior change on correctly-configured servers, loud failure on
misconfigured ones).

### F3 — Double-quote value wrapping breaks under `ANSI_QUOTES` (MEDIUM, config-dependent)

**Applies: conditionally.** `buildSql()`, `fieldVal()`, and `fieldValIn()` wrap
every interpolated value in **double quotes** (`'"' . escape($v) . '"'`) —
verified in real output, e.g. `fieldVal("name","O'Brien")` → `` `name` = "O\'Brien" ``.

**The hazard (believed, per MySQL docs; not driven live here):** with `ANSI_QUOTES`
enabled, a double-quoted token is parsed as an **identifier** (like a backtick
name), *not* a string literal. Under that mode `WHERE a = "O\'Brien"` would try to
resolve `O'Brien` as a column/identifier — at best an error ("Unknown column"),
at worst a semantic change. No config in this repo enables `ANSI_QUOTES` (grep
found none in `Kernel/Db/**`), so this is breakage-on-
misconfiguration, not an exploit on default MySQL.

**Interaction with F2:** enforcing/stripping `ANSI_QUOTES` at connect (F2's
remedy) also neutralizes F3. Alternatively, switching value wrapping from `"..."`
to `'...'` (single quotes) would make the interpolation robust under `ANSI_QUOTES`
*and* is the more conventional SQL string delimiter — but that is a wider change
touching `buildSql`/`fieldVal`/`fieldValIn` and their expected outputs, so it
should be weighed against F2's connect-time guard. **Recommend the connect-time
guard first** (smaller blast radius); consider single-quote wrapping as a
follow-up only if there's appetite for touching the interpolation format.

### F4 — Numeric fast-path returns string input verbatim & unquoted (LOW–MEDIUM)

**Applies: YES (behavioral).** `escapeSqlParam()` (QueryTools.php:243-245) does
`if (is_numeric($value)) return (string)$value;` **before** any escaping/quoting,
and `buildSql()` then interpolates that return *without* wrapping it — no, wait:
`buildSql` always wraps in `"..."`. The subtle point is that the value is returned
**unescaped**, and for a *string* input `(string)$value === $value` verbatim.

PHP's `is_numeric()` edge cases (all verified under PHP 8.1.7):

| Input | `is_numeric` | `escapeSqlParam` returns | Note |
|---|---|---|---|
| `" 1 "` | **true** | `" 1 "` (verbatim, whitespace incl. leading/trailing) | trailing `\n`/space preserved |
| `"1\n"` | **true** | `"1\n"` | newline preserved inside numeric |
| `"1e309"` | **true** | `"1e309"` | overflows to `INF` when used as a float |
| `"0777"` | true | `"0777"` | leading zero kept (octal-looking) |
| `".5"`, `"+1"`, `"0e0"` | true | verbatim | |
| `"1 OR 1=1"` | **false** | escaped normally | space between `1` and `OR` ⇒ not numeric ⇒ safe |
| `"0x1A"`, `"0b101"`, `"1_000"` | **false** | escaped normally | hex/binary/underscore literals are NOT `is_numeric` in modern PHP — safe |

**Injection assessment:** the dangerous historical worry ("a numeric fast-path
lets `1 OR 1=1` through") **does not apply** — `is_numeric("1 OR 1=1")` is `false`
(interior whitespace disqualifies it), so it is escaped. The characters
`is_numeric()` *does* accept (`0-9 . e + - `, leading/trailing whitespace) cannot
form SQL metacharacters that break out of a numeric context. **However:**

- **Whitespace-carrying "numbers"** (`" 1 "`, `"1\n"`) pass through verbatim.
  Inside `buildSql`'s `"..."` wrapping they're harmless (`" 1 "` → `" 1 "` inside
  quotes), but if `escapeSqlParam` were ever used to build an *unquoted* numeric
  context (e.g. `LIMIT $n`), the trailing whitespace/newline is a latent
  surprise. In the current call sites everything is double-quoted, so this is
  low severity.
- **`"1e309"` → `INF`**: passing the *float* `1e309` (not the string) yields
  `(string)1e309 === "INF"`, an **invalid SQL token** → query failure. The string
  `"1e309"` stays `"1e309"` (fine as a quoted literal). This is a robustness bug,
  not injection.

**Hardening:** make the numeric fast-path stricter — e.g. only fast-path values
that match a canonical numeric regex `^-?(?:0|[1-9]\d*)(?:\.\d+)?$`
(no whitespace, no exponent, no leading zeros) and fall through to normal
escaping+quoting otherwise. Or simplest: **always quote and escape** even numeric
strings (they're valid inside `"..."` and MySQL coerces), removing the fast-path's
special surface entirely — a very small, low-risk change that also fixes the
`INF` case. Note the `is_bool` branch below it is **load-bearing** (verified
`is_numeric(true)===false`, and `(string)false===""` — so `false` must be mapped
to `'0'` explicitly, which the branch does); do **not** remove it.

### F5 — No-`$link` fallback assumes ASCII-safe charset (LOW)

**Applies: YES, by design.** The fallback branch (QueryTools.php:261-265) is used
only when no `mysqli` link is supplied. The real async path
(`DbPool::queryAsync` → `buildSql($sql, $args, $link->getMysqli())`) **always**
supplies the link, so the charset-aware `real_escape_string` branch is what runs
in production. The fallback is reached only by `fieldVal`/`fieldValIn` (which call
`escapeSqlParam($value)` with no link) and by any misuse. Its escape table mirrors
mysqli's (NUL, `\n`, `\r`, `\`, `'`, `"`, Ctrl-Z — verified byte-for-byte in
output) and is correct **as long as the connection charset is an ASCII-superset**
(utf8mb4/utf8/latin1), which every config guarantees.

**Hardening:** thread `$link` into `fieldVal`/`fieldValIn` too, so they use the
connection-aware escaper on the async path rather than the assumption-based
fallback. Low priority — safe under the current utf8mb4 guarantee.

### Multi-byte / GBK smuggling — **DOES NOT APPLY**

`escapeSqlParam` calls `mb_check_encoding($value, 'UTF-8')` and returns `""` for
anything that is not valid UTF-8. Verified real output: the classic GBK sequence
`\xbf\x27 OR 1=1` and invalid UTF-8 `\xC3\x28` both return **`""`** (empty). UTF-8
is self-synchronizing (continuation bytes are always `10xxxxxx`, distinct from
lead bytes and from ASCII `'`/`\`), so the GBK-style "trailing byte + backslash
reinterpreted as a multi-byte char" trick cannot form a valid UTF-8 string that
also smuggles an unescaped quote. **Important caveat:** this safety depends on the
*connection* charset actually being utf8mb4 (which `DbPool::newLink` enforces via
`set_charset('utf8mb4')`) — the `mb_check_encoding` check validates the PHP
string's encoding, and the `set_charset` call ensures the server escapes/parses
with the matching charset. Both halves are present. **No change needed**, but the
dependency (validation-charset must equal connection-charset) is worth a code
comment.

### NULL byte / control characters — no issue

Verified: `"a\x00b"` → `a\0b`, `"a\nb"` → `a\nb`, `"a\rb"` → `a\rb`,
`"a\x1ab"` → `a\Zb`. Matches mysqli's table. Fine.

---

## 3. Recommended hardening changes (prioritized; advisory only)

| Pri | Change | Where | Why | Regression risk of the change itself |
|---|---|---|---|---|
| **P0** | Rewrite `buildSql()` as a **single tokenizing pass** (recognize `?` and `:name` on the original template, substitute each once, never re-scan substituted output). Model it on `patchArgsIndexed()`'s single combined regex. | `QueryTools::buildSql` | Fixes **F1**, the only config-independent injection/corruption vector. | Medium — output for *non-colliding* inputs must remain byte-identical; needs the attack-test suite in §4 plus the existing behavior locked by tests. The `:name` two-key lookup (`$key` then `':' . $key`) must be preserved. |
| **P1** | At connect, **assert or normalize `sql_mode`**: strip/refuse `NO_BACKSLASH_ESCAPES` and `ANSI_QUOTES`. Prefer *read-and-throw* (`DbException`) if present, over silently `SET`ting. | `DbPool::newLink` (right after `set_charset`) | Fixes **F2** and **F3** together; makes the escaper's server-side assumptions explicit and loud instead of silent. | Low — no behavior change on default servers; adds one round-trip at connect. A `SET`-based variant is slightly riskier (mutates session state) than the read-and-throw variant. |
| **P2** | Tighten the **numeric fast-path**: only fast-path canonical integers/decimals (regex, no whitespace/exponent), otherwise escape+quote; or drop the fast-path and always quote+escape. Keep the `is_bool` branch. | `QueryTools::escapeSqlParam` | Fixes **F4** (`INF`, whitespace-carrying numerics). | Low — but changes the emitted SQL for numeric args from unquoted-inside-quotes to escaped-inside-quotes; verify no call site relies on the exact numeric form. |
| **P3** | Thread `$link` into `fieldVal`/`fieldValIn` so they use the connection-aware escaper. | `QueryTools::fieldVal/fieldValIn`, callers | Fixes **F5**; removes the fallback assumption on the async path. | Low–Medium — signature change; update all callers. |
| **P3** | Add a code comment at the `mb_check_encoding` site documenting that its safety depends on the connection charset (set in `DbPool::newLink`) matching the validated encoding. | `QueryTools::escapeSqlParam` | Prevents a future refactor from breaking the multi-byte guarantee silently. | Negligible. |

**Do not** switch the async path to prepared statements (impossible under
`MYSQLI_ASYNC`) — all of the above preserve the escaping architecture.

---

## 4. Attack-attempt test cases (ready to translate into a Kahlan spec)

All "current output" columns below are **real output** captured by running the
actual `QueryTools` functions from this repo under PHP 8.1.7. Escaper outputs are
via the **fallback branch** (no `$link`); the production `real_escape_string`
branch produces equivalent results for these ASCII/UTF-8 payloads. Notation:
outputs shown literally (not JSON-escaped).

### 4a. `escapeSqlParam($v)` — string escaping

| # | Payload (`$v`) | Goal | Expected safe output | Current output | Verified? |
|---|---|---|---|---|---|
| E1 | `hello` | baseline | `hello` | `hello` | ✅ pass |
| E2 | `O'Brien` | break single-quote literal | `O\'Brien` | `O\'Brien` | ✅ pass |
| E3 | `' OR '1'='1` | classic quote injection | `\' OR \'1\'=\'1` | `\' OR \'1\'=\'1` | ✅ pass |
| E4 | `" OR "1"="1` | break double-quote literal (wrapping is `"`) | `\" OR \"1\"=\"1` | `\" OR \"1\"=\"1` | ✅ pass |
| E5 | `a\b` (single backslash) | backslash escape | `a\\b` | `a\\b` | ✅ pass |
| E6 | `\'` (backslash+quote) | escape-the-escape | `\\\'` | `\\\'` | ✅ pass |
| E7 | `a\x00b` (NUL) | NUL smuggle | `a\0b` | `a\0b` | ✅ pass |
| E8 | `a\nb`, `a\rb`, `a\x1ab` | control chars | `a\nb`, `a\rb`, `a\Zb` | as expected | ✅ pass |
| E9 | `1'; DROP TABLE users;--` | stacked-query / comment | `1\'; DROP TABLE users;--` (quote neutralized; note mysqli::query async does NOT run multi-statements) | `1\'; DROP TABLE users;--` | ✅ pass |
| E10 | `\xbf\x27 OR 1=1` (GBK) | multi-byte quote smuggle | `` (empty — rejected by `mb_check_encoding`) | `` (empty) | ✅ pass |
| E11 | `\xC3\x28` (invalid UTF-8) | invalid-encoding smuggle | `` (empty) | `` (empty) | ✅ pass |
| E12 | `true` (bool), `false` (bool) | bool coercion | `1`, `0` | `1`, `0` | ✅ pass (and `is_bool` branch confirmed reachable) |

### 4b. `buildSql($sql, $args)` — interpolation

| # | Template + args | Goal | Expected safe behavior | Current output | Verified? |
|---|---|---|---|---|---|
| B1 | `... name = ? AND age = ?`, `["O'Brien", 30]` | positional escaping | `... name = "O\'Brien" AND age = "30"` | `... name = "O\'Brien" AND age = "30"` | ✅ pass |
| B2 | `... name = :name AND role = :role`, `["name"=>'a" OR "1"="1',"role"=>"admin"]` | named escaping | `... name = "a\" OR \"1\"=\"1" AND role = "admin"` | matches | ✅ pass |
| B3 | `... a = ? AND b = ?`, `["has?mark","second"]` | `?` inside a value must NOT be recounted | `... a = "has?mark" AND b = "second"` | matches | ✅ pass |
| **B4** | `SELECT ? , :id`, `[0=>"inject :id here","id"=>99]` | **`:name` inside a positional value must NOT be re-expanded (F1)** | **`SELECT "inject :id here" , "99"`** (value kept literal) | **`SELECT "inject "99" here" , "99"`** ← **BUG** | ❌ **FAIL (F1)** |
| **B5** | `WHERE a = ? AND b = :role`, `[0=>":role injected","role"=>"SECRET"]` | **string-break via re-substitution (F1)** | **`WHERE a = "\:role injected" AND b = "SECRET"`** (literal preserved) | **`WHERE a = ""SECRET" injected" AND b = "SECRET"`** ← **BUG** | ❌ **FAIL (F1)** |
| B6 | `WHERE a = ?`, `[0=>"text :role more"]` (no `role` key) | `:name` with no matching key stays literal | `WHERE a = "text :role more"` | matches | ✅ pass |
| B7 | `WHERE a = :x`, `["x"=>"hi :x recurse"]` | named value containing its own key not re-expanded within one pass | `WHERE a = "hi :x recurse"` | matches | ✅ pass |
| B8 | `WHERE a = ? AND b = :n`, `[0=>null,"n"=>null]` | NULL → keyword, not `"NULL"` | `WHERE a = NULL AND b = NULL` | matches | ✅ pass |
| B9 | `WHERE a = ?`, `[0=>" 1 "]` | whitespace-numeric verbatim (F4) | ideally quoted+escaped; today unescaped-but-quoted | `WHERE a = " 1 "` | ⚠️ passes safely (quoted) but see F4 |
| B10 | `WHERE a = ?`, `[0=>"1e309"]` | exponent numeric string | `WHERE a = "1e309"` (string stays fine) | `WHERE a = "1e309"` | ✅ pass (but the **float** `1e309` → `INF`, F4) |

> **B4/B5 are the assertions that should FAIL against the current code and PASS
> after the P0 fix.** They are the regression tests that lock in the F1 remedy.

### 4c. `fieldVal` / `fieldValIn`

| # | Call | Expected safe output | Current output | Verified? |
|---|---|---|---|---|
| V1 | `fieldVal("name","O'Brien")` | `` `name` = "O\'Brien" `` | matches | ✅ pass |
| V2 | `fieldVal("cnt", 5)` | `` `cnt` = "5" `` | matches | ✅ pass |
| V3 | `fieldValIn("id", [1,"2",'a" OR 1=1'])` | `` `id` IN ("1", "2", "a\" OR 1=1") `` | matches | ✅ pass |

### 4d. Config/mode cases (require a live MySQL to assert server-side — not run here)

| # | Setup | What to assert | Status |
|---|---|---|---|
| M1 | Connect with `SET SESSION sql_mode='NO_BACKSLASH_ESCAPES'`, then run `buildSql`-built SQL with payload `O'Brien` | With the P1 guard: connection init throws `DbException` (or strips the mode). Without it: the escaped `\'` breaks the literal. | **VERIFIED via end-to-end integration tests (DbPoolSqlModeGuardIntegrationSpec.php):** Real DbPool::newLink() calls with dangerous init commands via SET SESSION (no SUPER privilege required) correctly throw DbException mentioning NO_BACKSLASH_ESCAPES. Tests exercise the actual guard code path, not a mock. Verified against live MySQL server. |
| M2 | Connect with `sql_mode='ANSI_QUOTES'`, run `fieldVal("name","x")` | With P1 guard: refuse/normalize. Without: `"x"` parsed as identifier → error. | **VERIFIED via end-to-end integration tests (DbPoolSqlModeGuardIntegrationSpec.php):** Real DbPool::newLink() calls with dangerous init commands via SET SESSION (no SUPER privilege required) correctly throw DbException mentioning ANSI_QUOTES. Tests exercise the actual guard code path, not a mock. Verified against live MySQL server. |

---

## 5. Open questions

1. **F1 fix vs. exact-output contracts.** Existing specs (and possibly app code)
   may assert `buildSql`'s exact string output. The P0 single-pass rewrite must
   reproduce byte-identical output for every non-colliding input. Before
   implementing, enumerate current `buildSql` output expectations across the repo
   so the refactor can be proven behavior-preserving. **Needs a human sign-off on
   the acceptable output format** (the two-pass artifacts like the `?`-then-`:name`
   ordering must not be relied upon elsewhere).

2. **P1: strip vs. throw.** Should a misconfigured `sql_mode`
   (`NO_BACKSLASH_ESCAPES`/`ANSI_QUOTES`) be **silently normalized** (`SET SESSION`)
   or cause a **loud `DbException`**? Throwing is safer (no hidden session
   mutation, fails fast) but breaks deployments that legitimately need those modes
   for other reasons. Architectural decision required.

3. **Double-quote vs. single-quote wrapping (F3).** Converting all value wrapping
   to single quotes would be more idiomatic and `ANSI_QUOTES`-robust, but is a
   broader change than the connect-time guard and would alter every emitted SQL
   string (and its tests). Is there appetite for that, or is the P1 guard
   sufficient? Recommend guard-first.

4. **F4 numeric fast-path removal.** Is any caller depending on numeric args being
   emitted *unquoted* (e.g. someone reading `buildSql` output and expecting a bare
   number)? If not, always-quote-and-escape is the simplest robust fix. Confirm no
   such dependency before changing.

5. **Live-server verification of M1/M2.** The `NO_BACKSLASH_ESCAPES` /
   `ANSI_QUOTES` behaviors are asserted here from MySQL documentation knowledge,
   **not** executed against a running server in this session. Before relying on the
   P1 remedy, these should be reproduced against the actual target MySQL/MariaDB
   version(s) the project deploys on, since exact mode semantics can vary between
   MySQL and MariaDB and across versions.

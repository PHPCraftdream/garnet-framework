# Idempotency

Server-side **idempotency keys** so client retries on flaky networks
don't double-charge, double-post, or double-anything. Tag your
mutating POST with a header; the framework collapses identical
retries into a single controller execution.

## What's here

| File / subdir | What it does |
|---|---|
| `IdempotencyMiddleware.php` | The middleware. Add it to mutating routes and forget about double-submits. |
| `Tables/FwIdempotencyKeys.php` | Append-only key store with `(account_id, key, route)` triples + stored response. |
| `Spec/` | Kahlan specs for first-hit / replay / concurrent-replay semantics. |

## Wire-up

```php
// MyApp.php → init()
IdempotencyMiddleware::setTableClass(MyApp\Common\Tables\IdempotencyKeys::class);
```

Then mount the middleware on every mutating route:

```php
$router->add('/checkout/~confirm', [
    CheckoutController::class,
    [[IdempotencyMiddleware::class, 'process']],
    '~confirm',
]);
```

## Client side

The client sends a header on the first POST:

```http
POST /checkout/~confirm HTTP/1.1
Content-Type: application/json
X-Idempotency-Key: 4f9c8e6b-b7d3-4a8a-9b1d-7f5d3a8a2e1d

{ "cart_id": 12, "amount": 9900 }
```

The key is a UUID-style string the client generates **once per logical
action** — typically when the user clicks the button. The retry loop
on the client sends the same key.

## Lifecycle

```
First hit                  Replay hit (within TTL)
─────────                  ──────────────────────
1. Middleware sees key.    1. Middleware sees the same triple.
2. CAS-insert row with     2. The row is already there.
   status="in_flight".     3. If status="done" → middleware
3. Lets the controller        emits the stored response, no
   run.                       controller call.
4. Stores response,        4. If status="in_flight" → middleware
   marks status="done".       returns 409 Conflict ("still
                              processing"); client should retry
                              again after a short backoff.
```

Triple = `(account_id, key, route)`. Different routes with the same
key don't collide; an unauthenticated request uses `account_id = 0`.

## Garbage collection

The keys table grows monotonically. The cleanup is a cron task:

```
* * * * * cd /var/www/myapp && php garnet idempotency:gc
```

`idempotency:gc` deletes rows older than the configured TTL (default
48h). Long enough to cover real network retries, short enough to
keep the table small.

## When not to use

- Reads (GET) — already idempotent by HTTP spec; no need.
- Bulk endpoints that the client breaks into many small calls — apply
  per chunk, not on the wrapper.
- Endpoints where the user genuinely wants to repeat (e.g. "send
  another reminder email"). Idempotency turns repeats into no-ops;
  if that's wrong, skip it.

## Don't

- Don't let two different controllers share a key — accidental cross-
  route replay reads as a privilege issue.
- Don't store the entire client payload in the response — only what
  you'd have responded with normally. The table can blow up otherwise.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../Invite/README.md`](../Invite/README.md) — same DI shape (`setTableClass` pattern).

---

↑ Back to [Bundle / Modules](../../README.md)

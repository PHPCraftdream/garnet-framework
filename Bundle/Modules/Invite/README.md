# Invite

Invite-token registration. Closed-signup apps use this to let new
users in through links containing a single-use (or limited-use) token.

## What's here

| File / subdir | What it does |
|---|---|
| `FwInviteTokenService.php` | The single public entry point. `issue()`, `validate()`, `consume()`. Apps pin their concrete tables once. |
| `Tables/FwInviteTokens.php` | Token store: `token`, `uses_left`, `expires_at`, optional `role_hint`, `created_by`. |
| `Tables/FwInviteRegistrations.php` | Audit row per consumption: `token`, `account_id`, `consumed_at`. |
| `Spec/` | Kahlan specs covering issue / validate / consume + concurrency. |

## Pin your tables at boot

Apps subclass the abstract tables (so they can pin the table name)
and tell the service once:

```php
// MyApp.php → init()
FwInviteTokenService::setTableClasses(
    MyApp\Common\Tables\InviteTokens::class,
    MyApp\Common\Tables\InviteRegistrations::class,
);
```

## Issue a token

```php
$token = FwInviteTokenService::issue(
    usesLeft:  1,                              // single-use
    expiresAt: time() + 7 * 86400,             // a week
    createdBy: $admin->id(),
    meta:      ['role' => 'expert'],
);
// → cryptographic 32-char string
```

Use the token in a URL: `https://app.example.com/system/invite~<token>`.

## Validate and consume

The signup controller asks the service whether a token is good, and on
successful registration calls `consume()` in the same transaction as
the `Account::insert()`. The service uses **compare-and-swap** on
`uses_left` so two parallel clicks can't both pass.

```php
if (!FwInviteTokenService::validate($token)) {
    return $this->error('Invalid or expired invite.');
}

DbPool::get()->transaction(function () use ($token, $form) {
    $accountId = Account::createFromForm($form);
    FwInviteTokenService::consume($token, $accountId);
});
```

## What `consume()` does atomically

1. `UPDATE invite_tokens SET uses_left = uses_left - 1 WHERE token = ?
   AND uses_left > 0` — the CAS step. Returns `0 affected rows` if
   somebody else just consumed the last slot.
2. Append a row to `invite_registrations` recording who consumed it.
3. If `uses_left` drops to 0, mark the token as exhausted (a derived
   column for reporting; not strictly needed for correctness).

## Bulk issue

`issue()` runs in a loop — call it N times. There is no batch insert
because each token's `created_at` and randomness matter; the cost is
negligible at sane batch sizes (<1000).

## Token format

Tokens are URL-safe (no `+`, `/`, `=`). They're stored verbatim
(no hashing). Reasoning: the value travels in plaintext in the URL
anyway, and a leaked token is already used up the moment somebody
clicks it. If you change your mind, swap the storage by overriding
`hashToken()` in your subclass.

## Don't

- Don't validate without consuming. A separate `validate()` then
  `consume()` is two round-trips; under load, the gap is exploitable.
  Either call them inside one transaction (preferred) or call
  `consume()` and act on its return value.
- Don't put the token in the email body without an unsubscribe-tracking
  redirect layer if the email goes through a transactional mail
  service — some providers rewrite URLs into trackers and break the
  token.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../Auth/README.md`](../Auth/README.md) — magic-link flow the registration lands on.
- [`../Idempotency/README.md`](../Idempotency/README.md) — same DI shape (`setTableClasses` pattern).

---

↑ Back to [Bundle / Modules](../../README.md)

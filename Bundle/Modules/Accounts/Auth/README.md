# Auth

Passwordless email **magic-link** authentication. No passwords stored,
no recovery flow, CSRF and brute-force mitigations baked in.

## What's here

| Subdir | What it does |
|---|---|
| `Controllers/` | The HTTP entry points — `AuthController` handles the request → email → code → session loop. |
| `AuthStrategy/` | The state machine: `INPUT_EMAIL` → `INPUT_CODE` → authenticated. Each phase is a strategy class so the controller stays thin. |
| `Middlewares/` | `EmailAuthMiddleware` — enforces the registration gate (`registrationsEnabled` setting + same-domain carve-out for admin@). |

## How the flow works

```
1. User types email at /system/                  → POST INPUT_EMAIL
2. Backend generates a 6-digit code, stores
   (code, email, expires_at, tries_left), emails
   it via FwEmailQueueService.
3. User types the code                           → POST INPUT_CODE
4. Backend verifies code, marks the row used,
   creates / fetches the Account, opens a Session.
5. Page reloads as the authenticated user.
```

All four POSTs hit the same `/system/` URL — the state machine reads
the current phase from the session, picks a strategy, and renders the
right form.

## Security knobs

- **Code TTL**: configurable in `app.ini` (default 10 minutes).
- **Tries**: each code has a small bounded `tries_left` counter; once
  exhausted the row is invalidated and the user starts over.
- **Rate limiting**: `RateLimit/` (under `Kernel/Io/`) blocks repeated
  email-send attempts from the same IP and from the same email.
- **CSRF**: every POST carries `__GARNET_CSRF__` (typed into the form
  by the bundled JS); `Csrf::check()` runs in middleware.
- **Same-site cookies**: `Lax` by default so magic-link clicks from
  webmail land authenticated; explained in `Kernel/Db/Entity/Session/Session.php`.
- **Same-domain carve-out**: when registrations are off, an
  `admin@<your-base-url>` address is still admitted. Lets the operator
  reach the admin console after locking the front door.

## Customising the magic-link email

Override `TwigTemplates/Email/MagicLink.twig` in your app — the
framework's copy is the fallback. The `code`, `email`, `brand_name`,
`expires_minutes` variables are passed in.

## When to extend

- If you need a non-email second factor (TOTP, WebAuthn), add a new
  strategy under `AuthStrategy/` and a corresponding form phase. The
  state machine is built for it.
- If you need a different identifier (phone, username), think twice —
  almost everything Garnet apps do assumes email. The right move is
  usually a separate "alias" entity, not replacing the email.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../../../docs/cookbook/send-an-email.md`](../../../docs/cookbook/send-an-email.md) — the mailer path the codes go through.
- [`../SystemSettings/README.md`](../SystemSettings/README.md) — `registrationsEnabled` flag.

---

↑ Back to [Bundle / Modules](../../README.md)

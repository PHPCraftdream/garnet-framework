# Email

`symfony/mailer` + a queue table + a cron-driven runner. Apps call
`FwEmailQueueService::queue(...)` from anywhere; the runner takes care
of the SMTP roundtrip.

## What's here

| File / subdir | What it does |
|---|---|
| `FwEmailQueueService.php` | The single public entry point. `queue()` schedules; `flushPending()` drains the queue once. |
| `Tables/` | `FwEmailQueue` (pending + sent rows), `FwEmailDeadLetter` (rows that exhausted retries). |
| `Spec/` | Kahlan specs for queueing semantics and retry policy. |

## SMTP config

`<App>/WorkDir/Config/email.ini`:

```ini
enabled = 1
scheme  = "smtps"
host    = "smtp.example.com"
port    = 465
user    = "noreply@example.com"
password = "…"
from    = "noreply@example.com"
verify_peer = 1
```

`enabled = 0` short-circuits sends in development — the queue still
records what would have gone out, useful for inspecting Twig output.

## Send

```php
use PHPCraftdream\Garnet\Bundle\Modules\Email\FwEmailQueueService;

FwEmailQueueService::queue(
    to: 'alice@example.com',
    subject: $t->Email_Welcome_Subject($brand),
    twigTemplate: 'Email/Welcome.twig',
    params: ['firstName' => 'Alice'],
);
```

Internals:

1. Render the Twig template into a body string.
2. Insert a row into `email_queue` with `status = pending`.
3. Return.

The web request is not held up by the SMTP handshake — that happens in
the cron runner.

## Drain

```php
FwEmailQueueService::flushPending();
```

The dev shortcut. In production a [Cron](../Cron/README.md) task runs
this every minute. The runner:

1. Picks the oldest `status = pending` rows (configurable batch).
2. Sends each via `symfony/mailer`.
3. On success → `status = sent`, `sent_at = NOW()`.
4. On transient failure → `tries += 1`, reschedule (exponential
   back-off).
5. After N retries → move to `email_dead_letter` and notify
   `support_contact_email` (from `SystemSettings`).

## Templates

Live under `<App>/Foreground/TwigTemplates/Email/` or
`Bundle/TwigTemplates/Email/`. Each extends `Email/LayoutPlain.twig`
or `Email/LayoutBrand.twig`. `brand_name` is always available.

Use [`docs/cookbook/localise-strings.md`](../../../docs/cookbook/localise-strings.md)
to pull `%s` into subjects safely.

## Don't

- Don't `FwEmailQueueService::queue()` inside a transaction you might
  roll back — the row would still be there. Queue **after** the
  transaction commits, or use the framework's transactional outbox
  helper.
- Don't send from a controller's request-handling path expecting it to
  happen "now". The user already got their response by the time SMTP
  runs.

## Related

- [`../../../docs/cookbook/send-an-email.md`](../../../docs/cookbook/send-an-email.md) — recipe.
- [`../Cron/README.md`](../Cron/README.md) — the runner.
- [`../SystemSettings/README.md`](../SystemSettings/README.md) — brand, support contacts.

---

↑ Back to [Bundle / Modules](../../README.md)

# Send an email

The framework ships `Bundle/Modules/Email/` on top of `symfony/mailer`.
SMTP credentials come from `email.ini`, templates from Twig under
`TwigTemplates/Email/`, and queueing is a thin table-backed loop the
cron runner drains.

## Configure SMTP once

`<App>/WorkDir/Config/email.ini`:

```ini
enabled  = 1
scheme   = "smtps"
host     = "smtp.example.com"
port     = 465
user     = "noreply@example.com"
password = "…"
from     = "noreply@example.com"
verify_peer = 1
```

The framework reads these at boot; nothing else touches the file at
runtime.

## Send right now

```php
use PHPCraftdream\Garnet\Bundle\Modules\Email\FwEmailQueueService;

FwEmailQueueService::queue(
    to: 'alice@example.com',
    subject: 'Welcome',
    twigTemplate: 'Email/Welcome.twig',
    params: ['firstName' => 'Alice'],
);
```

The call writes a row into the email queue table; the next `php garnet
cron` tick drains it (see below). For an immediate send during dev,
`FwEmailQueueService::flushPending()` runs the loop once.

## Write the Twig template

`TwigTemplates/Email/Welcome.twig`:

```twig
{% extends 'Email/LayoutPlain.twig' %}

{% block body %}
  <p>Hi {{ firstName }},</p>
  <p>Welcome to {{ brand_name }}.</p>
{% endblock %}
```

`brand_name` is injected automatically from `FwAppSettings::brandName()`.

## Queue and cron

Mail goes through a queue table so a slow SMTP relay can't tank a web
request. The runner:

1. The queue table is `email_queue` (per-app prefix).
2. `php garnet cron` picks up pending rows in FIFO order, sends them via
   `symfony/mailer`, marks them sent or — on failure — increments
   `tries` and reschedules.
3. After N retries (default 5) a row is moved to `email_dead_letter`
   for inspection.

Run cron from your scheduler (`crontab -e`, systemd timer, GitHub
Actions):

```
* * * * * cd /path/to/app && php garnet cron >> WorkDir/Logs/cron.log 2>&1
```

## i18n in subject lines

Subjects are i18n keys with `%s` interpolation handled by the engine —
**never** by `sprintf`. Pass arguments through the i18n call:

```php
use PHPCraftdream\Garnet\Bundle\I18n\I18nFramework;

$t = I18nFramework::getInstance();
FwEmailQueueService::queue(
    to: $email,
    subject: $t->Email_BookingCreated_Subject($brand),  // %s replaced inside tr()
    twigTemplate: 'Email/BookingCreated.twig',
    params: [...],
);
```

## Related

- [`../i18n.md`](../i18n.md) — translation pipeline.
- [`../../Bundle/Modules/Email/README.md`](../../Bundle/Modules/Email/README.md) — service / table reference.
- [`../../Bundle/Modules/Cron/README.md`](../../Bundle/Modules/Cron/README.md) — runner internals.

---

↑ Back to [Cookbook](README.md)

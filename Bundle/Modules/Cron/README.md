# Cron

Scheduled tasks. App-defined commands run on a chosen cadence from a
single host invocation: `php garnet cron`. Idempotent — running it
twice in the same minute drains the queue once.

## What's here

| Subdir | What it does |
|---|---|
| `Tables/` | `FwCronLog` — every run records the task, start/end time, status, output snippet. Used by the admin viewer. |
| `Spec/` | Kahlan specs for scheduling + the run loop. |

## Define a cron task

Cron tasks are app-level CLI commands (see
[`../../../docs/cookbook/add-a-cli-command.md`](../../../docs/cookbook/add-a-cli-command.md))
that declare a schedule. Add `cronSchedule()` to a `CMD…` class:

```php
class CMDFlushEmailQueue extends BaseCmdRun
{
    public function name(): string { return 'email:flush'; }

    public function cronSchedule(): string { return '* * * * *'; }  // every minute

    public function run(): void
    {
        FwEmailQueueService::flushPending();
    }
}
```

`cronSchedule()` returns a five-field crontab expression (no
`*/N`-only minimums — fully supported). Tasks without a `cronSchedule()`
override are only runnable on-demand from `php garnet <name>`; cron
ignores them.

## Run it

```
* * * * * cd /var/www/myapp && php garnet cron >> WorkDir/Logs/cron.log 2>&1
```

One host-level minute tick. The framework looks at the current time,
finds every registered task whose schedule matches, runs them sequentially,
records the outcome in `cron_log`.

## What you get for free

- **Per-task locking** — if a previous run is still going (long
  task, host overload) the next tick skips it instead of stacking up.
- **Log table + admin viewer** — the
  [Logging](../Logging/README.md) bundle surfaces cron history in the
  admin panel, with output and duration per run.
- **Failure mail** — when a task throws, the runner records the
  exception and (when configured) queues a notification to the
  `support_contact_email` from `SystemSettings`.

## Conventions

- Cron tasks should be **idempotent**: re-running on the same data
  should be a no-op. The framework will not magically deduplicate.
- Avoid long tasks (>1 minute) — split them, use a queue, or run on
  a less-frequent schedule.
- Anything that writes user-visible data should `Log::write` so the
  admin can see what changed.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../../../docs/cookbook/add-a-cli-command.md`](../../../docs/cookbook/add-a-cli-command.md) — on-demand commands.
- [`../Email/README.md`](../Email/README.md) — the email queue runner is a typical cron task.

---

↑ Back to [Bundle / Modules](../../README.md)

# Add a CLI command

Garnet exposes a single `php garnet <name>` entry point. There are two
levels at which a command can live: **framework-level** (every app gets
it — build, serve, deploy, …) and **app-level** (only the active app
knows about it — your own seeders, maintenance jobs, business one-offs).

## App-level command (the common case)

App commands live under `<App>/Common/Commands/CMD<Name>.php` and
inherit from `BaseCmdRun`. Garnet's CLI sees them through the app's
`run_cmd.php` and `IoRunConsole` — no extra registration required.

```php
<?php declare(strict_types=1);

namespace PHPCraftdream\MyApp\Common\Commands;

use PHPCraftdream\Garnet\Kernel\Io\IoRun\BaseCmdRun;

class CMDHelloWorld extends BaseCmdRun
{
    public function name(): string
    {
        return 'hello';
    }

    public function description(): string
    {
        return 'Print a friendly greeting.';
    }

    public function run(): void
    {
        $name = $this->arg(0) ?? 'world';
        echo "Hello, {$name}!" . PHP_EOL;
    }
}
```

```bash
php garnet hello             # → Hello, world!
php garnet hello Garnet      # → Hello, Garnet!
```

Helpers available on `BaseCmdRun`:

| Method | Returns |
|---|---|
| `$this->arg(int $n)` | nth positional argument (or `null`) |
| `$this->opt(string $name)` | `--name=value` value (or `null`) |
| `$this->hasFlag(string $name)` | `true` if `--name` is present |
| `$this->confirm(string $q)` | typed-token confirmation prompt |

## Framework-level command

Framework commands live under `Kernel/Io/GarnetCli/Garnet<Name>Command.php`
and are dispatched from `GarnetRunner::main()`. Adding one means
extending the `match` in `GarnetRunner` — fine for the framework
itself, **never appropriate for an app**. If you find yourself wanting
this, the answer is almost always an app-level command instead.

The existing framework commands (`GarnetBuildCommand`,
`GarnetServeCommand`, `GarnetDeployDiffCommand`, …) are good
templates. Each is a static class with a single `public static function
run(array $args): void` entry point.

## Cron tasks

If your command needs to run on a schedule rather than on-demand,
register it through the [`Cron`](../../Bundle/Modules/Cron/README.md)
bundle instead. Cron tasks are CLI commands the framework runs from
`php garnet cron` at a chosen cadence.

## Related

- [`../cli.md`](../cli.md) — every framework CLI command in full.
- [Add a CLI bundle](add-a-bundle.md) — package the command with templates and migrations.

---

↑ Back to [Cookbook](README.md)

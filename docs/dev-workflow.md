# Development workflow

How to develop the Garnet framework and your application together.

## Use case

You're building an app on top of `phpcraftdream/garnet-framework`, and you've hit
a limitation in the framework itself — a missing helper, a bug in the router, a
component you want to add. You need to edit framework code and have your app
pick it up instantly, without round-tripping through a Packagist release.

## Setup: side-by-side checkouts with a path repository

Put both projects in sibling directories:

```
~/dev/
├── garnet-framework/          # cloned from github.com/PHPCraftdream/garnet-framework
└── my-app/                    # your application
```

In `my-app/composer.json` add a path repository pointing at the framework
checkout:

```json
{
    "require": {
        "phpcraftdream/garnet-framework": "@dev"
    },
    "repositories": [
        {
            "type": "path",
            "url": "../garnet-framework",
            "options": {"symlink": true}
        }
    ]
}
```

Then:

```bash
cd my-app
composer update phpcraftdream/garnet-framework
```

Composer creates `my-app/vendor/phpcraftdream/garnet-framework/` as a symlink
(or NTFS junction on Windows) to `~/dev/garnet-framework/`. Every edit you
make in the framework is immediately visible in the app — no `composer
update`, no cache to clear.

## Daily cycle

```bash
# Terminal A — frontend watch
cd my-app
php garnet build:watch        # rebuilds on every TSX/CSS change

# Terminal B — PHP server
php garnet serve              # http://localhost:8001

# Terminal C — anything else (migrations, REPL, tests)
```

Edit framework code in `~/dev/garnet-framework/Kernel/`, `Bundle/`, etc.
Reload the browser — changes are live. PHP files take effect on next request
(or after `php garnet cache` if OPcache is enabled in dev).

## Running framework tests against your app

```bash
cd ~/dev/garnet-framework
composer test                 # kahlan unit specs — no app context
```

If you want to confirm a framework change doesn't break your app:

```bash
cd ~/dev/my-app
composer cs:check             # PHP-CS-Fixer dry run
composer phpstan              # static analysis
# plus whatever app-level tests you have
```

## Switching back to a published version

When the framework change you needed lands in a tagged release on Packagist:

```bash
cd my-app
# 1. remove the `repositories` block from composer.json
# 2. pin to the published version
composer require phpcraftdream/garnet-framework:^1.2
```

Composer drops the symlink and pulls the real package from Packagist.

## Tips

- **Do NOT commit `composer.lock` with `@dev` references** in a release branch
  — that ties your CI to a local path that doesn't exist on the build server.
  Keep the path repo in a `dev`/`local` branch, or use Composer's
  `auth.json`/`config.platform` to override per-machine.
- **Auto-prefer dev branches**: `"minimum-stability": "dev", "prefer-stable":
  true` lets you `composer require` `@dev` without flipping global stability.
- **Composer cache eats path-repo metadata sometimes** — if `composer update`
  doesn't see your latest framework change, `composer clear-cache && composer
  update phpcraftdream/garnet-framework`.

## Contributing back

If your framework change is a fix or a feature that benefits everyone, open a
PR against `phpcraftdream/garnet-framework`. See `CONTRIBUTING.md` in the
framework repo for the contribution flow.

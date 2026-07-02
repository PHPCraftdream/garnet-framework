# Add a bundle

A **bundle** is a self-contained module of functionality — routes,
services, Twig templates, frontend assets, table gateways. The framework
itself is built out of bundles (`Bundle/Modules/Auth`,
`Bundle/Modules/Comments`, …); you add your own the same way.

## Skeleton

A bundle is just a directory with a class that extends
`BaseBundleInit`. The minimum:

```
MyBundle/
└── MyBundle.php
```

```php
<?php declare(strict_types=1);

namespace PHPCraftdream\MyApp\MyBundle;

use PHPCraftdream\Garnet\Bundle\BaseBundleInit;

class MyBundle extends BaseBundleInit
{
    public function init(): void
    {
        // Register Twig template dir, services, etc.
        parent::init();
    }

    public function namespace(): string
    {
        return __NAMESPACE__;
    }

    public function bundleDir(): string
    {
        return __DIR__;
    }
}
```

## Hook it into the app

In `<App>/<App>.php` (your app's main class), add the bundle to the
list returned by `bundles()`:

```php
protected function bundles(): array
{
    return [
        // existing bundles...
        MyBundle::class,
    ];
}
```

That's it. On the next request:

- `MyBundle/TwigTemplates/` (if present) is added to the Twig loader.
- `MyBundle/Front/` (if present) is picked up by `php garnet prepare` and
  rspack builds its TSX/CSS.
- `MyBundle/Common/Tables/*` and any `BaseAppInit` integrations boot.

## Conventions inside a bundle

- **`Common/`** — services, table gateways, entity helpers usable by
  Foreground and Dashboard.
- **`Foreground/Controllers/`** — public-facing controllers.
- **`Dashboard/Controllers/`** — admin-only controllers (always behind
  `moderatorOnly` / `adminOnly` middleware).
- **`Front/Islands/`** — React islands lazy-loaded into pages.
- **`TwigTemplates/`** — Twig under `Layout/`, `Email/`,
  `Components/`, scoped by bundle name.
- **`Migrations/Items/M_NNNN.php`** — DB migrations (numbered).

## Test a bundle

Specs go alongside the code under `Spec/` directories:

```
MyBundle/
├── Common/
│   └── Services/
│       ├── Foo.php
│       └── Spec/
│           └── FooSpec.php
```

Run with `composer test:bundle` (the script picks up `Bundle/**/Spec/`).
Use the same `--spec` pattern in your app's composer.json for app-level
specs.

## Related

- [Add a route](add-a-route.md) — wire URLs to the bundle's controllers.
- [`../bundle.md`](../bundle.md) — full bundle reference.

---

↑ Back to [Cookbook](README.md)

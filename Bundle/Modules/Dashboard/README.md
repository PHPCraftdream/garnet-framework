# Dashboard

The admin-panel scaffold. Gives the framework a single canonical
admin shell so every bundle's admin page picks up the same chrome,
menus and role-gating without writing a layout per page.

## What's here

| Subdir | What it does |
|---|---|
| `Controllers/FwDashboardController.php` | The abstract base every admin controller extends. Owns side-menu, top-menu, role-gate plumbing. |
| `Spec/` | Kahlan specs for the shell. |

## What you get by extending it

```php
namespace PHPCraftdream\MyApp\Dashboard\Controllers;

use PHPCraftdream\Garnet\Bundle\Modules\Dashboard\Controllers\FwDashboardController;

class ReportsController extends FwDashboardController
{
    protected static function isAllowed(): bool
    {
        return AccountHelper::isModerator();
    }

    public function get__index(GlobalReqParams $g, RouterUriParams $u): ResponseInterface
    {
        return $this->renderTwig('Dashboard/reports.twig', [
            'rows' => $this->loadReports(),
        ]);
    }
}
```

For free:

- **Role gate.** `isAllowed()` runs before every action; failure returns
  the framework's `403` page rather than rendering the controller.
- **Layout shell.** `renderTwig` inside a dashboard controller wraps
  the body in `Layout/AdminLayout.twig`. Side-menu, top-menu, the
  active-link highlight, breadcrumbs — all set up automatically.
- **Active-item detection.** The shell knows which menu item is
  "current" from the URL, so submenus stay open and highlights stay
  right.

## Configuring menus

Each app's main dashboard controller (`Dashboard/Controllers/AdminController.php`)
declares the side and top menu arrays once. Sub-controllers inherit
those arrays via the framework's menu registry.

```php
protected static function sideMenu(string $url): array
{
    return [
        ['label' => $t->Admin_Users(),    'href' => '/admin/users/',   'icon' => 'users'],
        ['label' => $t->Admin_Reports(),  'href' => '/admin/reports/', 'icon' => 'bar-chart'],
        // …
    ];
}
```

The menu builder enforces an i18n contract: labels come from `t->…()`,
not hard-coded strings.

## Role hierarchy

The dashboard recognises four built-in roles (defined in
`Kernel/Db/Entity/Account/Account.php`): `IS_OWNER`, `IS_ADMIN`,
`IS_MODERATOR`, `IS_APPROVED`. Apps add domain-specific roles by
extending Account and exposing helpers (`AccountHelper::isExpert()`).
The dashboard layer only knows about the framework four.

`isAllowed()` returns a plain `bool` so apps can compose:
`$user->isAdmin() && $user->hasFeatureFlag('beta')`.

## Don't

- **Don't render admin pages without going through `FwDashboardController`.**
  Bypassing it gives you no shell, no role gate, no menu state. There
  are no shortcuts here — if you need a one-off internal page,
  subclass with a stub `sideMenu()` returning `[]`.
- **Don't put business logic in the controller.** Move it to
  `Common/Services/*`; the controller is for routing + role gate +
  passing typed arrays to Twig.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../Logging/README.md`](../Logging/README.md) — uses dashboard controllers for `/admin/logs/`.
- [`../SystemSettings/README.md`](../SystemSettings/README.md) — owner-only settings page sits on this shell.

---

↑ Back to [Bundle / Modules](../../README.md)

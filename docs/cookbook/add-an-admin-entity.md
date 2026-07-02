# Add an admin entity

You have a new DB entity (say, a `Product`) and want an admin CRUD
page for it — grid, edit form, validation, role gates — without
hand-rolling selects, paging, saving and diff-logging yourself.

The framework's only *ready-made* Template Method controller for this
today is `FwAccountsController`, and it's wired to one entity:
`accounts`. There is no generic "any entity" admin controller yet — if
your new entity isn't the accounts table, you'll write your own
Template Method controller modeled on the same shape (skeleton in the
abstract base, specifics in an `IEntityConfig`), or — for anything with
custom tabs, filters, or cross-table joins — skip `IEntityConfig`
entirely and hand-roll the page on `AdminGrid` + `GridConfig` /
`PaginationHelper` the way the framework's own bigger admin pages do.
Both paths are shown below; read the "Which path" section before
picking one.

## Which path

- **Reusing the accounts grid pattern for the accounts table itself**
  (e.g. an app subclassing `FwAccountsController` for its own user
  admin page) → follow steps 1-4 below as-is.
- **A genuinely new entity** (products, bookings, anything that isn't
  `accounts`) → `IEntityConfig` is *possible* (nothing in the interface
  ties it to accounts) but there's no framework base controller that
  drives arbitrary tables through it yet — you'd write your own
  abstract controller with the same list → detail → save → delete
  skeleton as `FwAccountsController`, swapping `Account::getAccounts()` /
  `Account::get($id)` for your own `DbTable` calls. If your page needs
  tabs, joins across tables, or bespoke filters (most real admin pages
  do), it's usually less code to skip `IEntityConfig` and build directly
  on `AdminGrid` + `GridConfig::make()` on the frontend and
  `PaginationHelper` on the backend — see
  `Bundle/Modules/Auth/Controllers/FwAccountsController.php` vs. a
  hand-rolled example like `DashboardBookingsController` in an app for
  the contrast.

The rest of this recipe walks the `IEntityConfig` path end to end,
since that's the piece with no existing cookbook coverage.

## 1. Define the DB table

An entity needs a `DbTable` gateway. `DbTable` subclasses declare their
schema via `init(): ITableBuilderDriver` and are abstract so an app can
customise them (add columns, override enum values) before wiring the
concrete table name via `DbTableBuilderFactory::newCreateTable`.

```php
<?php declare(strict_types=1);

namespace PHPCraftdream\MyApp\Common\Tables;

use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

class Products extends DbTable
{
    protected string $primaryKey = 'id';

    public static function init(): ITableBuilderDriver
    {
        return DbTableBuilderFactory::newCreateTable(table: static::get())
            ->addIdColumn()
            ->addColumn(column: 'title', type: 'VARCHAR', length: '255', null: false)
            ->addColumn(column: 'price', type: 'INT', length: '11', null: false, default: '0')
            ->addColumn(column: 'is_published', type: 'TINYINT', length: '1', null: false, default: '0')
            ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
            ->addIndex(indexName: 'is_published', indexes: ['is_published']);
    }
}
```

See a real framework example at
[`Bundle/Modules/Balance/Tables/FwBalanceLedger.php`](../../Bundle/Modules/Balance/Tables/FwBalanceLedger.php)
for the same pattern with an `ENUM` column and a static helper method.
Register the table's migration the usual way — see
[`../database.md`](../database.md).

## 2. Implement `IEntityConfig`

`IEntityConfig` (`Kernel/Interfaces/Db/IEntityConfig.php`) is the
declarative contract that separates "what is this entity" from "how do
I CRUD any entity":

```php
interface IEntityConfig {
    public static function getEntityConfig(): IEntityConfig;

    public function idField(): string;
    public function getFieldsInfo(array $fields = null): array;
    public function getGridInfo(): array;

    public function selectFields(): array;
    public function manageFormFields(): array;
    public function manageGridFields(): array;
    public function viewFields(): array;
    public function editFields(): array;
    public function dataFields(): array;

    public function patchItem(array &$item): array;
    public function saveOne(array $postData, array $fields, ?SaveFilesParams $saveFiles = null): SaveEntityResult;
}
```

You don't implement all of this by hand — `BaseEntity`
(`Kernel/Db/Entity/BaseEntity/BaseEntity.php`) is the abstract base
that already implements `getEntityConfig()`, `getGridInfo()`,
`filterKeys()` and `saveOne()` (validation + optional photo upload via
`Updater::validateByFieldsInfo` / `processUploadPhoto`). You subclass it
and override the field lists plus `getFieldsInfo()`. The only real
implementation in the framework today is `AccountEntity`
(`Kernel/Db/Entity/Account/AccountEntity.php`), extended by
`UserEntityConfig` in IRabi — read both together, the subclass shows
what an app is expected to add on top.

```php
<?php declare(strict_types=1);

namespace PHPCraftdream\MyApp\Foreground\Params;

use PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity\BaseEntity;
use PHPCraftdream\MyApp\Foreground\I18n\ForegroundI18n;

class ProductEntityConfig extends BaseEntity
{
    public function selectFields(): array
    {
        return ['id', 'title', 'price', 'is_published', 'created_at'];
    }

    public function manageGridFields(): array
    {
        return ['id', 'title', 'price', 'is_published'];
    }

    public function manageFormFields(): array
    {
        return ['id', 'title', 'price', 'is_published', 'photo'];
    }

    public function viewFields(): array
    {
        return ['id', 'title', 'price'];
    }

    public function editFields(): array
    {
        return ['id', 'title', 'price', 'is_published'];
    }

    public function getFieldsInfo(array $fields = null): array
    {
        $t = ForegroundI18n::getInstance();

        $result = [
            'id' => ['name' => 'id', 'readOnly' => true],
            'title' => [
                'type' => 'input',
                'name' => $t->Product_TitleLabel(),
                'validation' => ['required', 'maxLen[255]'],
            ],
            'price' => [
                'type' => 'input',
                'name' => $t->Product_PriceLabel(),
                'validation' => ['required', 'int', 'minVal[0]'],
            ],
            'is_published' => [
                'name' => $t->Product_PublishedLabel(),
                'type' => ['bool' => $t->Product_PublishedLabel()],
            ],
            'photo' => [
                'name' => $t->Product_PhotoLabel(),
                'type' => 'photo',
                'cropInfo' => 'crop_info',
                'cropName' => 'photo_cropped',
                'uploadPath' => 'products/{id}/',
            ],
        ];

        return $this->filterKeys($result, $fields);
    }

    public function patchItem(array &$item): array
    {
        return $item;
    }
}
```

Notes grounded in the real code, not guessed:

- **Field types are not a fixed enum.** `'type'` is either a plain
  string consumed as a UI hint (`'input'`, `'textarea'`, `'unix_time'`
  are the ones the shipped `AccountEntity`/`UserEntityConfig` use), or
  an **associative array** whose single key names the widget and whose
  value is the widget's data: `['bool' => $label]` for a checkbox,
  `['time_zone' => timezone_identifiers_list()]` for a timezone picker,
  `['map' => [['value' => …, 'text' => …], …]]` for a select built from
  an explicit option list. `'photo'` is the one type the *backend*
  actively special-cases — `Updater::validateByFieldsInfo()` skips
  validation for it, and `Updater::processUploadPhoto()`
  (`Kernel/Io/Forms/Updater.php`) handles the pending → commit upload +
  optional crop via `uploadPath`/`cropInfo`/`cropName`. Everything else
  is a rendering hint for the app's own grid/form island — the
  framework doesn't ship a generic admin grid React component in this
  repo, so double-check what your app's island actually understands
  before inventing a new type string.
- **Validation** reuses the same `fieldsInfo`/`Updater` machinery as
  regular forms — see
  [`add-validation-rules.md`](add-validation-rules.md) for the full
  validator list (`required`, `maxLen[n]`, `int`, `in_array[...]`, …)
  instead of re-deriving it here.
- **`dataFields()`** lists EAV keys (stored in a separate
  `*_data` table, e.g. account flags) — return `[]` if your entity has
  no EAV side table; `BaseEntity`'s default is already `[]`.
- **Role gates are not part of `IEntityConfig`.** There's no
  `isAllowed()`/`canEdit()`/`canDelete()` on the interface. Per-field
  edit gating happens inline via `'readOnly' => …` in `getFieldsInfo()`
  (see `login` being force-readOnly in `UserEntityConfig`, or the
  `id`/flag fields being conditionally readOnly based on
  `$account->isAdmin()`). Page-level access gating is the
  *controller's* job — `FwAccountsController` doesn't gate itself at
  all (the app wires `moderatorOnly` middleware on the route, see step
  4); other framework admin controllers that need finer control
  declare their own `abstract protected static function isAllowed(): bool`
  (e.g. `FwBalanceAdminController`, `FwStaticPagesAdminController`) —
  copy that pattern if a single middleware isn't granular enough.

## 3. Wire a controller

`FwAccountsController` (`Bundle/Modules/Auth/Controllers/FwAccountsController.php`)
is the framework's one shipped Template Method controller over
`IEntityConfig`. It defines list (`get__main`), create
(`post__create_user`), update (`post__save_user`) and delete
(`post__delete_user`), all delegating field lists / validation /
patching to `static::getEntityConfig()`. It also hardcodes the accounts
entity throughout (`Account::get($id)`, `Account::getAccounts()`,
`EntityLog::get()->writeLog('account', …)`) — it is **framework-owned**,
not a generic base for arbitrary app entities.

A concrete subclass only fills in the abstract hooks:

```php
<?php declare(strict_types=1);

namespace PHPCraftdream\MyApp\Dashboard\Controllers;

use PHPCraftdream\Garnet\Bundle\Modules\Auth\Controllers\FwAccountsController;
use PHPCraftdream\Garnet\Kernel\Interfaces\Db\IEntityConfig;
use PHPCraftdream\MyApp\Foreground\Params\Menu;
use PHPCraftdream\MyApp\Foreground\Params\UserEntityConfig;
use PHPCraftdream\MyApp\MyApp;

class DashboardAccountsController extends FwAccountsController
{
    protected static function publicDir(): string
    {
        return MyApp::getInstance()->publicDir;
    }

    protected static function getEntityConfig(): IEntityConfig
    {
        return UserEntityConfig::getEntityConfig();
    }

    protected static function getSideMenu(string $url): array
    {
        return Menu::side($url);
    }

    protected static function getMainMenu(string $url): array
    {
        return Menu::main($url);
    }
}
```

(This is, almost verbatim, IRabi's real
`Apps/IRabi/Dashboard/Controllers/DashboardAccountsController.php`.)

**If your entity isn't accounts**, there's no shortcut — write your own
abstract controller with the same shape (`getEntityConfig()`, list,
save, delete handlers), swapping `Account::get`/`Account::getAccounts`
for `Products::get($id)` / `Products::get()->select(...)`. For anything
beyond simple flat CRUD (tabs, filters, joined lookups), skip
`IEntityConfig` and build the page like
`Apps/IRabi/Dashboard/Controllers/DashboardBookingsController.php`
does: `PaginationHelper::fetchPage()` + a hand-rolled payload builder +
`AdminGrid`/`GridConfig::make()` on the frontend (see
`Framework/Bundle/Front/Common/` per the Reuse-over-reinvention rules)
— that's what the framework's own bigger admin pages (bookings,
finance, balances) actually do, and none of them go through
`IEntityConfig`.

## 4. Register the admin route

Admin/dashboard routes are registered the same way as any other route
(see [`add-a-route.md`](add-a-route.md)) — there's no separate
"admin route" mechanism. The framework CLI's own "Admin panel" section
in [`../cli.md`](../cli.md) is unrelated (`php garnet admin` mints a
one-shot login token; it doesn't register pages). In `<App>/<App>.php` →
`runWebApp()`:

```php
$adminMiddleware = [
    ...$common,
    [UserDataMiddleware::class, 'moderatorOnly'],
];

$router->add(DashboardAccountsController::URL, DashboardAccountsController::class, $adminMiddleware);
```

`moderatorOnly` (or `adminOnly`) is the access gate — see
[`../bundle.md`](../bundle.md) for the full middleware picture and the
"Admin controllers" convention (always behind one of these two).

## Related

- [Add validation rules to a form](add-validation-rules.md) — the
  `fieldsInfo` shape and validator list `getFieldsInfo()` reuses.
- [Add a route](add-a-route.md) — how the admin URL itself gets wired.
- [`../bundle.md`](../bundle.md) — `IEntityConfig` in one paragraph,
  reusable controllers table, admin/public controller separation.
- [`../database.md`](../database.md) — `DbTable` reference.
- The testing guide — Playwright coverage for an admin CRUD page (auth,
  grid, save/delete flows) follows the same per-role login + per-worker
  DB isolation pattern as the rest of the admin panel; see
  [`e2e-testing.md`](../e2e-testing.md).

---

↑ Back to [Cookbook](README.md)

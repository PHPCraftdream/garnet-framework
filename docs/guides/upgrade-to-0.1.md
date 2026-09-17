# Upgrade an alpha application to Garnet 0.1.x

Use this guide when moving an application from the alpha line to the first
stable `0.1.0` line. The alpha83 tag was deleted from the repository, so the
mapping below is the supported migration record rather than a promise that an
old alpha checkout can be reproduced byte-for-byte.

## Namespace moves

Replace old imports with the current symbols:

| Alpha import | 0.1.x import |
|---|---|
| `PHPCraftdream\Garnet\Kernel\Io\Router\Router` | `PHPCraftdream\Garnet\Kernel\Io\Http\Router\Router` |
| `PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools` | `PHPCraftdream\Garnet\Kernel\Io\Http\Router\Controller\ControllerTools` |
| `PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig` | `PHPCraftdream\Garnet\Kernel\Io\Services\IniConfig\IniConfig` |
| `PHPCraftdream\Garnet\Kernel\Io\Services\Logs\Logger` | `PHPCraftdream\Garnet\Kernel\Io\Services\Logs\Logger` |
| `PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams` | `PHPCraftdream\Garnet\Kernel\Interfaces\Core\IGlobalReqParams` |
| `PHPCraftdream\Garnet\Kernel\Interfaces\IRouterUriParams` | `PHPCraftdream\Garnet\Kernel\Interfaces\Web\Router\IRouterUriParams` |

The last two entries moved into the interface layer that matches their
responsibility. Search application code, tests, route arrays and string class
names; PHPStan does not see every class name stored in configuration.

## Composer and generated files

Update the framework requirement to `^0.1.0`, remove a local path repository
from a release application, and run `composer install`. Then run:

```text
php garnet setup
php garnet build
php garnet build:check
composer phpstan
composer cs:check
```

Generated `*Gen.php` files and `Public/assets/**` must come from the same
framework/app checkout. Do not copy generated classes from an alpha build into
a 0.1.x application.

## Public 0.1.x contract

The 0.1.x line covers the current interfaces and extension points under
`Kernel/Interfaces/`, `Kernel/Core/AppInit/`, `Kernel/Db/`, the `Fw*` bundle
classes, the CLI flags, the bundle directory `.env` keys and generated asset
accessors. Adding a method to an interface can break an external
implementation; applications should review custom implementations after every
0.1.x update.

The following remain documented v0.x limitations rather than migration bugs:

- balance ledger values named `booking_invoice`, `booking_payment` and
  `booking_refund`;
- `cancellationPenaltyPercent` in the framework settings module;
- public API changes that require a minor release while the project remains
  below 1.0.

## Release checklist

After migration, verify a clean install in a directory that does not contain
the old framework checkout. Build and boot the app, open the framework admin
panel only in a development directory, and run the application tests against
an isolated database. Keep the old app backup until login, uploads, migrations
and the generated frontend have been checked.

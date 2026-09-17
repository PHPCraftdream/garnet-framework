# Changelog

All notable changes to this project will be documented here.
This project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html);
breaking changes bump the major. While the framework is on `0.x` the public
API is allowed to shift between minor releases — see "Stability" below.

## [Unreleased]

Planned for `1.0`:
- Rename `FwBalanceLedger` enum values from `booking_*` to generic `tx_*`
  with a documented migration path.
- Move `FwAppSettings::cancellationPenaltyPercent` out of the framework into
  an application-side extension point.
- Generalise `AdminAction_booking_*` i18n keys.

## [0.1.0-alpha86] — release hardening

- Rebuilt the shipped admin bundle from current sources.
- Removed the incompatible `aura/sql` 5.x dependency and refreshed the locked
  production dependency graph.
- Sanitised Markdown preview output and removed lodash from runtime frontend
  dependencies.
- Fixed template frontend regression guards and automatic app setup after a
  fresh Composer update.
- Added production dependency audits and a migration guide for alpha apps.

## [0.1.0] — first public release

The framework's first standalone release after extraction from the
internal monorepo that gave rise to it.

Alpha upgrade note: the 0.1.0 API uses the reorganised `Kernel\Io\Http`,
`Kernel\Io\Services` and `Kernel\Interfaces\Core` namespaces. Applications
upgrading from the deleted alpha83 tag must follow
[`docs/guides/upgrade-to-0.1.md`](docs/guides/upgrade-to-0.1.md). The deleted
alpha83 tag cannot be used as a migration baseline; this guide records the
supported symbol moves and the public contract for the 0.1.x line.

### Added
- `phpcraftdream/garnet-framework` Composer package, dual-licensed under MIT or Apache-2.0.
- `vendor/bin/garnet` CLI; a 4-line per-app wrapper drives the same
  `GarnetRunner` from the application directory.
- `php garnet build / serve / prepare / deploy / deploy:diff / bundle /
  cache / migrate:status / app:* / ssh:* / snapshot:*` commands.
- O(1) router with `/path/~method` syntax and middleware pipeline.
- Parallel async MySQL through `DbPool` (`mysqli_poll`).
- Type-safe asset bridge: `*Gen.php` codegen classes for every JS/CSS chunk.
- Bundle architecture (`BaseBundleInit`) with reusable modules — auth,
  CRUD, comments, support, IM, notifications, i18n, file uploads,
  static pages, cron, balance.
- Passwordless email magic-link auth with CSRF protection.
- React-island frontend (`createIsland` + `ErrorBoundary`), tailwind
  v4-friendly theming, Intl-driven date / time helpers.
- Twig templates with strict separation of HTML from PHP.
- CI: phpstan + cs-fixer + kahlan on PHP 8.1 / 8.2 / 8.3.

### Known limitations
See [`README.md` → Known limitations](README.md#known-limitations-v0x).

## Stability

While the version is `0.x`, the public API may change between minor
releases. Treat any of the following as candidates for non-backwards-
compatible change before `1.0`:

- Class names under `Bundle/Modules/.../Fw*.php` if they carry booking-
  domain semantics (see the v1.0 plan above).
- The shape of `appInfo` JSON emitted by `php garnet prepare` if we keep
  generalising the frontend bridge.
- The default fallback values exposed by `FwAppSettings`.

Bug-fix-only releases will not change behaviour. Feature releases will.
`1.0.0` will pin the contract.

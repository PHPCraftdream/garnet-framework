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

## [0.1.0-alpha87] — CI release gate

- Use the Composer audit option supported by the current Composer 2 release.
- Run the short template frontend security and regression guards in CI.

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

## [0.1.3] — no suppressions in React code

0.1.1 and 0.1.2 were released without entries here; see the git history.

### Fixed
- The static-pages admin did not re-render: its components selected the whole
  store with `connectSelection(store, state => state)`, which reads no field and
  subscribes to nothing. The create form kept an empty slug and a disabled
  button. They now select their fields by name.
- Searching the Mail log answered 500 (`Unknown column 'account_name'`):
  `account_name` / `account_login` are hydrated after the query, so the search
  covers the real `mail_log` columns only.

### Changed (apps that pass a class string must adapt)
- `className` / `errorClassName` are gone from the Radix wrappers (`Dialog`,
  `Select`, `Tooltip`), `Combobox`, `Dropzone`, `UncontrolledForm`,
  `UncontrolledInput`, `UncontrolledSelect`, `UncontrolledTextarea`,
  `AdminUserLink`. Each renders its own functional class.
- Callers that need their own Function pass a template element instead of a
  string: `UserLink anchor={<a className="…" />}`,
  `EntityHistoryButton button={<button className="…" />}`,
  `DateInput field={<input className="…" />}`. The class stays a literal at the
  call site, where `require-function-class` checks it. A Function given this way
  replaces the framework's, it is not appended to it.
- Navigation links are marked with `data-hot-click` / `data-no-hot` attributes;
  the `hot-click` / `no-hot` classes keep working.
- `Validators` (grid validators) is a plain object instead of a class of statics.

### Tooling
- `require-function-class` is an error for every `.tsx` file, the warnings of the
  `recommended` preset are errors, and `carburetor-lint` runs with
  `--deny-warnings`. No suppression directive is left in the React sources.

## [0.1.4] — every Carburetor linter on

All of the `house-style` preset is an error now, next to `recommended`: `require-tsdoc`,
`max-line-length` (120), `one-export-per-file`, `no-parent-import`,
`prefer-readonly-array-param`, `no-blank-line-after-tsdoc`,
`tsdoc-blank-line-before-tags` and `no-module-level-store`. `oxlint` runs with
`--deny-warnings`. The React sources (TS/TSX) of the framework and of `Templates/Application` pass
them all without a suppression directive.

### Changed (apps must adapt their imports)
- `one-export-per-file`: a module that exported several values is split into one
  file per export, named after the export. The old multi-export paths are gone,
  and there are no re-export barrels. Names and behaviour are unchanged. Old
  path → new path:
  - `@common/Api/maintenance503`: `isMaintenanceError` → `@common/Api/isMaintenanceError`
  - `@common/Components/Admin/AdminPanel/UserDetailContext`: `useOpenUser` → `@common/Components/Admin/AdminPanel/useOpenUser`
  - `@common/Components/Admin/EntityHistory/entityHistoryFormat`: `renderHistoryValue` → `@common/Components/Admin/EntityHistory/renderHistoryValue`
  - `@common/Components/Admin/StaticPages/StaticPagesAdminIsland/types`: `STATIC_TABS` → `@common/Components/Admin/StaticPages/StaticPagesAdminIsland/values/staticTabs`; `SNIPPET_TYPES` → `@common/Components/Admin/StaticPages/StaticPagesAdminIsland/values/snippetTypes`; `BLOCK_TYPE_LABELS` → `@common/Components/Admin/StaticPages/StaticPagesAdminIsland/values/blockTypeLabels`; `SNIPPET_TYPE_LABELS` → `@common/Components/Admin/StaticPages/StaticPagesAdminIsland/values/snippetTypeLabels`
  - `@common/Components/Admin/SystemSettings/SystemSettingsPage/Tabs/GeneralTabs`: `RegistrationTab` → `@common/Components/Admin/SystemSettings/SystemSettingsPage/Tabs/RegistrationTab`; `PenaltyTab` → `@common/Components/Admin/SystemSettings/SystemSettingsPage/Tabs/PenaltyTab`; `ContactsTab` → `@common/Components/Admin/SystemSettings/SystemSettingsPage/Tabs/ContactsTab`
  - `@common/Components/Form/FormBuilder/validation`: `parseValidation` → `@common/Components/Form/FormBuilder/parseValidation`
  - `@common/Components/Form/useFormErrors`: `FormErrorProvider` → `@common/Components/Form/internal/FormErrorProvider`; `FormErrorStore` → `@common/Components/Form/internal/FormErrorStore`
  - `@common/Components/Layout/PageHeader`: default `PageHeader` → `@common/Components/Layout/PageHeader`
  - `@common/Components/UserPreviewModal/PreviewContext`: `usePreview` → `@common/Components/UserPreviewModal/usePreview`
  - `@common/Components/ui/Dialog`: `DialogTrigger` → `@common/Components/ui/dialog/DialogTrigger`; `DialogClose` → `@common/Components/ui/dialog/DialogClose`; `DialogContent` → `@common/Components/ui/dialog/DialogContent`; `DialogHeader` → `@common/Components/ui/dialog/DialogHeader`; `DialogTitle` → `@common/Components/ui/dialog/DialogTitle`; `DialogDescription` → `@common/Components/ui/dialog/DialogDescription`
  - `@common/Components/ui/Select`: `SelectGroup` → `@common/Components/ui/select/SelectGroup`; `SelectValue` → `@common/Components/ui/select/SelectValue`; `SelectTrigger` → `@common/Components/ui/select/SelectTrigger`; `SelectContent` → `@common/Components/ui/select/SelectContent`; `SelectItem` → `@common/Components/ui/select/SelectItem`; `SelectLabel` → `@common/Components/ui/select/SelectLabel`
  - `@common/Components/ui/Tooltip`: `TooltipProvider` → `@common/Components/ui/tooltip/TooltipProvider`; `TooltipTrigger` → `@common/Components/ui/tooltip/TooltipTrigger`; `TooltipContent` → `@common/Components/ui/tooltip/TooltipContent`
  - `@common/Dom/FormTool`: `FormTool` → `@common/Dom/Component/FormTool`
  - `@common/Dom/Nav/DocumentPrefetch`: `prefetchDocument` → `@common/Dom/Nav/DocumentPrefetch/prefetchDocument`; `fetchDocumentPrefetched` → `@common/Dom/Nav/DocumentPrefetch/fetchDocumentPrefetched`; `initDocumentPrefetchTriggers` → `@common/Dom/Nav/DocumentPrefetch/initDocumentPrefetchTriggers`
  - `@common/Dom/Nav/HotClickInit`: `hotClickHandler` → `@common/Dom/Nav/hotClickHandler`; `hotClickInit` → `@common/Dom/Nav/hotClickInit`
  - `@common/Dom/updateProgress`: `updateProgress` → `@common/Dom/Progress/updateProgress`
  - `@common/Support/Errors/JsErrorReporter`: `reportHandledError` → `@common/Support/Errors/reportHandledError`; `installJsErrorReporter` → `@common/Support/Errors/installJsErrorReporter`
  - `@common/Utils/Data/liveCounts`: `LiveCounts` → `@common/Utils/Data/liveCounts/LiveCounts`; `startLiveCounts` → `@common/Utils/Data/liveCounts/startLiveCounts`; `subscribeLiveCounts` → `@common/Utils/Data/liveCounts/subscribeLiveCounts`; `refreshLiveCounts` → `@common/Utils/Data/liveCounts/refreshLiveCounts`
  - `@common/Utils/Data/pagination`: `PAGE_SIZE_OPTIONS` → `@common/Utils/Data/pagination/PAGE_SIZE_OPTIONS`; `DEFAULT_PAGE_SIZE` → `@common/Utils/Data/pagination/DEFAULT_PAGE_SIZE`; `readPageSize` → `@common/Utils/Data/pagination/readPageSize`; `writePageSize` → `@common/Utils/Data/pagination/writePageSize`
  - `@common/Utils/Time/DateUtils`: `appLocale` → `@common/Utils/Time/DateUtils/shared/appLocale`; `FormatTsOptions` → `@common/Utils/Time/DateUtils/format/formatTs`; `formatTs` → `@common/Utils/Time/DateUtils/format/formatTs`; `formatTime` → `@common/Utils/Time/DateUtils/format/formatTime`; `formatDateShort` → `@common/Utils/Time/DateUtils/format/formatDateShort`; `formatDateLong` → `@common/Utils/Time/DateUtils/format/formatDateLong`; `tsToInputDate` → `@common/Utils/Time/DateUtils/input/tsToInputDate`; `tsToInputTime` → `@common/Utils/Time/DateUtils/input/tsToInputTime`; `tsToInputDateTime` → `@common/Utils/Time/DateUtils/input/tsToInputDateTime`; `tsToHour` → `@common/Utils/Time/DateUtils/input/tsToHour`; `tsToDow` → `@common/Utils/Time/DateUtils/input/tsToDow`; `weekStartTs` → `@common/Utils/Time/DateUtils/week/weekStartTs`; `addDaysTs` → `@common/Utils/Time/DateUtils/week/addDaysTs`; `resolvedUserTz` → `@common/Utils/Time/DateUtils/tz/resolvedUserTz`; `resolvedBrowserTz` → `@common/Utils/Time/DateUtils/tz/resolvedBrowserTz`; `userTzDiffersFromBrowser` → `@common/Utils/Time/DateUtils/tz/userTzDiffersFromBrowser`; `tzOffsetMinutes` → `@common/Utils/Time/DateUtils/tz/tzOffsetMinutes`
  - `@common/Utils/Time/TzList`: `currentTz` → `@common/Utils/Time/tz/currentTz`; `TzList` → `@common/Utils/Time/tz/tzList`
  - `@common/Utils/Ui/LucideIcons`: `menuIconMap` → `@common/Utils/Ui/menuIconMap`; `iconSvg` → `@common/Utils/Ui/iconSvg`
  - `@common/Utils/Ui/ThemeManager`: `Theme` → `@common/Utils/Ui/theme/themeTypes`; `getTheme` → `@common/Utils/Ui/theme/getTheme`; `setTheme` → `@common/Utils/Ui/theme/setTheme`; `initTheme` → `@common/Utils/Ui/theme/initTheme`
  - `@common/Utils/Url/staticPageUrl`: `renderMarkdownLinks` → `@common/Utils/Url/renderMarkdownLinks`
  - `@common/hooks/ui/useAsyncIcon`: `AsyncIconState` → `@common/hooks/ui/internal/useAsyncIcon`; `UseAsyncIconOptions` → `@common/hooks/ui/internal/useAsyncIcon`; `AsyncIconControls` → `@common/hooks/ui/internal/useAsyncIcon`; `useAsyncIcon` → `@common/hooks/ui/internal/useAsyncIcon`
  - `@common/hooks/ui/useBodyScrollLock`: `useBodyScrollLock` → `@common/hooks/ui/internal/useBodyScrollLock`
- `@common/Components/Layout/PageHeader` has a named export `PageHeader` only; the
  default export is gone.
- Static-only classes became plain objects with the same call syntax:
  `PageLoader`, `TzList`, `I18nData*` (`export const … as const`). `GridUtils` is a
  namespace with the same members.
- The Radix wrappers keep every sub-component, one per file:
  `@common/Components/ui/{dialog,select,tooltip}/*`; `Dialog`, `Select` and `Tooltip`
  stay in their files.
- `I18nFrontendDumper` (the generator behind `I18nGen`) writes TSDoc and `as const` objects, and a
  JSON-escaped string literal for values with newlines or quotes (the old output
  put a raw line break inside a single-quoted string). Regenerate `I18nGen` with
  `php garnet prepare`.

### Added
- `@/` — an alias for the application's `Front` directory, in the rspack builds
  (`resolve.alias['@']`, admin build included), `tsconfig*` files and
  `Templates/Application`. `@common` and `@framework` are unchanged.
- `no-parent-import` roots: `@/` (application), `@common/`, `@framework/`. Apps can
  turn the rule on with `{"roots": [{"path": "Front", "prefix": "@/"}]}` and
  rewrite `../` imports to `@/…`.

### Tooling
- `Templates/Application` ships the same linter configuration as the framework.
- The `one-export-per-component` override list is gone: no file needs it.
- The smoke guard `pageloader-incoming-state-ids-guard` stubs the new
  `PageLoaderDefaults` import.

## [0.1.5] — session writes no longer deadlock

### Fixed
- Requests that write the same session could fail with MySQL error 1213
  "Deadlock found" on the multi-row `INSERT … ON DUPLICATE KEY UPDATE` into
  `session_data` (a login ended in a 500). The statement takes its row and gap
  locks in `VALUES` order, and the `param` column is case-insensitive
  (`utf8mb4_unicode_ci`), so the index holds `auth_login` before `AUTH_PHASE`
  while the request listed them the other way round. `QueryTools::sortKeysInIndexOrder`
  puts the rows in the order of the index; `SessionData::buildDataRows` and
  `Settings::flushChanged` use it. Data written and behaviour are unchanged; only
  the row order inside one statement differs. Measured on MySQL, two concurrent
  identical upserts of `AUTH_PHASE` and `auth_login`: 9114 deadlocks in 20 000
  tries in byte order, 0 in index order.

### Tooling
- `QueryToolsSortKeysInIndexOrderSpec` and `SessionDataRowsSpec` cover the order;
  the expected order is what MySQL returns for `ORDER BY param`.

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

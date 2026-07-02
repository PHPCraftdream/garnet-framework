# Frontend

Garnet's frontend is **server-rendered Twig with React islands**. The
page ships fast and complete; each interactive widget is a lazy chunk
that hydrates in place. No SPA, no global router, no double rendering.

This document is a tour of how that fits together — the build, the
asset bridge, the islands, and the conventions that keep the two
sides honest.

## Contents

- [Stack](#stack)
- [Big-picture flow](#big-picture-flow)
- [Build pipeline](#build-pipeline)
- [Asset bridge (`*Gen.php`)](#asset-bridge-genphp)
- [Islands](#islands)
- [Shared library (`@common`)](#shared-library-common)
- [Theming](#theming)
- [I18n on the front](#i18n-on-the-front)
- [Server data — globals + props](#server-data--globals--props)
- [Conventions](#conventions)
- [Related](#related)

---

## Stack

- **React 18** — UI library; islands only.
- **TypeScript** — every TSX file is `strict`; no implicit `any`.
- **Rspack** — the bundler. Same config shape as Webpack 5; faster.
- **Tailwind v4** + **Less** — utility CSS plus component classes.
- **Twig** — server-side templating; renders the page shell and drops
  island placeholders.

Versions are pinned in `Framework/FrontBuilder/package.json`.

## Big-picture flow

```
Browser ─── GET /page ──► PHP / Twig
                            │
                            │  Renders Layout/HtmlLayout.twig with:
                            │   • <link rel="stylesheet"> to *.gen.css
                            │   • <script async> to *.gen.js (entry chunks)
                            │   • <div class="…-init" data-props='…'>
                            │     placeholders for each island
                            ▼
Browser ◄── HTML ─── PHP
   │
   ├── Browser fetches the JS/CSS chunks (content-hash cached forever)
   │
   ├── createIsland() finds each placeholder, dynamically imports
   │    its TSX chunk, hydrates the React tree in place.
   │
   └── Subsequent clicks → sendPost(...) → JSON → React state update.
        No full page reload. No SPA either.
```

## Build pipeline

```
Source code            php garnet prepare              php garnet build
────────────           ────────────────────            ────────────────
Bundle/Front/    ──►   Codegen `*Gen.php`        ──►   Rspack compiles
<App>/Front/           (one per asset for each         the entry tree,
                       bundle; type-safe paths).       writes content-
Bundle/I18n/...PHP ─►  Codegen `*I18nGen.ts`           hashed *.gen.js
<App>/.../I18nDataXX   (TS shims of PHP keys).         and *.gen.css
.php                                                   into Public/.
```

`php garnet build` does both `prepare` and the rspack production
build. `php garnet build:watch` keeps rspack in watch mode for dev.

Rspack config lives at `Framework/FrontBuilder/rspack.config.ts`. The
config calls `php garnet prepare` first to read every bundle's
declared entry points + output paths — there's no "discover by
filename" guessing.

## Asset bridge (`*Gen.php`)

After every build, the framework regenerates two PHP files per bundle:

- `<Bundle>JsGen.php` — `public static function islandName(): string`
  returns the content-hashed JS path for each island.
- `<Bundle>CssGen.php` — same for CSS chunks.

```php
use PHPCraftdream\Garnet\Bundle\FrameworkJsGen;
use PHPCraftdream\MyApp\Foreground\ForegroundJsGen;

$layoutParams['top_menu_js']   = FrameworkJsGen::topMenu();
$layoutParams['my_island_js']  = ForegroundJsGen::myIsland();
```

Why this matters:

- **Type-safe.** Every asset is an IDE-autocompletable method. Typos
  fail at PHP parse time, not browser load time.
- **Cache-busted.** Renames embed a content hash; URLs change on every
  body change.
- **Twig stays simple.** Templates just emit the URL string.

These files are generated — gitignored, regenerated on every build.

## Islands

An **island** is a single React component lazy-loaded into a Twig
placeholder. The convention is:

```
Bundle/Front/Islands/MyFeature/
├── MyFeature.tsx          # default export — the component itself
└── MyFeature.entry.tsx    # createIsland wrapper, the entry-point file
```

`.entry.tsx`:

```tsx
import { createIsland } from '@common/Islands/createIsland';

export default createIsland({
    selector: '.my-feature-init',
    lazy: () => import('./MyFeature'),
});
```

Twig drops the placeholder with the props inlined as JSON:

```twig
<div class="my-feature-init" data-props='{{ my_feature_props_json | raw }}'></div>
```

What `createIsland` does for you:

1. Reads `data-props`, parses JSON, passes the result to the component.
2. Wraps the render in an `ErrorBoundary`.
3. Removes the `-init` class after hydration so two passes never run.
4. Code-splits the import so the chunk only ships when the page actually
   renders the placeholder.

Recipe for a fresh island: [`cookbook/add-an-island.md`](cookbook/add-an-island.md).

## Shared library (`@common`)

`@common` resolves to `Framework/Bundle/Front/Common/` via the rspack
alias declared in `FrontBuilder/build/moduleConfig.ts`. Both framework
islands and app islands use it.

| Path | What's there |
|---|---|
| `@common/Api` | `sendPost`, `sendPostFormData`. **The** way to talk to the backend; never `fetch` directly. |
| `@common/Components` | The component zoo: `AdminGrid`, `Banner`, `Calendar`, `ConfirmModal`, `DateInput`, `Form/*`, `Pagination`, `SendButton`, `Toast`, `Modal`, `Drawer`, `EntityHistory*`, `ImageUploadField`, `Navigation/*`, … |
| `@common/hooks` | `useConfirm`, `useSending`, `useCtrlEnter`, `useBodyScrollLock`, `useOpenUser`, … |
| `@common/Islands/createIsland` | The lazy-island factory. |
| `@common/Utils/DateUtils` | `formatTs`, `formatTime`, `formatDateShort`, `formatDateLong` — timezone-aware via `Intl.DateTimeFormat`. |
| `@common/Utils/zodFromFieldsInfo` | Converts the backend `fieldsInfo` shape to a Zod schema. |
| `@common/I18n/I18nFramework` | The generated TS shim of every translation key. |

Before writing anything new, search `@common/Components` for an
existing match. Duplicate components are the #1 source of visual drift
across pages.

## Theming

Strict four-layer hierarchy lives in
`Framework/Bundle/Front/Styles/`:

1. **Palette variables** — `--blue-500`, `--stone-50`. Hex values live
   here only.
2. **Light/dark theme variables** — `--light-link-color`,
   `--dark-link-color`. Bind palette to theme.
3. **Functional target classes** — `bg-surface`, `text-on-surface`,
   `border-default`. What component classes consume.
4. **Component classes** — `.stat-card`, `.chip`, `.btn-icon-round`.
   What JSX/Twig markup uses.

JSX and Twig only ever reference layer 4. Long inline Tailwind
utility chains are a smell — extract a component class in
`components.css`. Full rules: see "Colors, Themes & Component
Classes" in [`../AGENTS.md`](../AGENTS.md).

## I18n on the front

Server-side keys live in PHP. The build dumps them to TypeScript:

```
<Bundle>/I18n/I18nDataRu.php
<Bundle>/I18n/I18nDataEn.php
        │
        │ php garnet prepare (codegen)
        ▼
<Bundle>/I18nGen/I18nDataRU.ts
<Bundle>/I18nGen/I18nDataEN.ts
<Bundle>/I18nGen/I18nFramework.ts (typed shim)
```

```tsx
import { t } from '@common/I18n/I18nForeground';

<h1>{t.Course_Title()}</h1>
<p>{t.Common_Min([5])}</p>
```

Pipeline details: [`i18n.md`](i18n.md). Never edit the `*Gen.ts`
files — the next `prepare` rewrites them.

## Server data — globals + props

Two channels:

### Globals on `window`

`Layout/HtmlLayout.twig` injects a handful of typed globals:

| Global | What it carries |
|---|---|
| `window.__GARNET_UI_LANG__` | Current locale (`'RU'` / `'EN'`) — see `DateInput`'s use. |
| `window.__GARNET_USER__` | Logged-in user payload (id, email, timezone, flags). |
| `window.__GARNET_CSRF__` | CSRF token for POST requests. |
| `window.__GARNET_BASE_URL__` | Base URL string. |
| `window.__GARNET_BUILD__` | Build id (debugging cache mismatches). |

Read these from helpers — `formatTs` already knows about
`__GARNET_USER__.timezone`; `sendPost` already adds the CSRF header.

### Per-island props

Each island reads its `data-props` JSON. The controller assembles it:

```php
return $this->renderTwig('Foreground/page.twig', [
    'my_feature_props_json' => json_encode([
        'id'        => $id,
        'fields'    => static::courseFieldsInfo(),
        'items'     => $items,
    ]),
]);
```

For complex shapes, mirror the PHP types in `<Bundle>/Front/Models.ts`
so the TSX side reads the same shape it was given.

## Conventions

- **Lazy islands.** Every island goes through `createIsland({ lazy: …
  })`. No sync imports of islands from entry points.
- **One source of validation truth.** PHP `fieldsInfo` → Zod via
  `zodFromFieldsInfo`. Never duplicate rules.
- **Time is `unixtime`.** Display through `formatTs(ts)` — timezone
  arrives via `__GARNET_USER__.timezone`.
- **i18n on every string.** No hard-coded English / Russian in JSX or
  Twig.
- **Component classes, not utility soup.** Extract a `.component-name`
  in `components.css` when a Tailwind chain repeats.
- **No browser-native dialogs.** `window.confirm` / `alert` / `prompt`
  are forbidden. Use `useConfirm` + `ConfirmModal`.
- **No `<form action="...">` with full navigation.** Every form is
  `onSubmit={preventDefault}` + `sendPost`. No `window.location.reload`
  ever.

## Related

- [`architecture.md`](architecture.md) — the layered architecture and
  request lifecycle.
- [`i18n.md`](i18n.md) — translation pipeline.
- [`bundle.md`](bundle.md) — what a bundle ships (PHP + Twig + TSX +
  Front-end).
- [`cookbook/add-an-island.md`](cookbook/add-an-island.md) — recipe.
- [`cookbook/add-validation-rules.md`](cookbook/add-validation-rules.md) —
  fieldsInfo → Zod.
- [`cookbook/localise-strings.md`](cookbook/localise-strings.md) —
  i18n recipe.
- [`../Bundle/Front/README.md`](../Bundle/Front/README.md) — directory
  tour of the shared front sources.
- [`../Bundle/TwigTemplates/README.md`](../Bundle/TwigTemplates/README.md) —
  layouts, email skeletons, partials.

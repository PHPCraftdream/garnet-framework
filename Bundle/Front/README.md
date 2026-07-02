# Bundle / Front

The framework's **shared frontend** — TSX/CSS that every app inherits.
React islands, hooks, utility modules, the theming layer, the auth
flow's client. Apps add their own business islands under
`<App>/Front/`; the assets here are the common floor underneath.

## What's here

| Subdir / file | What it does |
|---|---|
| `Assets/` | Static files that ship with the build — favicons, fonts, icon-sets. Copied into each app's `Public/` during `php garnet prepare`. |
| `auth/` | The passwordless magic-link UI: state machine, code input, error toast. Renders inside the `/system/` page. |
| `Common/` | The big shared library — see breakdown below. |
| `EntryPoints/` | rspack entry-point shims — the islands that always load in the page shell (top menu, sidebar, mobile drawer, toaster). |
| `I18nGen/` | Generated TS shims for the framework's translation keys (`I18nFramework.ts`). **Generated** — never edit by hand; `php garnet prepare` rewrites them. |
| `lightbox/` | Image lightbox wrapper around `@fancyapps/ui`. |
| `Styles/` | Tailwind v4 theme tokens, component classes, base reset. The theming pipeline. |
| `ThirdParty/` | Vendored third-party JS/CSS that doesn't fit npm (small CDN-style drops). |

## Common/ breakdown

| Subdir | What it does |
|---|---|
| `Api/` | `sendPost`, `sendPostFormData`, response helpers. **The** way to talk to the server from React — never use `fetch` directly. |
| `Components/` | The component zoo: `AdminGrid`, `Banner`, `Calendar`, `ConfirmModal`, `DateInput`, `Form/*`, `Pagination`, `SendButton`, `Toast`, `Modal`, `Drawer`, `EntityHistory*`, `ImageUploadField`, `Navigation/*`, … See [Reuse over reinvention in AGENTS.md](../../AGENTS.md). |
| `Debug/` | Dev-only inspectors that no-op in production. |
| `Dom/` | DOM helpers (`GridTable`, custom-event bus, etc.). |
| `Errors/` | The shared `ErrorBoundary` every island wraps in. |
| `hooks/` | Custom React hooks — `useConfirm`, `useSending`, `useCtrlEnter`, `useBodyScrollLock`, `useOpenUser`, … |
| `Islands/createIsland.tsx` | The lazy-island factory every TSX entry uses. |
| `Models.ts` | Type definitions shared across the front. |
| `Stats/` | Tiny telemetry helpers (page-load timing, build hash beacons). |

## How an island reaches it all

```tsx
import { createIsland }    from '@common/Islands/createIsland';
import { useConfirm }      from '@common/hooks/useConfirm';
import { sendPost }        from '@common/Api';
import { formatTs }        from '@common/Utils/DateUtils';
import { ConfirmModal }    from '@common/Components/ConfirmModal';
import { t }               from '@common/I18n/I18nForeground';
```

`@common` resolves to `Bundle/Front/Common/` via the rspack alias
defined in `FrontBuilder/build/moduleConfig.ts`. Apps reference the
same `@common` and the same `@framework` aliases — they're framework-
provided, not per-app.

## Conventions

- **Lazy load.** Every island goes through `createIsland({ lazy: …
  })`. No sync imports of islands from entry points.
- **One source of validation truth.** Backend `fieldsInfo` becomes Zod
  via `zodFromFieldsInfo`. Never rewrite validators.
- **Time is unixtime.** Render via `formatTs(ts, { tz })` — the user's
  timezone arrives in `window.__GARNET_USER__.timezone`.
- **i18n.** All user-visible strings go through `t.Key()`. No
  hard-coded English / Russian in JSX.
- **Component classes, not utility soup.** Reach for a class in
  `Styles/components.css`; extract one if you find yourself repeating
  five+ Tailwind utilities.
- **No browser-native dialogs.** `window.confirm` / `alert` / `prompt`
  are forbidden. Use `useConfirm` + `ConfirmModal`.

## Don't

- Don't add an app-specific island here. App islands live in
  `<App>/Front/Islands/`. Things here are reusable across apps.
- Don't import from `<App>/…` in this tree — it would leak app
  concepts into the framework.

## Related

- [`../README.md`](../README.md) — bundle index.
- [`../TwigTemplates/README.md`](../TwigTemplates/README.md) — the markup the islands hydrate into.
- [`../../docs/frontend.md`](../../docs/frontend.md) — full frontend reference.
- [`../../docs/cookbook/add-an-island.md`](../../docs/cookbook/add-an-island.md) — recipe.

---

↑ Back to [Bundle](../README.md)

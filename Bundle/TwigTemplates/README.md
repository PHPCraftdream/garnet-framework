# Bundle / TwigTemplates

The framework's reusable Twig templates: the page shell, the error
pages, the email skeletons, the components that bundles include.
Apps add their own templates under `<App>/Foreground/TwigTemplates/`;
the templates here are the ground floor.

## Locale-paired files

Every template ships in **two locale variants**: `Foo/Bar.en.twig` and
`Foo/Bar.ru.twig`. There is no canonical bare `Foo/Bar.twig` on disk.

Callers keep using the bare name — `Twig::get()->render('Foo/Bar.twig', $vars)`
or `{% include 'Foo/Bar.twig' %}` inside another template. Resolution
happens transparently inside [`LocaleResolvingLoader`](../../Kernel/Io/Twig/LocaleResolvingLoader.php):

1. Reads the active locale from `app.ini` (`default_locale=en|ru|…`,
   falls back to `en`).
2. Rewrites the requested name to `Foo/Bar.{locale}.twig`.
3. Each locale gets its own compiled-cache key, so EN-rendered and
   RU-rendered outputs never collide.

For purely structural templates (every email skeleton, every layout
shell except `Maintenance`) the `.en.twig` and `.ru.twig` files are
byte-identical — all user-facing text is passed in via variables. The
duplicate exists as a hook for future hardcoded copy: when a template
gains a literal string, only the `.{locale}.twig` it belongs to needs
to change.

`Layout/Maintenance.{en,ru}.twig` is the one template that actually
diverges today — it is served by `MaintenanceMiddleware` without the
i18n pipeline, so the user-facing text has to live in the file itself.

## What's here

| Subdir | What it does |
|---|---|
| `Layout/` | Full-page layouts. `HtmlLayout.twig` is the master shell; `Maintenance.twig`, `ErrorPage.twig`, `Error404Fallback.twig`, `Island.twig` cover the rest. |
| `Email/` | Email skeletons + shared components — brand footer, label-value row, plain layout. |
| `Components/` | Small reusable partials that bundles include from their own templates. |
| `Framework/` | Internal layouts used only by the framework's own pages (login, admin shell). |
| `StaticPages/` | `Blocks.twig` and `Link.twig` — the renderer the [StaticPages](../Modules/StaticPages/README.md) bundle uses to materialise page blocks. |

## The master shell

`Layout/HtmlLayout.twig` is the only HTML layout almost every app
page extends. It owns:

- `<!doctype>` and `<html lang="…">` (BCP-47, never `auto`).
- `<head>`: title / description / OG tags / Twitter cards / canonical
  / theme-color / viewport.
- Build-time `__GARNET_*` globals (`__GARNET_UI_LANG__`, `__GARNET_CSRF__`,
  `__GARNET_USER__`, `__GARNET_BASE_URL__`, …).
- The asset chain: styles in `<head>`, scripts before `</body>` with
  `async`, prefetch links for the most-likely-next chunks.
- The skeleton: top menu mount, sidebar mount, mobile drawer mount,
  main content, footer, support / IM widgets.

Apps don't customise the shell — they pass typed arrays into it. The
PHP side prepares the values; Twig just plugs them in. See
[`Utils/HtmlLayout.php`](../Utils/HtmlLayout.php) for the params
contract.

## Email layouts

| Template | When |
|---|---|
| `Email/LayoutPlain.twig` | Default. Brand header + body block + brand footer. |
| `Email/LayoutBrand.twig` | Same, but with a coloured banner per brand. |
| `Email/LabelValueRow.twig` | "Field: value" row partial. |
| `Email/BrandFooter.twig` | Footer with brand name, support contact. |

All emails extend one of the layouts and fill the `body` block. The
brand name pulls from `FwAppSettings::brandName()` automatically.

## Auto-escape

Twig auto-escapes `{{ x }}` by default. The `| raw` filter is reserved
for content the framework already trusts — pre-rendered sub-templates,
inlined SVG literals, `target="_blank" rel="…"` strings the PHP side
assembled. Every `| raw` should carry a one-line comment justifying
the trust.

## Shell-marker detection

The framework's `HtmlLayout::render` looks for the markers `sp-nav`
and `sp-footer` in the body content. If either is present, it forces
`bare_main = true` so the page doesn't double-render menus and
footers. This is what lets static-pages-with-chrome work without
manual flag flipping.

## Conventions

- **No HTML in PHP.** Markup belongs here. PHP prepares typed arrays;
  Twig renders them. See the strict rule in [AGENTS.md](../../AGENTS.md).
- **Component classes in markup.** Templates use the same Layer-4
  component classes from `components.css` as the React side. No
  inline `style="…"`, no long Tailwind utility soup.
- **i18n through `t.Key()`.** No raw strings in templates — same rule
  as React.
- **One layout per page type.** New full-page layouts are a smell; the
  shell is one. If you need a different shape, extend `HtmlLayout` and
  override blocks.

## Related

- [`../README.md`](../README.md) — bundle index.
- [`../Front/README.md`](../Front/README.md) — the islands these templates host.
- [`../../docs/i18n.md`](../../docs/i18n.md) — the `t.Key()` pipeline.

---

↑ Back to [Bundle](../README.md)

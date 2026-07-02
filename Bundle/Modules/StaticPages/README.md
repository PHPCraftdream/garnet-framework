# Static Pages

CMS-style static page editor with a block-based body, reusable
snippets, and live preview. Backs pages like Privacy Policy, Terms,
landing fragments, and any text content the operator needs to edit
without a deploy.

## What's here

| File / subdir | What it does |
|---|---|
| `FwStaticPagesService.php` | The abstract service. Apps pin three concrete tables; the service then handles list / get / render. |
| `Tables/FwStaticPages.php` | Page metadata: slug, title, lang, robots, OG image, draft/published flag. |
| `Tables/FwStaticPageBlocks.php` | Ordered blocks per page: paragraph, image, snippet ref, raw HTML (gated). |
| `Tables/FwStaticSnippets.php` | Named reusable fragments — e.g. a contact block reused on five pages. |
| `Controllers/` | Public renderer + admin editor controllers. |
| `Spec/` | Kahlan specs for block-to-HTML rendering, link resolution, snippet substitution. |

## Block types

Each block has a `type` and a `body`. Built-in types:

| Type | What `body` holds | How it renders |
|---|---|---|
| `paragraph` | Markdown | Sanitised HTML via `league/commonmark` with a DisallowedRawHtml extension. |
| `heading` | Plain text | `<h2>` / `<h3>` based on `level`. |
| `image` | URL + alt | `<img>` with lazy-loading attrs. |
| `snippet` | Snippet key | Inlines the snippet's blocks recursively (depth-limited). |
| `raw` | HTML | Verbatim — only available to owner-role editors. |
| `link` | URL + label | Routed through the link resolver so internal targets stay stable across renames. |

Adding a new block type is a single switch case in the service plus a
Twig include — see `TwigTemplates/StaticPages/Blocks.twig`.

## Markdown safety

Paragraph bodies are CommonMark, **not** raw HTML. The configured
extensions strip `<script>`, `on*` attributes, and disallow `<style>`.
`raw` blocks bypass the sanitiser — gate them behind the owner role in
your controller and never expose them in the public edit form.

## Snippet inlining

A `snippet` block names another snippet; the renderer expands it
inline. Depth is capped (default 3) so cycles can't deadlock the page.
Use this for "shared footer", "shared CTA", "shared address" — anything
edited in one place that appears on many.

## Link resolver

`link` blocks store a logical target (`{type: 'page', slug: 'about'}`),
not a literal URL. The resolver materialises a URL at render time.
Renaming a slug doesn't break inbound links.

## Wire-up

```php
// MyApp.php → init()
StaticPagesService::register(
    Pages::class,           // extends FwStaticPages
    PageBlocks::class,      // extends FwStaticPageBlocks
    Snippets::class,        // extends FwStaticSnippets
);
```

Then mount the public renderer (`/page/view~<slug>`) and the admin
editor (`/admin/pages/*`) in your router.

## Caching

Rendered pages are cached by `(slug, lang, theme_color)` and busted on
admin save. Set a short max-age header on the response for shared
proxies if you serve at scale.

## Don't

- **Don't store secrets in pages.** They're rendered for the public and
  cached.
- **Don't bypass the link resolver** with absolute URLs unless you mean
  it. Rename safety is the whole point.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../SystemSettings/README.md`](../SystemSettings/README.md) — brand name + SEO defaults pulled into the layout.

---

↑ Back to [Bundle / Modules](../../README.md)

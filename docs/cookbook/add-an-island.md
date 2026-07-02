# Add a React island

Garnet's frontend is server-rendered Twig with **lazy-loaded React
islands** dropped into the markup. The page ships fast, then each
island hydrates on its own. No SPA, no global router.

## The shape of an island

An island is a TSX component wrapped by `createIsland`. The framework
gives you a `<div class="my-island-init" data-props='…'>` placeholder
in Twig and replaces it with the React tree at runtime.

```
Bundle/Front/Islands/MyFeature/
├── MyFeature.tsx              # default export — the React component
└── MyFeature.entry.tsx        # createIsland wrapper, lazy-loaded
```

```tsx
// MyFeature.tsx
import * as React from 'react';

interface Props { title: string; count: number }

export default function MyFeature({ title, count }: Props) {
    return (
        <div className="my-feature">
            <h2>{title}</h2>
            <p>You have {count} items.</p>
        </div>
    );
}
```

```tsx
// MyFeature.entry.tsx
import { createIsland } from '@common/Islands/createIsland';

export default createIsland({
    selector: '.my-feature-init',
    lazy: () => import('./MyFeature'),
});
```

## Drop it into a Twig template

```twig
{# my_feature_props is a PHP array prepared in the controller and
   json_encoded for the data-props attribute. #}
<div class="my-feature-init" data-props='{{ my_feature_props_json | raw }}'></div>
```

The controller side:

```php
return $this->renderTwig('Foreground/page.twig', [
    'my_feature_props_json' => json_encode([
        'title' => 'Hello',
        'count' => 7,
    ]),
]);
```

## Why lazy

- The main page bundle stays small — only the framework runtime + the
  shared `@common/*` utilities load eagerly.
- Each island ships as its own content-hashed chunk
  (`my-feature.<hash>.gen.js`). Browsers cache it across page loads.
- If a page doesn't render the placeholder, the chunk never downloads.

## Always wrap in ErrorBoundary

`createIsland` puts an `ErrorBoundary` around the lazy import for you
— but only if you export the component as the **default** of the file
you import. The convention exists for that reason; don't named-export
the root.

## Conventions

| Do | Don't |
|---|---|
| Use `@common/*` helpers (`useConfirm`, `formatTs`, `sendPost`, …) | Roll your own `fetch`, ad-hoc date formatters, raw `window.confirm` |
| Read time as `unixtime` from props, render via `formatTs` | Use `new Date()` without a timezone |
| Get translation strings via `t.Key()` from the generated i18n file | Hard-code English/Russian strings in JSX |
| Drive validation rules from PHP `fieldsInfo` → Zod via `zodFromFieldsInfo` | Duplicate rules in TS |
| Keep component class names short and semantic in markup | Long Tailwind utility strings in TSX |

## Related

- [`../frontend.md`](../frontend.md) — full frontend reference.
- [Add validation rules](add-validation-rules.md) — Zod from PHP fieldsInfo.

---

↑ Back to [Cookbook](README.md)

# Localise UI strings

All user-facing text in Garnet flows through the i18n pipeline. You
edit Russian and English keys in PHP files; the build step generates
the TypeScript companions that the React side imports.

## Where keys live

| File | Scope |
|---|---|
| `Bundle/I18n/I18nDataRu.php` / `I18nDataEn.php` | Framework-level keys (Auth, Pagination, Action_*, …) |
| `<App>/Foreground/I18n/ForegroundI18nDataRu.php` / `…En.php` | Per-app foreground keys |
| `<App>/Dashboard/I18n/DashboardI18nDataRu.php` / `…En.php` | Per-app admin-panel keys |

Each file returns an associative array of `key => value` pairs.

## Add a key

```php
// ForegroundI18nDataRu.php
return [
    // …
    'Course_TitleLabel'    => 'Название курса',
    'Course_CountSuffix'   => 'курсов: %s',
];
```

```php
// ForegroundI18nDataEn.php
return [
    // …
    'Course_TitleLabel'    => 'Course title',
    'Course_CountSuffix'   => 'courses: %s',
];
```

## Use it in PHP

```php
use PHPCraftdream\MyApp\Foreground\I18n\ForegroundI18n;

$t = ForegroundI18n::getInstance();
$label = $t->Course_TitleLabel();             // 'Course title'
$line  = $t->Course_CountSuffix(7);           // 'courses: 7'
```

The magic `__call`/`tr` handler substitutes `%s` from arguments in
order. **Never** wrap the call in `sprintf` — that double-substitutes
and breaks pluralisation rules.

## Use it from Twig

```twig
<h1>{{ t.Course_TitleLabel() }}</h1>
<p>{{ t.Course_CountSuffix(courses|length) }}</p>
```

## Use it in React

```tsx
import { t } from '@common/I18n/I18nForeground';

<h1>{t.Course_TitleLabel()}</h1>
<p>{t.Course_CountSuffix(courses.length)}</p>
```

The TS files (`I18nForeground.ts`, `I18nFramework.ts`) are **generated**
by `php garnet prepare`. Never edit them by hand — the next prepare
will overwrite your changes.

After adding a key in PHP:

```bash
php garnet prepare       # regenerates TS shims for both langs
```

## Brand name

`%s` for the brand name reads from `app.ini` → `title`, exposed via
`FwAppSettings::brandName()`:

```php
$t->Email_BookingCreated_Subject(FwAppSettings::brandName());
```

Don't hard-code the brand into the translation value; pass it as a
`%s` argument so re-skinning a deployment doesn't require new
translations.

## Conventions

- Keys are PascalCase with module/feature prefix: `Course_TitleLabel`,
  `Action_Delete`, `Email_BookingCreated_Subject`.
- One `%s` per substitution slot; the engine fills them in argument
  order.
- Plural forms — keep separate keys (`Course_OneCount`,
  `Course_ManyCount`) until a real ICU-style plural library lands.
- All user-facing strings go through the pipeline. No hard-coded
  English in JSX, Twig, or PHP echo statements.

## Related

- [`../i18n.md`](../i18n.md) — full pipeline reference.

---

↑ Back to [Cookbook](README.md)

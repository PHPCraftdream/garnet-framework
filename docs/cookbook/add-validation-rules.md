# Add validation rules to a form

The framework has **one source of validation truth — PHP**. The
backend declares fields in a static method, and the frontend converts
that declaration to a Zod schema at runtime. The two sides cannot
drift.

## Declare fields in the controller

A controller that handles a form exposes a `*FieldsInfo()` method:

```php
use PHPCraftdream\MyApp\Foreground\I18n\ForegroundI18n;

protected static function courseFieldsInfo(): array
{
    $t = ForegroundI18n::getInstance();
    return [
        'fields' => [
            'title' => [
                'name'       => $t->Course_TitleLabel(),
                'type'       => 'string',
                'validation' => ['required', 'maxLen[255]'],
            ],
            'cost' => [
                'name'       => $t->Course_CostLabel(),
                'type'       => 'string',
                'validation' => ['required', 'int', 'minVal[0]'],
            ],
            'tags' => [
                'name'       => $t->Course_TagsLabel(),
                'type'       => 'string',
                'validation' => ['maxLen[1024]'],
            ],
        ],
        'detailsFields' => ['title', 'cost', 'tags'],
    ];
}
```

## Pass it to the React island

In `get__edit`:

```php
return $this->renderIsland('CourseEditor', [
    'course'      => $course,
    'fieldsInfo'  => static::courseFieldsInfo(),
]);
```

## Build a Zod schema from it on the frontend

```tsx
import { useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { zodFromFieldsInfo, getFieldRegisterOptions }
    from '@common/Utils/zodFromFieldsInfo';

export default function CourseEditor({ fieldsInfo, course }: Props) {
    const schema = useMemo(
        () => zodFromFieldsInfo(fieldsInfo.fields, fieldsInfo.detailsFields),
        [fieldsInfo],
    );
    const { register, handleSubmit, formState: { errors } } = useForm({
        resolver: zodResolver(schema),
        defaultValues: course,
    });

    return (
        <form onSubmit={handleSubmit(onSubmit)}>
            <input {...register('title', getFieldRegisterOptions(fieldsInfo.fields.title))} />
            {errors.title && <span>{errors.title.message}</span>}
            …
        </form>
    );
}
```

`getFieldRegisterOptions` infers things like `valueAsNumber: true` for
numeric fields, so you don't have to.

## Supported validators

Same set the backend `Updater.php` accepts; both sides honour them:

| Rule | What it checks |
|---|---|
| `required` | non-empty |
| `len[min,max]` | string length in `[min, max]` |
| `minLen[n]` / `maxLen[n]` | string length bound |
| `nameSymbols` | name-friendly character set |
| `simpleText` | safe printable text |
| `email` | valid email |
| `tzExists` | known timezone identifier |
| `in_array[v1,v2,…]` | one of |
| `int` | parses as integer |
| `min[n]` / `minVal[n]` | numeric lower bound |
| `max[n]` / `maxVal[n]` | numeric upper bound |
| `pattern[regex,flags]` | regex match |
| `url` | valid URL |
| `alphanumeric` | letters + digits only |

## Error messages

Come from `I18nFramework` on both sides. Don't pass `message` strings
through the validator config — let i18n handle it. Custom per-field
messages can be supplied via the field's `'name'` key (used in
"<name> is required" style sentences).

## Nested arrays

For repeated rows (e.g. a course with N slots), extend the schema
returned by `zodFromFieldsInfo`:

```ts
const slotSchema = zodFromFieldsInfo(slotFieldsInfo.fields, slotFieldsInfo.detailsFields);
const formSchema = z.object({
    course: zodFromFieldsInfo(courseFieldsInfo.fields, courseFieldsInfo.detailsFields),
    slots:  z.array(slotSchema.extend({ sort_order: z.number() })),
});
```

## Related

- [`../i18n.md`](../i18n.md) — error message keys.
- [Add an island](add-an-island.md) — wiring the React form.

---

↑ Back to [Cookbook](README.md)

# Comments

Generic "comments on anything" store. Any entity in the app — a
booking, a course, a profile, a static page — can carry threaded
comments without a custom table per type.

## What's here

| Subdir / file | What it does |
|---|---|
| `Tables/FwComments.php` | Abstract `DbTable` for the comments store. App-side subclasses pin `$tableName`. |
| `Spec/` | Kahlan specs for the table builder and query patterns. |

## Schema in one paragraph

`FwComments` builds a table with `id`, `author_id`, `entity_type`,
`entity_id`, `body`, `is_hidden`, `created_at`, `updated_at`. The
identity of the commented-on thing is a `(entity_type, entity_id)`
tuple — `entity_type` is `VARCHAR`, so the app decides which values it
accepts (validated in the controller / service layer, not at the DB
level).

Indexes:

| Index | Used for |
|---|---|
| `entity` (`entity_type, entity_id`) | "show all comments on this thing" |
| `entity_created` (`entity_type, entity_id, created_at`) | paginated newest-first reads |
| `author_id` | "show this user's comments" |
| `is_hidden` | admin moderation views |

## App-side subclass

```php
namespace PHPCraftdream\MyApp\Common\Tables;

use PHPCraftdream\Garnet\Bundle\Modules\Comments\Tables\FwComments;

class Comments extends FwComments
{
    protected string $tableName = 'comments';
}
```

Run a migration to create the table, then write to it via the
inherited `DbTable` API:

```php
Comments::get()->insert([
    'author_id'   => $userId,
    'entity_type' => 'booking',
    'entity_id'   => $bookingId,
    'body'        => $text,
]);
```

## Listing

```php
$page = Comments::get()->select(
    ['entity_type' => 'booking', 'entity_id' => $bookingId, 'is_hidden' => 0],
    orderBy: 'created_at DESC',
    limit:   DEFAULT_PAGE_SIZE,
    offset:  $offset,
);
```

For hot pages, the `entity_created` index keeps this O(log N) even for
chatty threads.

## Hiding (moderation)

`is_hidden = 1` keeps the row in place and indexable for audit but
takes it out of the user-facing list. Reuse the same index by
filtering on `is_hidden` in your selects. Hard deletes are rare —
keep them for spam.

## Threading

This module is intentionally **flat**, not tree-shaped. If an app
needs replies-to-replies, add a `parent_id` column in the subclass
and a controller-side renderer. The framework keeps it minimal so
the table indexes stay simple.

## Don't

- Don't use `entity_type` for application sharding. It's a sieve, not
  a partition key.
- Don't store rendered HTML in `body`. Sanitise + render at read time
  via Twig with `| raw` only on output you trust.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../EntityHistory/README.md`](../EntityHistory/README.md) — per-entity audit trail (different shape: who edited what, not user-visible discussion).

---

↑ Back to [Bundle / Modules](../../README.md)

# Run MySQL queries in parallel

The framework's hottest performance trick: queries that don't depend on
each other run **concurrently**. Total wait time is bounded by the
slowest query, not the sum.

## When to use it

You want this when a controller needs more than one independent SELECT
to render a page — for instance loading the current user, their notification
count and the latest news in the same response.

```
sequential : ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ ▓▓▓▓▓ ▓▓▓▓▓▓▓▓     ~30ms
parallel   : ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓                    ~18ms
             ▓▓▓▓▓
             ▓▓▓▓▓▓▓▓
```

## API

`DbTable` exposes async variants of every CRUD shape. The pattern:

```php
use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;

// 1. Kick off async queries. They start immediately on the connection
//    pool, but do not block.
Users::get()->selectAsync(['id' => $userId]);
News::get()->selectAsync(['status' => 'published'], orderBy: 'id DESC', limit: 5);
Notifications::get()->countAsync(['user_id' => $userId, 'is_read' => 0]);

// 2. Drain the pool. This is the only blocking call — it waits until
//    every in-flight query has completed.
DbPool::get()->pollFinishAll();

// 3. Read the results. The async tables stash them keyed by their hash;
//    the matching select/count call returns the cached result.
$user      = Users::get()->select(['id' => $userId])[0]                  ?? null;
$news      = News::get()->select(['status' => 'published'], orderBy: 'id DESC', limit: 5);
$unreadCnt = Notifications::get()->count(['user_id' => $userId, 'is_read' => 0]);
```

The framework already calls `pollFinishAll()` at the end of every web
request (`run_web.php`), so if you only need the results inside Twig,
you can fire-and-forget — Twig calls the sync variant and the result is
already there.

## Patterns

### Bulk-load a list of items by id

```php
foreach ($postIds as $id) {
    Posts::get()->selectAsync(['id' => $id]);
}
DbPool::get()->pollFinishAll();

$posts = array_map(
    fn (int $id) => Posts::get()->select(['id' => $id])[0] ?? null,
    $postIds,
);
```

For large lists this trumps a single `WHERE id IN (...)` because every
fetch hits the row cache by primary key.

### Mix selects and counts

```php
Threads::get()->selectAsync(['forum_id' => $forumId], orderBy: 'updated_at DESC', limit: 20);
Threads::get()->countAsync(['forum_id' => $forumId]);
Users::get()->selectAsync(['id' => $forum->owner_id]);
DbPool::get()->pollFinishAll();
```

## Gotchas

- The async tables key results by the **(table, where, order, limit)
  tuple**. Two `selectAsync` calls with identical args fire one query
  and share the result.
- `pollFinishAll()` is cheap to call multiple times — it just returns
  immediately if the pool is empty. Call it before any code that needs
  the data, just to be explicit.
- Don't `selectAsync` inside a Twig template — the rendering loop won't
  drain the pool. Prepare the data in the controller.

## Related

- [`../database.md`](../database.md) — `DbPool`, `DbTable`, EAV, Account.
- [`../architecture.md`](../architecture.md#async-database) — the
  `mysqli_poll` mechanism in depth.

---

↑ Back to [Cookbook](README.md)

# News / Activity Feed Module

Event-driven notification feed with broadcast and personal delivery.
Broadcast events are visible to all users except the actor; personal events
target a single recipient.

> **Backend-only module.** This module ships a service class, controllers, tables, and validation logic — no bundled frontend and no routes wired into the framework's router. The consuming app is expected to subclass `FwNewsService`/`FwNewsController`, register its own route, and build its own React UI to render the feed, the same pattern the reference app IRabi follows.

## Tables

| Table class      | Key columns                                                  |
|------------------|--------------------------------------------------------------|
| `FwNewsEvents`   | `id`, `event_type` (VARCHAR 50), `audience_type` (ENUM broadcast/personal), `audience_id` (nullable INT), `actor_id`, `payload` (TEXT, JSON), `created_at` (INT, unix) |
| `FwNewsReads`    | `id`, `account_id`, `event_id`, `read_at` (INT). UNIQUE on `(account_id, event_id)` |
| `FwNewsArchived` | `id`, `account_id`, `event_id`, `archived_at` (INT). UNIQUE on `(account_id, event_id)` |

All three are abstract -- your app must create concrete subclasses with a
table name.

## Constants

| Constant              | Value       | Description                              |
|-----------------------|-------------|------------------------------------------|
| `AUDIENCE_BROADCAST`  | `broadcast` | Visible to everyone except actor         |
| `AUDIENCE_PERSONAL`   | `personal`  | Visible only to `audience_id`            |
| `FEED_TTL_SEC`        | 7 776 000   | 90 days -- events older than this are excluded from the feed |
| `MESSAGE_THROTTLE_SEC`| 3 600       | 1 hour -- `createThrottledEvent` deduplication window |

## FwNewsService

Abstract class. Subclass it and implement three factory methods that return
your concrete table instances:

```php
abstract protected static function eventsTable(): FwNewsEvents;
abstract protected static function readsTable(): FwNewsReads;
abstract protected static function archivedTable(): FwNewsArchived;
```

### Methods

| Method | Signature | Description |
|--------|-----------|-------------|
| `createBroadcast` | `(string $eventType, int $actorId, array $payload): int` | Insert a broadcast event. Returns the new row ID. |
| `createPersonal` | `(string $eventType, int $actorId, int $audienceId, array $payload): int` | Insert a personal event for one user. |
| `createThrottledEvent` | `(string $eventType, int $actorId, int $audienceId, array $payload): ?int` | Like `createPersonal`, but skips if the same `(event_type, actor, audience)` tuple already has an event within `MESSAGE_THROTTLE_SEC`. Returns `null` when throttled. |
| `getFeed` | `(int $accountId, int $page, int $perPage, bool $includeArchived = false): array` | Paginated feed. Returns `{items, page, perPage, total, totalPages, unreadCount}`. Each item includes `is_read`, `read_at`, `is_archived`. |
| `getUnreadCount` | `(int $accountId): int` | Count of unread, non-archived events within TTL. |
| `markRead` | `(int $accountId, array $eventIds): void` | Mark specific events as read (INSERT IGNORE). |
| `markAllRead` | `(int $accountId): void` | Mark every visible event as read in one query. |
| `archive` | `(int $accountId, array $eventIds): void` | Hide events from feed (recoverable). |
| `unarchive` | `(int $accountId, array $eventIds): void` | Restore archived events. |

### Feed visibility rules

An event is visible to `accountId` when **all** of the following hold:

1. `(audience_type = 'broadcast' AND actor_id != accountId)` OR
   `(audience_type = 'personal' AND audience_id = accountId)`
2. `created_at` is within the last `FEED_TTL_SEC` (90 days)
3. Event is not archived by this user (unless `includeArchived = true`)

## FwNewsController

Abstract controller with five JSON POST endpoints. Subclass and implement:

```php
abstract protected static function newsService(): string; // class-string<FwNewsService>
```

| Endpoint          | POST params                          | Response                              |
|-------------------|--------------------------------------|---------------------------------------|
| `post__feed`      | `page`, `perPage`, `includeArchived` | Feed payload from `getFeed`           |
| `post__markRead`  | `event_ids` (JSON array)             | `{success, unreadCount}`              |
| `post__markAllRead` | --                                 | `{success, unreadCount: 0}`           |
| `post__archive`   | `event_ids` (JSON array)             | `{success}`                           |
| `post__unarchive` | `event_ids` (JSON array)             | `{success}`                           |

All endpoints require an authenticated session; they return 401 otherwise.

## Setup

1. Create three table classes extending `FwNewsEvents`, `FwNewsReads`,
   `FwNewsArchived`. Set `$tableName` in each.

2. Create `NewsService extends FwNewsService`. Implement the three
   `*Table()` methods. Add app-specific event type constants:

```php
class NewsService extends FwNewsService {
    public const EVENT_NEW_SLOT = 'new_slot';
    public const EVENT_BOOKING  = 'slot_booked';

    protected static function eventsTable(): NewsEvents { return NewsEvents::get(); }
    protected static function readsTable(): NewsReads   { return NewsReads::get(); }
    protected static function archivedTable(): NewsArchived { return NewsArchived::get(); }
}
```

3. Create a controller extending `FwNewsController`:

```php
class NewsController extends FwNewsController {
    protected static function newsService(): string { return NewsService::class; }
}
```

4. Register the controller route (e.g. `/news/`).

5. Run the migration that creates the three tables.

## Usage examples

```php
// Broadcast: everyone (except actor 42) sees this
NewsService::createBroadcast('new_course', 42, ['courseId' => 7, 'title' => 'Hebrew']);

// Personal: only user 99 sees this
NewsService::createPersonal('booking_confirmed', 42, 99, ['slotId' => 5]);

// Throttled: at most one per hour per actor->audience pair
NewsService::createThrottledEvent('new_message', 42, 99, ['chatId' => 3]);
```

## Extension points

- Override `FEED_TTL_SEC` or `MESSAGE_THROTTLE_SEC` in your subclass.
- Add custom event type constants in your service subclass.
- The `payload` column is free-form JSON -- store whatever your frontend needs.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../Messaging/README.md`](../Messaging/README.md) — direct messages between users.

---

↑ Back to [Bundle / Modules](../../README.md)

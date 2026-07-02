# Messaging (IM) Module

1-to-1 private messaging system with conversations, messages, file attachments, and read tracking.

> **Backend-only module.** This module ships controllers, tables, and validation logic — no bundled frontend and no routes wired into the framework's router. The consuming app is expected to subclass `FwImController`, register its own route, and build its own React island component (`im-page`) on top, the same pattern the reference app IRabi follows.

## Tables

### FwImConversations
| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Auto-increment |
| participant_a | INT | Lower account ID (unique pair with participant_b) |
| participant_b | INT | Higher account ID |
| last_message_at | INT | Unix timestamp of last message |
| created_at | INT | Unix timestamp |

Participants are stored canonically: `min(a,b)` in `participant_a`, `max(a,b)` in `participant_b`.
Unique index on `(participant_a, participant_b)` prevents duplicate conversations.

### FwImMessages
| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Auto-increment |
| conversation_id | INT | FK to conversations |
| sender_id | INT | Account ID of sender |
| body | TEXT | Message content |
| created_at | INT | Unix timestamp |

### FwImAttachments
| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Auto-increment |
| message_id | INT | FK to messages |
| original_name | VARCHAR(255) | User-facing filename |
| stored_name | VARCHAR(255) | Randomized filename on disk |
| mime_type | VARCHAR(100) | MIME type (validated via finfo) |
| size | INT | File size in bytes |
| created_at | INT | Unix timestamp |

### FwImReadStatus
| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Auto-increment |
| conversation_id | INT | FK to conversations |
| account_id | INT | Reader's account ID |
| last_read_message_id | INT | Highest message ID read |
| updated_at | INT | Unix timestamp |

Unique index on `(conversation_id, account_id)`. Unread count = messages with `id > last_read_message_id` and `sender_id != account_id`.

## Controller: FwImController

Abstract base controller at URL `/im/`. Provides these endpoints:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `~main` | Render IM page (island component) |
| POST | `~conversations` | List conversations with partner info, snippets, unread counts |
| POST | `~messages` | Fetch messages for a conversation (auto-marks as read) |
| POST | `~send` | Send message with optional attachments (FormData, CSRF) |
| GET | `~download` | Download attachment (access-checked via conversation membership) |
| POST | `~unreadCount` | Total unread message count for current user |
| POST | `~searchRecipients` | Search users to start a new conversation |
| POST | `~quickChat` | Load last N messages with a partner (for embedded widgets) |

## Abstract Methods

Apps must implement these six methods:

```php
// Who can this user message? Returns [{id, name, role}, ...]
abstract protected static function searchRecipients(int $accountId, string $query): array;

// Add app-specific fields to conversation data (e.g. partner_has_expert_profile)
abstract protected static function enrichConversation(array &$conv, int $accountId): void;

// Path to upload directory for IM attachments
abstract protected static function getUploadDir(): string;

// Side menu items for IM page layout
abstract protected static function getSideMenu(string $url): array;

// Top menu items for IM page layout
abstract protected static function getMainMenu(string $url): array;

// Whether the current user is a moderator (for UI flags)
abstract protected static function isModeratorCheck(): bool;
```

Optionally override `conversationsTable()`, `messagesTable()`, `attachmentsTable()`, `readStatusTable()` to return app-specific table subclasses.

## Setup

1. Create four app table classes extending the `Fw*` base tables.
2. Register them in your migration.
3. Create a controller extending `FwImController`:

```php
class ImController extends FwImController {
    public const URL = '/im/';

    protected static function getUploadDir(): string {
        return MyApp::getInstance()->uploadDir;
    }

    protected static function searchRecipients(int $accountId, string $query): array {
        // Query your accounts table, return [{id, name, role}, ...]
    }

    protected static function enrichConversation(array &$conv, int $accountId): void {
        // Add custom fields if needed
    }

    // ... implement getSideMenu, getMainMenu, isModeratorCheck
}
```

4. Register the controller route (`/im/`) in your app's router.
5. Create the frontend island component (`im-page`) that receives the props passed by `get__main`.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../Support/README.md`](../Support/README.md) — admin-to-user variant on top of the same primitives.

---

↑ Back to [Bundle / Modules](../../README.md)

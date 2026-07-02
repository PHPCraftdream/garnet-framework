# Support (Tickets) Module

Full-featured support ticket system with user-facing and admin-facing controllers, file attachments, internal comments, assignment tracking, and auto-context capture.

> **Backend-only module.** This module ships controllers, tables, and validation logic — no bundled frontend and no routes wired into the framework's router. The consuming app is expected to subclass `FwSupportController`/`FwSupportAdminController`, register its own routes, and build its own React UI on top (see `getSideMenu`/`getMainMenu` and the abstract methods below), the same pattern the reference app IRabi follows.

## Tables

### FwSupportTickets
| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Auto-increment |
| account_id | INT | Ticket owner |
| subject | VARCHAR(255) | Ticket subject line |
| status | ENUM | open, investigation, in_progress, waiting_user, waiting_support, escalated, on_hold, resolved, rejected |
| assignee_id | INT NULL | Assigned staff member |
| unread_user | INT | Unread count for the ticket owner |
| unread_staff | INT | Unread count for staff |
| context | TEXT NULL | Auto-captured browser context (JSON) |
| created_at | INT | Unix timestamp |
| updated_at | INT | Unix timestamp |

### FwSupportMessages
| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Auto-increment |
| ticket_id | INT | FK to tickets |
| author_id | INT | Account ID of author |
| body | TEXT | Message content |
| is_internal | TINYINT(1) | 1 = staff-only internal comment, 0 = visible to user |
| msg_type | ENUM | user, staff, system |
| created_at | INT | Unix timestamp |

Internal comments (`is_internal=1`) are never returned to the user-facing controller.

### FwSupportAttachments
Same schema as Messaging attachments: `id`, `message_id`, `original_name`, `stored_name`, `mime_type`, `size`, `created_at`. Includes `getByMessageIds()` for batch loading.

### FwSupportAssignmentLog
| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Auto-increment |
| ticket_id | INT | FK to tickets |
| actor_id | INT | Who performed the assignment |
| from_id | INT NULL | Previous assignee |
| to_id | INT NULL | New assignee (NULL = unassigned) |
| created_at | INT | Unix timestamp |

## Controllers

### FwSupportController (user-facing)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `~main` | Render support page with paginated ticket list |
| POST | `~tickets` | List user's tickets |
| POST | `~ticketPage` | Paginated ticket list |
| POST | `~messages` | Fetch messages for a ticket (excludes internal comments) |
| POST | `~createTicket` | Create ticket with subject, message, context, attachments (FormData, CSRF) |
| POST | `~reply` | Reply to own ticket (sets status to `waiting_support`) |
| GET | `~download` | Download attachment (owner access check, blocks internal comment files) |
| POST | `~unreadCount` | Total unread count across user's tickets |

### FwSupportAdminController (admin-facing)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `~ticketDetail` | Full ticket detail: messages (including internal), assignment log, parsed context |
| POST | `~reply` | Staff reply (visible to user, auto-assigns if unassigned) |
| POST | `~internalComment` | Internal comment (staff-only, no status/unread change) |
| POST | `~changeStatus` | Change ticket status (inserts system message with translated labels) |
| POST | `~assign` | Assign/unassign ticket (logs to assignment_log, inserts system message) |
| GET | `~download` | Download attachment (moderator access check) |
| POST | `~userTickets` | List all tickets for a specific user |

## Status Flow

Automatic transitions:
- User replies to ticket --> `waiting_support`
- Staff replies to `open` or `waiting_support` ticket --> `waiting_user`
- Staff replies auto-assigns if no assignee is set

Manual transitions via `~changeStatus`: any valid status can be set by staff.

## Auto-Context

The `context` field stores JSON captured by the frontend: current URL, user agent, viewport size, recent JS errors, network errors, and navigation breadcrumb. Parsed and displayed in admin ticket detail.

## Abstract Methods

### FwSupportController (user-facing)
```php
abstract protected static function getUploadDir(): string;
abstract protected static function getSideMenu(string $url): array;
abstract protected static function getMainMenu(string $url): array;
abstract protected static function ticketsTable(): DbTable;
abstract protected static function messagesTable(): DbTable;
abstract protected static function attachmentsTable(): DbTable;
```

### FwSupportAdminController (admin-facing)
All of the above, plus:
```php
abstract protected static function isModerator(): bool;
abstract protected static function assignmentLogTable(): DbTable;
abstract protected static function resolveUserRole(int $accountId): array; // {role, has_expert_profile}
abstract protected static function getStatusLabels(): array;              // status key => translated label
abstract protected static function fetchModerators(): array;              // [{id, login, name}]
abstract protected static function getStatusChangedLabel(): string;
abstract protected static function getAssignedToLabel(): string;
abstract protected static function getUnassignedLabel(): string;
```

## Setup

1. Create four app table classes extending the `Fw*` base tables. Register in migration.
2. Create a user-facing controller extending `FwSupportController`.
3. Create an admin controller extending `FwSupportAdminController`.
4. Register both routes in your app's router.
5. Create frontend island components for the user support page and admin support panel.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../Messaging/README.md`](../Messaging/README.md) — user-to-user direct messaging on the same primitives.
- [`../Logging/README.md`](../Logging/README.md) — admin viewer for support-channel events.

---

↑ Back to [Bundle / Modules](../../README.md)

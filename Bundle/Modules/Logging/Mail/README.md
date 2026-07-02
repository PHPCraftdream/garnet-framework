# Mail Log Module

## Purpose
Email audit log with dev-mode bypass. Wraps any `IMailer` implementation to log every
outgoing email (subject, body, status, recipient). In dev mode, emails to `.test`
addresses are logged but not actually sent.

## Tables

### FwMailLog
- `id` -- auto-increment primary key
- `account_id` -- resolved account ID (nullable, looked up by email)
- `recipient_email` -- destination email address
- `mail_type` -- auto-detected category (e.g. `auth_code`, `general`)
- `subject` -- email subject line
- `body_html` -- full HTML body (LONGTEXT)
- `status` -- `sent`, `failed`, or `skipped_dev`
- `error_log` -- error message on failure (nullable)
- `created_at` -- unix timestamp

Indexes: `account_id`, `mail_type`, `status`, `created_at`.

### FwMailLogRecipients
- `id` -- auto-increment primary key
- `mail_log_id` -- foreign key to mail_log
- `account_id` -- resolved account ID (nullable)
- `recipient_email` -- email address

Indexes: `mail_log_id`, `account_id`.

## FwAppMailer -- Decorator Pattern

`FwAppMailer` implements `IMailer` and wraps another `IMailer` instance. On every
`sendHtmlMail()` call it:

1. Checks if env is `dev` and email ends with `.test` -- if so, logs with
   status `skipped_dev` and returns without sending.
2. Otherwise delegates to the inner mailer, logs `sent` on success.
3. On exception, logs `failed` with the error message, then re-throws.

Account ID is resolved automatically by looking up the recipient email in `db_accounts`.

## Status Values
- `sent` -- email delivered successfully
- `failed` -- send threw an exception (error stored in `error_log`)
- `skipped_dev` -- dev mode, `.test` email address, not sent

## Setup

### 1. Create app tables

```php
class MailLogRecipients extends FwMailLogRecipients {
    protected string $tableName = 'ir_mail_log_recipients';
}

class MailLog extends FwMailLog {
    protected string $tableName = 'ir_mail_log';

    protected static function recipientsTable(): FwMailLogRecipients {
        return MailLogRecipients::get();
    }
}
```

### 2. Create AppMailer

```php
class AppMailer extends FwAppMailer {
    protected function mailLogTable(): DbTable {
        return MailLog::get();
    }
}
```

### 3. Register with framework

```php
Mailer::setInstance(new AppMailer(Mailer::getInstance()));
```

### 4. Query logs

```php
$logs = MailLog::get()->selectAll(function (SelectInterface $q): void {
    $q->orderBy(['id DESC']);
    $q->limit(50);
});
```

## Extension Points
- Override `detectMailType()` to categorize emails beyond the default
  `auth_code`/`general` detection (matches "auth" or Russian equivalent in subject).
- Override `mailLogTable()` to swap in a different log table.

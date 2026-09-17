# System Settings Module

Owner-only admin page for managing application settings: user registration
toggle and SMTP email configuration. Settings are persisted to INI files
(`app.ini` and `email.ini`).

## FwAppSettings

Reads and writes two INI files via `IniConfig`:

- **app.ini** -- `registrations_enabled` (0/1)
- **email.ini** -- `enabled`, `scheme`, `host`, `port`, `user`, `password`,
  `from`, `verify_peer`

### Methods

| Method                | Returns | Description |
|-----------------------|---------|-------------|
| `read()`              | `array{registrationsEnabled, smtp{...}}` | Full settings snapshot |
| `registrationsEnabled()` | `bool` | Whether `/register` is open |
| `smtpSettings()`      | `array{enabled, scheme, host, port, user, password, from, verify_peer}` | Current SMTP config |
| `save(bool $reg, array $smtp)` | `array{settings?} \| array{error?}` | Validate and persist. Returns `error` key on failure |

### Validation (on save)

- `scheme` must be `smtp` or `smtps`
- `port` must be numeric, 1--65535
- When SMTP is enabled: `host` and `from` are required

## FwSystemSettingsController

Abstract controller. Provides three routes:

| Route                  | Method | Description |
|------------------------|--------|-------------|
| `get__main`            | GET    | Renders the settings island inside the layout |
| `post__save`           | POST   | Validates and saves settings; returns JSON |
| `post__sendTestEmail`  | POST   | Saves current SMTP values, sends a test email, then restores original SMTP config |

### Abstract methods to implement

```php
abstract protected static function isAllowed(): bool;
abstract protected static function settingsManager(): FwAppSettings;
abstract protected static function getLabels(): array;
abstract protected static function getSideMenu(string $url): array;
abstract protected static function getMainMenu(string $url): array;
abstract protected static function testEmailSubject(): string;
abstract protected static function testEmailBody(): string;
```

### Overridable methods

| Method         | Default             | Purpose |
|----------------|---------------------|---------|
| `islandName()` | `admin-system-settings` | Island component name |
| `baseUrl()`    | `/admin/system/`    | URL prefix for save/test-email endpoints |

## Built-in i18n

`SystemSettingsI18nDataEn` and `SystemSettingsI18nDataRu` provide all UI
labels (40+ keys each). Keys cover section titles, field labels, hints,
button states, and error/success messages for both SMTP and registration
sections.

## Frontend component

`Framework/Bundle/Front/Common/Modules/SystemSettings/SystemSettingsPage.tsx`

A reusable React component with two tabs (SMTP, Registration). It receives
server-rendered props:

```ts
interface SystemSettingsPageProps {
    settings: SettingsData;   // current values
    saveUrl: string;          // POST endpoint for save
    testEmailUrl: string;     // POST endpoint for test email
    labels: SystemSettingsLabels; // all UI strings
}
```

Features: form state management, save with spinner, inline test-email
sender, toast notifications on success/error.

## Setup

1. Create a controller extending `FwSystemSettingsController`. Implement
   all abstract methods:

```php
class SystemSettingsController extends FwSystemSettingsController {
    protected static function isAllowed(): bool {
        return AccountHelper::isOwner();
    }

    protected static function settingsManager(): FwAppSettings {
        return new FwAppSettings();
    }

    protected static function getLabels(): array {
        return [
            'title'       => t('Admin_SystemSettings'),
            'accessDenied'=> t('Admin_SystemSettings_AccessDenied'),
            'invalidEmail'=> t('Admin_SystemSettings_TestEmail_InvalidAddress'),
            'sendFailed'  => t('Admin_SystemSettings_TestEmail_SendFailed'),
            'testEmailSuccess' => t('Admin_SystemSettings_TestEmail_Success'),
            // ... map all SystemSettingsLabels keys
        ];
    }

    protected static function getSideMenu(string $url): array { /* ... */ }
    protected static function getMainMenu(string $url): array { /* ... */ }
    protected static function testEmailSubject(): string { return 'SMTP Test'; }
    protected static function testEmailBody(): string { return 'Test email.'; }
}
```

2. Register an island that renders `SystemSettingsPage` with the props
   passed from `get__main`.

3. Register the controller route (e.g. `/admin/system/`).

4. Include the i18n data classes in your app's i18n merge.

## Extension points

- Subclass `FwAppSettings` to add app-specific INI keys.
- Override `baseUrl()` and `islandName()` to change the route or island.
- The `getLabels()` method lets you swap in any language or override
  individual strings.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../Auth/README.md`](../Auth/README.md) — the `registrationsEnabled` gate.
- [`../Email/README.md`](../Email/README.md) — uses the SMTP config configured here.

---

↑ Back to [Bundle / Modules](../../README.md)

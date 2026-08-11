# Known issues

## `Account` — no `name()` method

The `Account` class does not expose a `name()` accessor directly. Read
the name through the EAV store instead:

```php
// Wrong:
$account->name();              // Call to undefined method

// Right:
$account->readData('name');    // EAV table accounts_data
$account->readParam('login');  // Main table accounts
```

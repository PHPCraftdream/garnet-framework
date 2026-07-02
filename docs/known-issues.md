# Known issues

## `DbTable::insertBatch` — named parameters on MySQLi

**Problem.** `DbTable::insertBatch()` calls
`QueryTools::makeInsertBatchNamed()`, which produces named placeholders
of the form `:field0`, `:field1`, … The MySQLi driver does not support
named parameters in prepared statements — only positional `?`.

**Error you'll see:**

```
You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version
for the right syntax to use near ':field0, :field1, ...'
```

**Workaround.** Insert in a loop instead of batching:

```php
// Does NOT work on MySQLi:
// TimeSlots::get()->insertBatch($rows);

// Works:
foreach ($rows as $row) {
    TimeSlots::get()->insert($row);
}
```

**Status.** Not yet fixed in the framework. The fix is in
`QueryTools::makeInsertBatchNamed()` — swap named placeholders for
positional ones.

---

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

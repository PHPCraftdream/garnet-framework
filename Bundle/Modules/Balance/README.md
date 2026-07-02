# Balance

Per-account balance and a transactional ledger. Every change to an
account's balance is recorded as an immutable ledger row, so the
balance at any past moment is reconstructable.

## What's here

| Subdir | What it does |
|---|---|
| `Controllers/` | Admin endpoints: balance adjust, ledger view, filters. |
| `Tables/` | `FwAccountBalance` (current balance per account) and `FwBalanceLedger` (the immutable transaction log). |
| `Spec/` | Kahlan specs for balance + ledger invariants. |

## Headline pieces

### `FwAccountBalance`

One row per account. `account_id` is the primary key. Holds the
current numeric balance only — never edit it directly; go through a
ledger entry that flushes the new balance as a side-effect.

### `FwBalanceLedger`

Append-only. Each row records:

- `account_id`, `amount` (signed: positive = credit, negative = debit),
- `entry_type` — one of the well-known enum values,
- `ref_table`, `ref_id` — optional pointer to the entity that
  triggered the change (a booking, an invoice, an admin action),
- `meta` — JSON for human-readable context (admin id, reason, etc.),
- `created_at`.

`entry_type` values currently include `top_up`, `manual`, plus the
booking-domain values (`booking_invoice`, `booking_payment`,
`booking_refund`) the framework still carries from its origin. See
the [Known limitations section in README](../../../README.md#known-limitations-v0x);
v1.0 will rename these to generic `tx_*`.

## Recording a transaction

```php
use PHPCraftdream\Garnet\Bundle\Modules\Balance\Services\BalanceService;

BalanceService::record(
    accountId: $userId,
    amount:    -2500,           // 25.00 in the smallest unit
    entryType: 'manual',
    meta:      ['admin' => $adminId, 'reason' => 'refund #42'],
);
```

The service runs a single transaction:

1. Append a row to `FwBalanceLedger`.
2. Update the cached `FwAccountBalance.amount` (the running total).
3. Optionally emit a logging event via [Logging](../Logging/README.md)
   so the admin viewer shows the change.

## Reading balance + history

```php
$current = FwAccountBalance::get()->balanceFor($userId);
$page    = FwBalanceLedger::get()->paginate($userId, page: 1);
```

History is paginated with the framework-wide `DEFAULT_PAGE_SIZE`. The
admin grid honours the same filters as the rest of the
admin tooling: free-text on `meta`, entry-type chips, date range.

## Don't

- **Don't UPDATE `FwBalanceLedger` rows.** It's append-only. To correct
  a mistake, add a reversing row with the same `ref_*` pointer and a
  `meta.reason` that explains.
- **Don't bypass `BalanceService` to update `FwAccountBalance` directly.**
  The cached total can drift; only the service guarantees the ledger
  and the cache stay aligned.

## Related

- [`../../README.md`](../../README.md) — bundle index.
- [`../EntityHistory/README.md`](../EntityHistory/README.md) — generic audit trail (complementary, not a replacement for the ledger).

---

↑ Back to [Bundle / Modules](../../README.md)

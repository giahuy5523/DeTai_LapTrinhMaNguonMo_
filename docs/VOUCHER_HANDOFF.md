# Voucher schema and checkout handoff for Trọng

This branch delivers the voucher schema and Admin CRUD/validation. Checkout
application and the runtime voucher service belong to Trọng and are intentionally
not implemented here. Use this contract as the handoff so the checkout flow has
one source of discount calculation.

## Schema delivered in this branch

`vouchers` contains:

| Column | Meaning |
| --- | --- |
| `code` | Uppercase normalized code, 3–32 ASCII letters/digits/`-`/`_`, unique |
| `discount_type` | `percentage` or `fixed` |
| `discount_value` | Percentage (0–100, two decimal places) or fixed VND amount |
| `max_discount_amount` | Required VND cap for percentage vouchers; null for fixed |
| `minimum_order_amount` | Minimum pre-discount cart subtotal in VND |
| `usage_limit` | Global successful-order limit; null means unlimited |
| `used_count` | Number of successful orders consuming the voucher |
| `starts_at`, `ends_at` | Inclusive validity timestamps |
| `is_active` | Admin on/off status |

Orders have `voucher_id`, `voucher_code`, `subtotal_amount`, and
`discount_amount`; `total_amount` remains the payable amount. The voucher foreign
key becomes null on deletion while the code and amount snapshots remain.

## Proposed service contract for Trọng

Implement this in the checkout/service layer and use it for both preview and
order creation; do not calculate a second discount in the controller or view:

```php
$quote = $voucherService->quote($code, $subtotalCents);
// voucher_id, code, discount_amount, discount_cents,
// payable_amount, payable_cents

$finalQuote = $voucherService->consume($code, $subtotalCents);
```

Amounts passed to the service are integer cents. `quote()` should check the
normalized code, active status, time window, minimum subtotal, and remaining
uses, then compute the percentage/fixed discount and cap without consuming a
use. `consume()` should repeat those checks under a voucher row lock and
increment `used_count` once.

Call `consume()` inside the same transaction that creates the order and
decrements stock. A failed order write must roll the usage increment back too.
Store the returned amounts in the order snapshot. Count a use when an order is
successfully created; cancellation does not restore it. The percentage result
must be rounded down to cents; fixed discounts must not exceed the subtotal.

## Suggested HTTP wiring

- `POST /checkout/voucher`: validate/preview the code against the current
  server-side cart and store only the normalized code plus cart snapshot in the
  customer session.
- `DELETE /checkout/voucher`: remove the current selection.
- `POST /checkout`: rebuild subtotal from database product prices, revalidate
  and consume inside the order/stock transaction, then save the order snapshot.
- Protect all endpoints with the existing authenticated-customer middleware.

These checkout endpoints are not present yet. Admin CRUD is available under
`/admin/vouchers`, protected by the existing `auth` and `admin` middleware.

## Acceptance cases for checkout integration

1. Lowercase input normalizes to uppercase; duplicate codes differing only by
   case are rejected by Admin validation and the database unique index.
2. Percentage discount is rounded down to cents and capped; fixed discount is
   clamped to subtotal.
3. Inactive, future, expired, under-minimum, and exhausted vouchers are rejected.
4. Preview does not increment usage; a successful order increments once; failed
   order creation rolls the increment back.
5. With a limit of one, concurrent checkouts cannot both consume the last use.
6. Old orders retain voucher code and amount snapshots after deletion.

Admin CRUD tests are in `tests/Feature/VoucherTest.php`. Trọng should add the
service/checkout tests with the integration work.

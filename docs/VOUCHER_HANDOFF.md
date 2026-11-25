# Voucher schema and checkout handoff (Tuấn ↔ Trọng)

> **Trạng thái phối hợp:** **ĐÃ CHỐT CONTRACT & HOÀN TẤT TÍCH HỢP** (25/11/2026).
> Tuấn phụ trách schema, Admin CRUD/validation và diễn tập demo; Trọng triển khai `App\Support\VoucherService`, luồng áp dụng/gỡ voucher ở checkout, cập nhật QR theo số tiền sau giảm và kiểm thử giới hạn lượt dùng đồng thời.

## 1. Schema (`vouchers` & `orders`)

`vouchers` gồm các cột:

| Column | Meaning |
| --- | --- |
| `code` | Uppercase normalized code, 3–32 ASCII letters/digits/`-`/`_`, unique |
| `discount_type` | `percentage` or `fixed` |
| `discount_value` | Percentage (0–100, two decimal places) or fixed VND amount |
| `max_discount_amount` | Required VND cap for percentage vouchers; null for fixed |
| `minimum_order_amount` | Minimum pre-discount cart subtotal in VND |
| `usage_limit` | Global successful-order limit; null means unlimited |
| `used_count` | Number of successful orders consuming the voucher |
| `starts_at`, `ends_at` | Inclusive validity timestamps (`[starts_at, ends_at]`) |
| `is_active` | Admin on/off status |

Bảng `orders` lưu snapshot bất biến `voucher_id` (`ON DELETE SET NULL`), `voucher_code`, `subtotal_amount`, `discount_amount`, và `total_amount` (số tiền phải trả sau giảm). Khi Admin xóa voucher, `voucher_id` chuyển về `NULL` nhưng `voucher_code`, `subtotal_amount`, `discount_amount`, `total_amount` trên đơn cũ vẫn được giữ nguyên.

## 2. Finalized service contract (`App\Support\VoucherService`)

Cả bước xem trước (`POST /checkout/voucher`, `GET /checkout`) và bước tạo đơn (`POST /checkout`) đều dùng chung một nguồn tính duy nhất tại `App\Support\VoucherService` (không tính giảm giá lần hai ở controller hay view):

```php
$quote = app(\App\Support\VoucherService::class)->quote($code, $subtotalCents);
// Returns:
// voucher_id, code, discount_type, discount_value, max_discount_amount,
// subtotal_cents, subtotal_amount, discount_cents, discount_amount,
// payable_cents, payable_amount

$finalQuote = app(\App\Support\VoucherService::class)->consume($code, $subtotalCents);
```

- Mọi số tiền truyền vào là số nguyên xu (`int $subtotalCents` qua `App\Support\Money::cents()`).
- `quote()` chuẩn hóa mã chữ hoa (`VoucherService::normalizeCode()`), kiểm tra tồn tại, `is_active`, khung giờ bao gồm hai đầu mút `[starts_at, ends_at]`, giá trị đơn tối thiểu `minimum_order_amount`, và lượt còn lại (`usage_limit === null || used_count < usage_limit`) mà **không** tăng `used_count`.
- Tính giảm giá phần trăm bằng phép chia nguyên chống tràn `intdiv($subtotalCents, 10000) * $rateBasisPoints + intdiv(($subtotalCents % 10000) * $rateBasisPoints, 10000)` (luôn làm tròn xuống đến xu), áp trần `max_discount_amount`, và kẹp không vượt `$subtotalCents`. Giảm cố định (`fixed`) được kẹp tối đa bằng `$subtotalCents`.
- `consume()` chạy **bên trong cùng `DB::transaction`** với `StockReservationService::consume()`, khóa dòng voucher bằng `lockForUpdate()`, kiểm tra lại toàn bộ điều kiện rồi cập nhật nguyên tử `whereColumn('used_count', '<', 'usage_limit')->increment('used_count')`. Nếu tạo đơn hoặc trừ kho lỗi, toàn bộ transaction (bao gồm `used_count`) được rollback.
- Lượt dùng chỉ tăng khi đơn tạo thành công; hủy đơn (Customer hoặc Admin) hoàn kho sản phẩm nhưng **không** hoàn lại `used_count`.

## 3. Finalized HTTP wiring

Tất cả endpoint checkout nằm trong nhóm middleware `['auth', 'customer']`:

- `POST /checkout/voucher` (`checkout.voucher.apply`): làm mới giỏ từ DB, gọi `VoucherService::quote()`, lưu `session('checkout_voucher') = ['code' => $normalizedCode, 'snapshot' => CartSnapshot::version($cart)]` và quay lại `/checkout` (giữ trạng thái chọn COD/QR).
- `DELETE /checkout/voucher` (`checkout.voucher.remove`): xóa `session('checkout_voucher')`.
- `GET /checkout` (`checkout.index`): nếu có `checkout_voucher` khớp snapshot giỏ hàng thì gọi `VoucherService::quote()` hiển thị Tạm tính / Giảm giá / Tổng thanh toán và nhúng số tiền sau giảm + mã voucher vào QR chuẩn ISO/IEC 18004; nếu voucher vừa bị Admin tắt/hết hạn/hết lượt thì tự gỡ và hiện cảnh báo.
- `POST /checkout` (`checkout.store`): tính lại subtotal từ giá DB đã khóa, gọi `VoucherService::consume()` trong cùng transaction với `StockReservationService::consume()`, lưu snapshot đơn hàng rồi xóa `['cart', 'checkout_quote', 'checkout_voucher']`. Replay idempotent với cùng `checkout_token` không trừ thêm lượt voucher.
- Mọi thao tác sửa giỏ hàng (`CartController@add`, `update`, `remove`, `CartSnapshot::refresh` chuẩn hóa) đều xóa `checkout_voucher` cũ để tránh lệch giá.
- Admin CRUD nằm tại `/admin/vouchers` (middleware `['auth', 'admin']`), hiển thị `used_count / usage_limit` và trạng thái *Đang bật / Đã tắt / Chưa đến hạn / Hết hạn / Hết lượt*.

## 4. Acceptance matrix (đã kiểm chứng)

| # | Tiêu chí nghiệm thu | Bằng chứng kiểm thử | Kết quả |
| --- | --- | --- | --- |
| 1 | Chuẩn hóa chữ thường → chữ hoa; từ chối trùng mã khác hoa/thường ở Admin & unique index DB | `VoucherTest`, `CheckoutVoucherTest` | **PASS** |
| 2 | Phần trăm làm tròn xuống theo xu và áp trần `max_discount_amount`; giảm cố định kẹp theo subtotal | `CheckoutVoucherTest::test_voucher_service_normalizes_case_rounds_percentage_down_and_caps_or_clamps_discounts` | **PASS** |
| 3 | Từ chối voucher tắt, tương lai, hết hạn, dưới tối thiểu, hết lượt; biên `[starts_at, ends_at]` bao gồm 2 đầu mút | `CheckoutVoucherTest` (rejection + inclusive window tests) | **PASS** |
| 4 | Xem trước không tăng lượt; đặt thành công tăng đúng 1; tạo đơn lỗi rollback lượt; hủy đơn không hoàn lượt; replay idempotent không trừ 2 lần | `CheckoutVoucherTest` | **PASS** |
| 5 | Giới hạn 1 lượt: 2 tiến trình checkout đồng thời không thể cùng dùng lượt cuối | `CheckoutVoucherTest::test_concurrent_two_process_checkouts_cannot_both_consume_single_use_voucher` & `tests/manual/voucher-concurrency.php` (3/3 vòng `[accepted, rejected]`) | **PASS** |
| 6 | Đơn cũ giữ nguyên `voucher_code`, `subtotal_amount`, `discount_amount`, `total_amount` sau khi xóa voucher | `CheckoutVoucherTest` & diễn tập demo bước 14 (`docs/DEMO_REHEARSAL_RESULT.md`) | **PASS** |

Xem thêm biên bản diễn tập máy demo tại [docs/DEMO_REHEARSAL_RESULT.md](DEMO_REHEARSAL_RESULT.md).

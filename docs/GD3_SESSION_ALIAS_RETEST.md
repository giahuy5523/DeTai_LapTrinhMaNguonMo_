# GĐ3 — Kiểm thử session và ID sản phẩm tương đương

Ngày 23/10/2026. Nhánh `codex/gia-huy-gd3`.

**R07 đã sửa và kiểm chứng trên SQLite/MariaDB/HTTP.** Suite hiện hành đạt
**123 tests / 2.884 assertions**. Xem [bản tổng hợp và bản sửa](GD3_FINAL_QA.md).
Phần dưới giữ bằng chứng trước sửa; không còn là lỗi đang mở.

## R07 — ID có số 0 đầu tạo hai dòng cùng một Product

Route hiện tại: `POST /gio-hang/them/{id}`, yêu cầu auth + customer.
`CartController::add` tìm Product theo ID nhưng dùng **chuỗi ID của route** làm khóa cart.
Database coi `123` và `0123` là cùng ID số, trong khi PHP array giữ hai khóa khác nhau.

Tái hiện bằng fixture DB test:

1. Tạo Product có quantity=5, price=10.00.
2. Thêm 3 qua route với ID chuẩn.
3. Thêm 3 qua route với ID có số 0 đầu. Cả hai lần đều thấy từng dòng không quá stock 5.
4. GET checkout lấy quote thật, POST checkout với quote đó.

Kết quả MariaDB thực tế:

```json
{
  "initial_stock": 5,
  "cart_keys": [123, "0123"],
  "checkout_accepted": true,
  "sold_quantity": 6,
  "stock_after_checkout": -1,
  "stock_invariant_passed": false,
  "all_fixture_writes_rolled_back": true
}
```

`CartSnapshot::refresh` có thể đánh dấu dòng alias không khả dụng do key không khớp,
nhưng GET/POST checkout trực tiếp vẫn tiếp tục. Vì vậy chỉ ẩn nút trong Cart không đủ.
Không cần chạy đồng thời; một tài khoản Customer gửi tuần tự cũng tái hiện được.

Nguyên nhân tiếp theo ở checkout: kiểm tra từng dòng và gom `orderItems` trước khi
trừ kho. Cùng Product xuất hiện hai lần đều được so quantity=3 với stock=5; sau đó
hai lần decrement khiến stock âm. Transaction/row lock không tự cộng gộp dòng trùng.

Hướng sửa cần kiểm chứng:

- Chuẩn hóa key giỏ theo **Product ID thực tế**, không theo biểu diễn của route.
- Xử lý cart legacy chứa alias: gộp/từ chối dòng trùng; không chỉ sửa thao tác add mới.
- Checkout phải bảo đảm tổng quantity của từng Product không vượt stock trước khi ghi.
- Kiểm tra lại add/update/remove và token snapshot sau chuẩn hóa; tránh giữ alias hoặc
  xóa nhầm dòng khác. `whereNumber` đơn thuần vẫn cho qua ID có số 0 đầu.

## Test và kết quả

```powershell
& ./storage/app/php83/php.exe vendor/phpunit/phpunit/phpunit --filter SessionAndAliasRetestTest
& ./storage/app/php83/php.exe tests/manual/cart-id-alias-audit.php
```

- Bộ thăm dò mới: **4 tests / 32 assertions**, 3 đạt, 1 thất bại do stock=-1.
- Script MariaDB tự guard schema `sneakershop_gd3_test`, luôn rollback toàn bộ ghi
  fixture kể cả Order/OrderItem/Product. Exit 1 hiện là bằng chứng lỗi chưa sửa.
- Test PHPUnit hiện chạy riêng ngoài suite mặc định; cần chuyển vào hồi quy mặc định
  sau khi sửa. XML ignored `storage/app/gd3-session-alias.xml`.
- Suite mặc định chạy lại: **103 tests / 748 assertions đạt**, XML ignored
  `storage/app/gd3-alias-baseline.xml`. Kết quả xanh này không bao gồm ca R07 đang đỏ.

## Các ca mới đạt

1. Logout xóa cart/quote/last_checkout_order_id; đăng nhập tài khoản khác không thừa
   hưởng giỏ và đơn của người trước.
2. Gửi lại token của đơn đã hủy chỉ nhận lại đơn cancelled, không mở lại đơn hay trừ
   kho lần hai; trang success vẫn hiển thị trạng thái đã hủy.
3. Bật CSRF middleware thật trong test: POST thêm giỏ/checkout không token trả 419,
   không đổi stock hoặc ghi đơn.

Đối chứng `deep-mysql-audit.php`: 6 ca cũ đạt, DB ứng dụng chỉ đọc và counts không đổi
(users=1, categories=2, products=0, orders=0, order_items=0). Pint các file test mới và
`git diff --check` đạt. Chưa commit/push/merge; chưa sửa R07 trong lượt tester này.

Lượt này xác minh controller thật và route hiện có; không có lượt kiểm thử Chrome mới.

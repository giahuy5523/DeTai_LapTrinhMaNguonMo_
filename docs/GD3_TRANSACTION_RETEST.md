# GĐ3 — Kiểm thử transaction và cạnh tranh bổ sung

> **Mới nhất:** R06 cũng đã sửa; suite đạt **103 tests / 748 assertions**.
> Xem [kiểm chứng biên phone](GD3_BOUNDARY_RETEST.md). R01–R06 đã đóng.

Ngày: 23/10/2026. Nhánh `codex/gia-huy-gd3`.

**Trạng thái sau sửa: R05 đã đóng.** Toàn bộ suite đạt **96 tests / 700 assertions**.
Hai worker MariaDB cùng token mua sản phẩm cuối đều nhận lại đúng một đơn.
R01–R04 của lượt trước vẫn đã được sửa. Phần tái hiện dưới đây là bằng chứng trước sửa.

## R05 — Phản hồi gửi lặp không nhất quán khi mua sản phẩm cuối

Mức đề xuất: **P2, độ tin cậy của cơ chế idempotency dự phòng**.

Điều kiện tái hiện: hai worker dùng cùng user/cart/checkout token nhưng không dùng
chung khóa session; cả hai SELECT đơn theo token đều trả rỗng trước khi transaction
đầu tiên bắt đầu. Chỉ còn đúng một sản phẩm trong kho.

`tests/manual/last-unit-replay.php` dùng hai process/connection MariaDB độc lập và
barrier sau SELECT token để tạo đúng điều kiện này, không phụ thuộc bấm nhanh bằng tay.

Kết quả thực tế (Product 94, Order 53):

- Worker thứ nhất thành công, tạo đơn 53 và trừ stock 1 → 0.
- Worker thứ hai báo validation “không đủ số lượng … còn: 0”, không nhận lại đơn 53.
- Đúng **1 đơn**, không bán vượt kho hoặc trừ kho hai lần.
- Sau kiểm tra, đơn được hủy, stock về 1 và fixture được ẩn.

Kỳ vọng của lớp dự phòng: cùng yêu cầu đã thành công phải trả cùng đơn đã tạo,
kể cả khi đơn đó vừa mua hết hàng. Test trước với stock 5 vẫn đạt vì worker thứ hai
đi đến unique index; test mới stock 1 bị chặn sớm ở kiểm tra tồn kho.

Nguyên nhân: `CheckoutController::store` tra token trước transaction. Khi hai SELECT
đều chưa thấy đơn, worker sau đợi row lock rồi thấy stock 0 và ném ValidationException;
không tới INSERT nên nhánh xử lý QueryException/unique token không chạy.

Phạm vi tác động: **không khẳng định lỗi xảy ra ở double-click thông thường** vì
khóa session hiện tại ngăn hai request cùng session chạy vào đoạn này. Đây là nhánh
dự phòng ngoài khóa session đã được triển khai/kiểm thử trước đó. Không phải lỗi
phân quyền hay mất tính toàn vẹn đơn/kho.

Hướng sửa cần kiểm chứng: khi đã giành khóa sản phẩm, kiểm tra lại đơn theo token
bằng cách đọc thấy dữ liệu mới nhất trước khi kết luận thiếu kho/giá thay đổi;
tiếp tục giữ unique index và scope user. Phải kiểm tra MariaDB transaction isolation,
không chỉ thêm SELECT thông thường mà chưa chứng minh đọc thấy commit mới.

Chạy lại (chỉ DB test, script tự guard; sau sửa trả exit 0):

```powershell
.\scripts\php.ps1 tests/manual/last-unit-replay.php
```

## Các ca hồi quy mới đạt

`tests/Feature/TransactionRetestTest.php`: **11 tests / 107 assertions**.

1. Gây lỗi ở lần ghi OrderItem thứ hai: rollback Order/Item đầu tiên/stock; giữ cart và quote.
2. Gây lỗi lưu trạng thái cancelled sau hoàn kho: rollback stock, đơn vẫn pending.
3. Hoàn sản phẩm thứ hai bị overflow: rollback cả phần hoàn sản phẩm thứ nhất.
4. Gây lỗi xóa Category: giữ Category/Product và file ảnh.
5. Checkout nhận array ở từng trường: validation, render lại form, không ghi đơn/trừ kho.
6. Validation đăng ký không flash password/password_confirmation vào session.
7. Sản phẩm giá 0 được checkout đúng tổng 0, stock vẫn giảm bình thường.
8. Đổi tên/giá Product không viết lại snapshot của đơn đã đặt.
9. Client giả total/price/owner/status/quantity không thay được giá trị đơn tính từ server.
10. Customer không sửa/xóa hoặc mở form sửa Category/Product của Admin.
11. Form Product không nhận slug/is_active giả ngoài các trường được hỗ trợ.

Đã chạy suite chung để kiểm tra listener dùng gây lỗi được dọn đúng, không làm hỏng test khác:
**94 tests / 682 assertions, 0 failures/errors**. XML ignored:
`storage/app/gd3-next-retest.xml`.

## Đối chứng MariaDB

- `deep-lock-audit.php`: thứ tự khóa sản phẩm đảo ngược, hủy cùng đơn bởi
  Admin/Customer, cùng token với stock còn dư: đạt.
- `deep-mysql-audit.php`: 6 ca biên tiền/TEXT/hoàn kho: đạt.
- `ban_giay_the_thao` chỉ đọc, counts vẫn users=1, categories=2, products=0,
  orders=0, order_items=0.
- Pint hai file kiểm thử mới: đạt. Chưa commit/push/merge.

SQLite suite xanh không thay thế test concurrency MariaDB đang đỏ; chưa kết luận
không còn lỗi khác. Lượt này tập trung transaction/input/authorization, không tính
các lần chạy giao diện trước là một lượt kiểm thử trình duyệt mới.

## Sửa R05 và kiểm chứng

`CheckoutController::store` xử lý thêm ValidationException phát sinh trong transaction.
Khi transaction đã rollback, tra lại đơn theo **checkout_token + user_id** bên ngoài
snapshot cũ. Nếu worker khác đã commit đơn thì trả lại chính đơn đó; nếu không có,
giữ nguyên exception để báo lỗi thật. Nhánh QueryException/unique token vẫn được giữ;
không thêm khóa hoặc thay isolation level.

- MariaDB, barrier buộc cả hai initial lookup đều rỗng: Product 101, cả hai worker
  nhận Order 61; đúng 1 đơn; stock 1 → 0. Cleanup hủy đơn, stock về 1, ẩn fixture.
- Test hồi quy SQLite mới mô phỏng đơn thắng được ghi sau initial lookup nhưng trước
  kiểm tra stock: trả đúng đơn, không thêm OrderItem/trừ kho lần hai, dọn cart/quote.
- Test mới bảo đảm không phục hồi đơn của user khác khi validation thất bại.
- Suite đầy đủ: **96 tests / 700 assertions đạt**; XML ignored `storage/app/gd3-r05-fixed.xml`.
- Chạy lại MariaDB `gd3-concurrency.php` (3 vòng), `deep-lock-audit.php` và
  `deep-mysql-audit.php`: đạt, không hồi quy bán vượt kho/hoàn kho/tiền.
- Pint các file PHP sửa và `git diff --check`: đạt.
- DB ứng dụng chỉ đọc, counts vẫn 1 user / 2 categories / 0 products / 0 orders / 0 items.
- Chưa commit/push/merge; chưa triển khai migration vào DB ứng dụng.

Đoạn kết luận test MariaDB đỏ phía trên ghi nhận thời điểm trước sửa;
ca này hiện đã đạt. Không tuyên bố kiểm thử hết mọi tình huống ngoài các ca được nêu.

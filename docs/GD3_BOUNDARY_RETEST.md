# GĐ3 — Kiểm thử biên upload, phân trang và độ dài SQL

> **Hiện hành:** R01–R08 đã đóng; xem [QA tổng hợp](GD3_FINAL_QA.md).
> Suite hiện tại 123 tests / 2.884 assertions đạt; số liệu dưới là lịch sử.

Ngày: 23/10/2026. Nhánh `codex/gia-huy-gd3`.

**Cập nhật sau sửa: R06 đã đóng.** Suite mặc định đạt **103 tests / 748 assertions**.
MariaDB xác nhận phone 20 ký tự hợp lệ, 21 ký tự trả validation ở trường phone;
không còn SQL 1406 ở ca tái hiện. Phần dưới giữ bằng chứng ban đầu trước sửa.

## R06 — Phone 21 ký tự vượt cột SQL nhưng lọt validation

Mức đề xuất **P2**: đầu vào sai gây exception SQL thay vì lỗi form.

- `orders.phone` là VARCHAR(20).
- Rule phone trong `CheckoutController::store` cho phép dấu `+` tùy chọn, một số đầu,
  7–18 ký tự giữa và một số cuối: tổng có thể lên **21 ký tự**.
- Kiểm tra bổ sung 9–15 chữ số không giới hạn tổng độ dài, không chặn trường hợp này.
- Ví dụ `+012345678----------9` dài 21 ký tự, có 11 chữ số; qua được cả hai kiểm tra.
- Form HTML có `maxlength="20"`, nhưng server chưa kiểm tra tương đương; gửi POST
  trực tiếp có thể vượt giới hạn HTML. Không khẳng định gõ bình thường trong form gây lỗi này.

Bằng chứng thực thi:

| Nền kiểm thử | Đầu vào | Kết quả |
| --- | --- | --- |
| SQLite PHPUnit | Phone 21 ký tự | Checkout nhận và redirect 302; test mong 422 thất bại vì SQLite không ép độ dài VARCHAR |
| MariaDB 10.4.32 | Phone 20 ký tự, 11 chữ số | Tạo đơn đúng, stock 3 → 2 trong transaction kiểm thử |
| MariaDB 10.4.32 | Phone 21 ký tự, 11 chữ số | QueryException, driver code **1406**; 0 đơn mới, stock vẫn 3 |

MariaDB được gọi qua controller thật; lượt này không xác minh status HTTP bằng trình duyệt.
Handler hiện không chuyển mã 1406 thành validation. Cần chặn độ dài ở validation phone
trước khi ghi SQL, giữ các kiểm tra định dạng/số chữ số và thêm ca hồi quy 20/21 ký tự.

Script SQL luôn rollback toàn bộ fixture, chỉ chạy nếu schema thực tế đúng
`sneakershop_gd3_test`. Cả ca thành công 20 ký tự cũng được rollback; không để lại đơn.

```powershell
.\scripts\php.ps1 vendor/phpunit/phpunit/phpunit --filter BoundaryRetestTest
.\scripts\php.ps1 tests/manual/phone-length-audit.php
```

Trước sửa hai lệnh trả exit 1. Sau sửa ca này đã đạt; test chuyển sang
`tests/Feature/BoundaryRetestTest.php` để chạy trong suite mặc định.

## Các ca mới đạt

1. Xóa Category có Product đã nằm trong đơn: cascade xóa Product, OrderItem giữ
   tên/giá snapshot và product_id thành NULL; Customer vẫn xem/hủy được đơn.
2. Xóa file ảnh thất bại: Product đã xóa trong DB, phản hồi vẫn thành công kèm warning;
   không biến thao tác đã commit thành lỗi giả. File không xóa được vẫn giữ để xử lý sau.
3. Upload nội dung PHP đặt tên PNG và SVG có script bị chặn, không có file/bản ghi mới.
   Dùng UploadedFile thật bọc file tạm để kiểm tra MIME từ nội dung; không lấy MIME giả
   theo đuôi file của test helper làm bằng chứng upload thực tế.
4. 11 Product có created_at giống nhau: trang 1 ID 11–7, trang 2 ID 6–2, không lặp;
   keyword chứa script được escape trong HTML.
5. Form sửa Product đã bị xóa trả 404, không tạo lại bản ghi từ form cũ.

## Đối chứng và an toàn dữ liệu

- Suite mặc định: **96 tests / 700 assertions đạt**, XML ignored
  `storage/app/gd3-after-boundary-baseline.xml`.
- Bộ thăm dò: `tests/manual/BoundaryRetestTest.php`; XML ignored
  `storage/app/gd3-boundary-retest.xml`.
- `deep-mysql-audit.php`: 6 ca tiền/TEXT/stock cũ đạt.
- DB ứng dụng chỉ đọc, counts vẫn users=1, categories=2, products=0, orders=0, order_items=0.
- Pint hai file test mới và `git diff --check`: đạt. Chưa commit/push/merge.

Chưa kết luận hết lỗi; lượt này không có kiểm thử trình duyệt mới hoặc đo tải.

## Bản sửa R06 và kết quả hiện tại

- Thêm `max:20` vào validation phone trong `CheckoutController::store`, giữ kiểm tra
  định dạng và 9–15 chữ số. Không sửa schema hoặc dữ liệu hiện có.
- Chuyển 6 ca biên vào suite Feature, thêm ca phone đúng 20 ký tự; kiểm tra phone
  quá dài không tạo đơn/trừ kho và vẫn giữ cart để sửa.
- Suite đầy đủ: **103 tests / 748 assertions đạt**; XML ignored `storage/app/gd3-r06-fixed.xml`.
- `phone-length-audit.php` trên MariaDB: 20 ký tự accepted, 21 ký tự validation phone;
  0 đơn mới và stock vẫn 3 cho ca bị từ chối. Mọi ghi fixture đều rollback.
- `deep-mysql-audit.php`: 6 ca đạt; DB ứng dụng chỉ đọc, counts không đổi
  (users=1, categories=2, products=0, orders=0, order_items=0).
- Pint và `git diff --check` đạt. Chưa commit/push/merge.
- Phiên PowerShell hiện chặn script `.ps1`; đã chạy trực tiếp
  `& ./storage/app/php83/php.exe ...`, không đổi Execution Policy hệ thống.

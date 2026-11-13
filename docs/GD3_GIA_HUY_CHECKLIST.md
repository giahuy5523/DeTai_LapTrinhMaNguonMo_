# GĐ3 — Gia Huy

> **Trạng thái hiện hành:** R01–R08 đã đóng; **131 tests / 2.970 assertions đạt**.
> [Báo cáo QA tổng hợp](GD3_FINAL_QA.md). Các số liệu dưới đây là lịch sử trước đó.

> **Mới nhất:** R01–R06 đã đóng; suite **103 tests / 748 assertions đạt**.
> Xem [bản sửa và kiểm chứng R06](GD3_BOUNDARY_RETEST.md). Chưa commit/push/merge.

> **Trạng thái mới nhất:** R05 đã sửa; **96 tests / 700 assertions đạt**,
> ca hai worker MariaDB mua sản phẩm cuối đạt. [Kiểm chứng](GD3_TRANSACTION_RETEST.md).

> **Sau sửa nghiệm thu lại:** R01–R04 đã xử lý; suite **83 tests / 575 assertions đạt**.
> Xem [kết quả độc lập và bản sửa](GD3_ACCEPTANCE_RETEST.md). Chưa commit/push/merge.

> **Cập nhật 23/10/2026:** đã sửa các lỗi trong audit và chạy lại kiểm thử.
> Kết quả hiện tại: **69 tests / 481 assertions**, MariaDB và Chrome đạt các ca đã nêu.
> Xem [bản sửa và kiểm chứng cuối](GD3_FIX_VERIFICATION.md).
> Phần dưới là nhật ký ngày 22/10 trước vòng sửa; chưa commit/push/merge GĐ3.

Ngày kiểm chứng: **22/10/2026** (Asia/Saigon).
Nhánh: `codex/gia-huy-gd3`, bắt đầu từ `develop` / `origin/develop`
`804fdc629318b35fd17f807d27bb79cc76af2101`, đối chiếu bằng fetch live.

## Kết quả

| Hạng mục | Bằng chứng | Kết quả |
| --- | --- | --- |
| Toàn bộ test tự động | `php vendor/phpunit/phpunit/phpunit`: 31 tests, 291 assertions; SQLite memory | Đạt |
| Admin CRUD danh mục | Chrome: thêm, xem, sửa tên, xóa danh mục demo | Đạt |
| Admin CRUD sản phẩm/ảnh | Chrome: thêm/upload PNG, sửa giá, thay ảnh, ảnh truy cập được, tìm kiếm, xóa | Đạt |
| Tích hợp mua hàng | Chrome: khách mua 2 sản phẩm giá 1.600.000; Admin pending → processing → cancelled; stock 5 → 3 → 5 | Đạt |
| Responsive | 1440×900 và 390×900; Home, Store, Dashboard, Products, Orders, Cart, Checkout, Success; scrollWidth bằng viewport | Đạt |
| Menu mobile | Chrome 390px: mở menu và thấy liên kết Quản trị | Đạt |
| Giá thay đổi | MariaDB: session giữ giá 1, DB đổi 1.700.000; mua 2 tính đúng 3.400.000 | Đạt |
| Thiếu tồn kho/ngừng bán | MariaDB: từ chối, không tạo thêm đơn, giữ giỏ | Đạt |
| Hai người mua sản phẩm cuối | Hai PHP process / connection riêng, barrier đồng bộ; 3 vòng, mỗi vòng 1 chấp nhận + 1 từ chối, stock 0 | Đạt |
| Hủy cùng đơn đồng thời | Hai PHP process; 3 vòng, mỗi vòng 1 chấp nhận + 1 từ chối, stock hoàn đúng 1 | Đạt |
| Bảo toàn DB ứng dụng | `ban_giay_the_thao`: trước/sau users=1, categories=2, products=0, orders=0, order_items=0; chỉ đọc | Đạt |

MariaDB XAMPP **10.4.32**, không phải MySQL 8. Kiểm thử đồng thời gọi controller
thật trong hai tiến trình, dùng transaction/row lock thật; không phải tải HTTP đồng thời.
Các ca quyền riêng tư đơn, sản phẩm bị xóa, rollback nhiều sản phẩm và form sai
được bổ sung trong `tests/Feature/GiaHuyPhaseThreeTest.php` trên SQLite.

## Thay đổi

- Sửa ký tự `>` thừa ở ô tên trong form sửa sản phẩm.
- Bổ sung 6 test GĐ3 (5 ca tích hợp và 1 ca chặn seeder sai DB), cùng script concurrency MariaDB/MySQL riêng.
- Thêm `Gd3DemoSeeder` có chặn database/môi trường và yêu cầu mật khẩu từ biến môi trường.
  Không nối seeder này vào `DatabaseSeeder`; không sửa role/mật khẩu tài khoản có sẵn.
- Thêm SVG minh họa tự tạo và ảnh chụp desktop/mobile `docs/gd3-*.png`.
- Bỏ qua log/snapshot Playwright trong Git vì có thể chứa dữ liệu phiên kiểm thử.

## Chạy lại

1. Khởi động MySQL XAMPP. Dùng PHP **8.3+** cho cài đặt mới với lock file hiện tại
   (Pint yêu cầu 8.3; một số thư viện yêu cầu 8.2). Không dùng `composer update` để né lỗi môi trường.
2. Tạo database riêng `sneakershop_gd3_test`, UTF8MB4. Không trỏ thao tác seed/test vào DB ứng dụng.
3. Trong PowerShell của dự án, đặt biến môi trường cho riêng phiên chạy:

   ```powershell
   $env:APP_ENV = 'testing'
   $env:DB_CONNECTION = 'mysql'
   $env:DB_DATABASE = 'sneakershop_gd3_test'
   # Đặt GD3_DEMO_PASSWORD riêng, tối thiểu 12 ký tự, ngoài Git.
   php artisan migrate
   php artisan db:seed --class=Gd3DemoSeeder
   php artisan storage:link
   php artisan serve --host=127.0.0.1 --port=8013
   ```

   `storage:link` chỉ cần khi liên kết chưa tồn tại. Không dùng config cache của DB ứng dụng.
   Tài khoản: `admin@gd3.example.test`, `customer@gd3.example.test`,
   `customer2@gd3.example.test`. Seed lại giữ nguyên bản ghi hiện có, không reset stock/mật khẩu.
   Dữ liệu demo gồm 13 sản phẩm, một sản phẩm hết hàng và một sản phẩm ngừng bán.
   Đơn demo được tạo bằng luồng checkout, không ghi cứng lịch sử đặt hàng trong seeder.

4. Chạy `php tests/manual/gd3-concurrency.php`. Script tự chặn DB khác,
   chạy 3 vòng, giữ các bản ghi kiểm thử để đối chiếu; chỉ xóa file barrier của chính lần chạy.
5. Chạy bộ test thường bằng `php vendor/phpunit/phpunit/phpunit`;
   `phpunit.xml` force SQLite `:memory:`, không dùng DB demo.

Trong phiên kiểm thử ngày 22/10, mật khẩu ngẫu nhiên được lưu cục bộ tại
`storage/app/gd3-credentials.json` (đã được Git ignore); không ghi vào báo cáo/GitHub.
PHP 8.1.25 với vendor sẵn có đã chạy đạt test, nhưng Composer install từ lock
bị chặn vì yêu cầu phiên bản PHP; đây là giới hạn môi trường, không kết luận cài mới PHP 8.1 đạt.

## Trạng thái bàn giao

Đã kiểm thử local, trình duyệt và MariaDB tách biệt. Chưa commit/push/tạo PR/merge.
Không tạo Admin hoặc thêm dữ liệu vào DB ứng dụng. Chưa chuẩn bị demo trực tiếp trên
`ban_giay_the_thao`; nếu cần dùng database đó để thuyết trình phải được trưởng nhóm đồng ý riêng.
Không làm slide/báo cáo thuyết trình, thanh toán online hoặc chức năng mở rộng.

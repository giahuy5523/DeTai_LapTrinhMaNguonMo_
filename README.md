# SneakerShop — Website bán giày thể thao

Đồ án Lập trình mã nguồn mở. Laravel 12, PHP 8.3+ x64, MySQL/MariaDB, Blade và Bootstrap 5.
Nhánh tích hợp: `develop`. Workflow: feature → PR → develop; `main` dành cho bản ổn định.

Kiểm chứng local mới nhất: **131 tests / 2.970 assertions đạt**, R01–R08 đã xử lý.
Xem [trạng thái QA hiện hành và phạm vi kiểm thử](docs/GD3_FINAL_QA.md).
Các bước bàn giao/triển khai: [release checklist](docs/GD3_RELEASE_CHECKLIST.md).
Đã merge [PR #19 vào develop](https://github.com/giahuy5523/DeTai_LapTrinhMaNguonMo_/pull/19);
xem [trạng thái bàn giao và backup](docs/GD3_HANDOFF_STATUS.md).

## Phân công

| Thành viên | Phần chính |
| --- | --- |
| Gia Huy | Nền Laravel/DB, layout, CRUD Category/Product/ảnh, Home/Admin, dashboard, ERD/CRUD, kiểm thử tích hợp |
| Tuấn | Đăng ký/đăng nhập/đăng xuất, role và middleware |
| Trọng | Giỏ session, checkout, Order/OrderItem, hủy/hoàn kho |
| Vĩ | Tìm kiếm/phân trang, lọc và trạng thái đơn |

## Môi trường

- PHP **8.3+ x64**, Composer 2; bật PDO MySQL, PDO SQLite/SQLite3, mbstring, fileinfo, OpenSSL, cURL, XML/DOM và GD để chạy toàn bộ test ảnh.
- MySQL/MariaDB; máy kiểm chứng dùng MariaDB 10.4.32 của XAMPP.
- PHP 8.1 mặc định trong XAMPP cũ không chạy được lock file hiện tại.
- Bản sửa nâng framework lên Laravel 12.70.1, Sanctum 4 và PHPUnit 11 để xử lý các advisory dependency đã xác minh. Cấu trúc Kernel/Providers/Blade được giữ; không thêm bảng nghiệp vụ.
- Bootstrap 5.3.8 được lưu tại `public/vendor/bootstrap` kèm LICENSE; trình duyệt không cần internet để tải CSS/JS. Giao diện hiện tại không cần build Vite.

Trên máy Gia Huy, PHP portable đã được chuẩn bị cục bộ ở `storage/app/php83` (không đưa vào Git).
Máy khác cài PHP từ [nguồn chính thức](https://windows.php.net/download/) và thêm PHP 8.3+ vào PATH,
hoặc đặt `PHP_BIN` trỏ đến php.exe. Không cần thay PHP/Apache của XAMPP nếu dùng PHP CLI server. Migration orders.checkout_token nullable/unique đã được triển khai trên DB ứng dụng sau khi Gia Huy đồng ý: schema hiện 9 bảng/70 cột, dữ liệu nghiệp vụ giữ nguyên.
`scripts/php.ps1` chọn PHP_BIN → PHP portable của dự án → PHP trong PATH, và kiểm tra phiên bản trước khi chạy. Khi chạy artisan serve, wrapper tự thêm --no-reload để giữ đúng biến môi trường/DB cho child process Windows.

## Dựng ứng dụng

1. Chạy `composer install` bằng PHP 8.3+. Máy đang dùng portable có thể chạy:

   ```powershell
   .\scripts\php.ps1 C:\ProgramData\ComposerSetup\bin\composer.phar install
   ```

2. Copy `.env.example` thành `.env` nếu chưa có. Điền kết nối DB, `APP_URL`; chạy `php artisan key:generate` **chỉ với bản cài mới chưa có APP_KEY**. Không tạo lại key của bản đang dùng.
3. Tạo database `ban_giay_the_thao` nếu chưa có, rồi chạy `php artisan migrate:status` và `php artisan migrate`. Không dùng migrate:fresh/db:wipe trên dữ liệu ứng dụng.
4. Chạy `php artisan storage:link` nếu `public/storage` chưa có; thư mục storage/bootstrap/cache cần quyền ghi.
5. Chạy `php artisan serve --host=127.0.0.1 --port=8023 --no-reload` bằng đúng PHP. Với PowerShell có thể dùng `.\scripts\php.ps1 artisan serve --host=127.0.0.1 --port=8023`.

Không tự chạy DatabaseSeeder trên DB ứng dụng: seeder mặc định có tài khoản Admin mẫu.
Việc tạo/nâng quyền Admin hoặc đổi mật khẩu phải theo sự đồng ý của trưởng nhóm.

## Demo tách biệt đã được cho phép

Tạo schema `sneakershop_gd3_test` (utf8mb4) rồi dùng một terminal riêng:

```powershell
$env:APP_ENV = 'testing'
$env:DB_DATABASE = 'sneakershop_gd3_test'
$env:SESSION_COOKIE = 'sneakershop_gd3_demo_session'
# Đặt GD3_DEMO_PASSWORD riêng, tối thiểu 12 ký tự, ngoài Git.
.\scripts\php.ps1 artisan migrate
.\scripts\php.ps1 artisan db:seed --class=Gd3DemoSeeder
$env:APP_ENV = 'local'
.\scripts\php.ps1 artisan serve --host=127.0.0.1 --port=8023
```

Dùng `local` khi phục vụ HTTP để CSRF hoạt động; chỉ seed với `testing` và đúng schema riêng.
Nếu máy không chạy được artisan serve, mở server native tại đúng document root:

```powershell
Set-Location public
& ..\storage\app\php83\php.exe -S 127.0.0.1:8023 ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
```

Tài khoản demo: `admin@gd3.example.test`, `customer@gd3.example.test`, `customer2@gd3.example.test`.
Seed lại giữ nguyên tài khoản, role, mật khẩu và sản phẩm hiện có; không reset tồn kho.
Trong phiên kiểm thử trên máy này, mật khẩu được lưu ở `storage/app/gd3-credentials.json`, chỉ cục bộ.
Dữ liệu demo có sản phẩm còn hàng, hết hàng, ngừng bán và ảnh minh họa tự tạo.
Chuyển khoản là demo/hướng dẫn, không xác nhận tiền tự động.

## Kiểm thử

```powershell
.\scripts\php.ps1 vendor/phpunit/phpunit/phpunit
.\scripts\php.ps1 tests/manual/deep-mysql-audit.php
.\scripts\php.ps1 tests/manual/gd3-concurrency.php
.\scripts\php.ps1 tests/manual/deep-lock-audit.php
```

Suite mặc định force SQLite `:memory:` và có guard chặn DB khác trước RefreshDatabase.
Script manual tự kiểm tra schema `sneakershop_gd3_test`; cần XAMPP/MySQL và dữ liệu demo đã chuẩn bị.
Các ca biên MariaDB rollback; concurrency giữ đơn đã hủy/sản phẩm đã ẩn để đối chiếu, không xóa dữ liệu ứng dụng.

Smoke test trình duyệt public/auth khi server cổng 8023 đang chạy:

```powershell
playwright-cli open http://127.0.0.1:8023 --browser=chrome
playwright-cli --raw run-code --filename=tests/browser/smoke.js
```

Script chặn tài nguyên ngoài localhost, đo 320/390/768/1440px và kiểm tra menu mobile.
Kiểm thử HTTP đồng thời phải dùng hai process server độc lập; không coi SQLite hoặc một server PHP đơn luồng là bằng chứng concurrency HTTP.

## Tài liệu và kết quả

- [Bàn giao sửa lỗi và kiểm thử GĐ3](docs/GD3_FIX_VERIFICATION.md)
- [ERD](docs/ERD.md), [Mermaid](docs/ERD.mmd), [SVG](docs/ERD.svg)
- [Ma trận CRUD](docs/CRUD_MATRIX.md), [luồng CRUD](docs/CRUD.mmd)
- [Audit trước khi sửa](docs/GD3_DEEP_LOGIC_AUDIT.md)

Không cần Use Case theo xác nhận của giảng viên. Slide/báo cáo thuyết trình chỉ làm khi trưởng nhóm yêu cầu.

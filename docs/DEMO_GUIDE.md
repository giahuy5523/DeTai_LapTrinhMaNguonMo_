# Demo SneakerShop trên Windows

Chuẩn bị một bản checkout riêng, PHP 8.3+ x64, Composer 2.11, MariaDB/MySQL
và Chrome. PHP cần PDO MySQL, mbstring, OpenSSL, fileinfo và ZIP (hoặc 7-Zip
cho Composer). Dùng `scripts/php.ps1` để chọn đúng PHP, kể cả khi XAMPP có PHP cũ.

## Chuẩn bị và chạy

1. Khởi động MariaDB riêng trên loopback. Tạo schema `sneakershop_gd3_test` với
   charset utf8mb4. Dùng trình SQL chạy `SELECT DATABASE(), @@port, @@datadir;`
   sau khi chọn schema. Đối chiếu cả ba giá trị trước khi chạy migration.
2. Trong checkout demo, tạo `.env` từ `.env.example` nếu chưa có. Đặt
   `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, cổng instance demo (lần nghiệm
   thu dùng 33430), `DB_DATABASE=sneakershop_gd3_test`, thông tin kết nối riêng,
   `APP_URL=http://127.0.0.1:8024`, `SESSION_DRIVER=file` và
   `SESSION_COOKIE=sneakershop_demo_session`. Kiểm tra các biến môi trường
   `DB_*` của terminal nếu từng dùng terminal đó với dự án khác.
3. Cài dependency bằng Composer 2.11 chạy trên PHP 8.3+. Với Composer PHAR,
   dùng ` .\scripts\php.ps1 C:\ProgramData\ComposerSetup\bin\composer.phar install`.
   Tạo `APP_KEY` một lần nếu đang trống. Tạo storage link nếu chưa có.
4. Chạy các lệnh dưới đây. Chỉ tiếp tục khi mỗi lệnh thành công. Nhập mật khẩu
   demo tự chọn ít nhất 12 ký tự ở prompt ẩn và lưu riêng để trình diễn.

```powershell
.\scripts\php.ps1 artisan config:clear
.\scripts\php.ps1 artisan key:generate # Chỉ chạy nếu APP_KEY đang trống
.\scripts\php.ps1 artisan storage:link # Chỉ chạy nếu chưa có public/storage
$env:APP_ENV = 'testing'
.\scripts\php.ps1 artisan migrate
$env:GD3_DEMO_PASSWORD = [Net.NetworkCredential]::new('', (Read-Host 'Mật khẩu demo' -AsSecureString)).Password
try {
    .\scripts\php.ps1 artisan db:seed --class=Gd3DemoSeeder
} finally {
    Remove-Item Env:GD3_DEMO_PASSWORD
}
$env:APP_ENV = 'local'
.\scripts\php.ps1 artisan serve --host=127.0.0.1 --port=8024 --no-reload
```

Tài khoản Admin là `admin@gd3.example.test`. Hai Customer là
`customer@gd3.example.test` và `customer2@gd3.example.test`. Seeder giữ nguyên
mật khẩu, quyền và tồn kho của bản ghi đã có. Trước buổi demo, tạo hai sản phẩm
đang bán, còn hàng, tên bắt đầu bằng **GD3 Sneaker Demo** trong danh mục
**GD3 Sneakers**, qua form Admin. Cửa hàng cần hơn 12 kết quả còn hàng để có
trang 2. Sản phẩm **GD3 Sneaker 12** hết hàng có thể xem tại Admin, vì cửa hàng
lọc bỏ sản phẩm hết hàng.

## Kịch bản trình diễn

| # | Vai trò và trang | Thao tác → Kết quả mong đợi | Nếu gặp lỗi |
| --- | --- | --- | --- |
| 1 | Khách · `/` | Mở trang chủ → thấy ảnh và danh sách nổi bật | Kiểm tra server và storage link |
| 2 | Khách · `/san-pham` | Mở danh sách → thấy sản phẩm đang bán và còn kho | Kiểm tra kết nối DB và tồn kho |
| 3 | Khách · `/san-pham` | Tìm GD3 Sneaker rồi nhấp trang 2 → vẫn giữ từ khóa | Kiểm tra có trên 12 kết quả khả dụng |
| 4 | Customer · `/login` | Đăng nhập tài khoản demo → vào cửa hàng | Dùng mật khẩu đã đặt tại lần seed đầu |
| 5 | Customer · `/san-pham` | Thêm GD3 Sneaker 1 → badge giỏ tăng, có thông báo AJAX | Tải lại trang nếu phiên hoặc CSRF hết hạn |
| 6 | Customer · `/gio-hang` | Đổi số lượng từ 1 thành 2 → tổng tiền cập nhật | Kiểm tra tồn kho và thông báo form |
| 7 | Customer · `/checkout` | Nhập thông tin giả lập, chọn COD và đặt đơn → chuyển sang trang thành công | Mở lại checkout nếu xác nhận đã cũ |
| 8 | Customer · `/checkout/thanh-cong` | Đọc mã đơn và tổng tiền → ghi mã để đối chiếu bên Admin | Kiểm tra lịch sử trước khi gửi lại |
| 9 | Admin · `/login` | Đăng xuất Customer hoặc dùng cửa sổ riêng rồi đăng nhập Admin → mở dashboard | Kiểm tra đúng tài khoản Admin |
| 10 | Admin · `/admin/orders` | Tìm mã đơn vừa ghi → thấy đơn đang chờ | Bỏ bộ lọc trạng thái đang áp dụng |
| 11 | Admin · `/admin/orders` | Chọn đang xử lý rồi lưu → trạng thái đổi trên đúng đơn | Tải lại trang và kiểm tra trạng thái mới nhất |
| 12 | Admin · `/admin/categories`, `/admin/products` | Tạo/sửa danh mục và sản phẩm thử, tải ảnh PNG/JPEG, xóa sản phẩm rồi xóa danh mục thử | Dùng tên riêng, kiểm tra định dạng và storage link |
| 13 | Admin · `/admin/users` | Tìm Customer và mở form quyền → thấy các vai trò. Thử mở Admin bằng Customer → bị chặn | Kiểm tra phiên đăng nhập ở mỗi cửa sổ |

Để minh họa validation, thử cập nhật giỏ với số lượng lớn hơn tồn kho. Form
phải chặn hoặc báo lỗi và giỏ giữ số lượng hợp lệ. Có thể giới thiệu chuyển
khoản demo ở checkout. Hệ thống hiển thị dữ liệu minh họa và không xác nhận
tiền về hay thực hiện giao dịch ngân hàng.

## Chạy lần sau

Dùng lại instance và thư mục dữ liệu demo, chạy `migrate:status`, kiểm tra đăng
nhập, ảnh, giỏ trống và sản phẩm trình diễn còn ít nhất hai đôi. Seeder có thể
bổ sung mục thiếu, nhưng không đặt lại đơn, quyền, mật khẩu hoặc tồn kho hiện có.
Nếu cần một lần diễn tập sạch, giữ nguyên instance cũ và chuẩn bị thư mục
dữ liệu MariaDB mới trên cổng khác với cùng tên schema. Chọn cổng mới trong
checkout demo rồi chạy quy trình chuẩn bị từ đầu. Với lỗi ảnh, kiểm tra
`public/storage` trỏ tới `storage/app/public` của đúng checkout.

## Diễn tập trên Windows

Đã chạy trên PHP 8.3.33, Laravel 12.71.0, MariaDB 10.4.32 và Chrome 157.
Cả 12 migration đã chạy. Sau khi bổ sung hai sản phẩm trong schema demo,
cửa hàng có 13 sản phẩm đang bán và còn hàng. Chrome tải được ảnh trang chủ
và cả sáu nhóm kiểm thử đã đạt, gồm tìm kiếm/phân trang, đăng nhập, giỏ AJAX
và validation, checkout, quyền Admin và CRUD có thay ảnh. Đơn COD số 2 chuyển
từ đang chờ sang đang xử lý, tổng tiền 1.210.000 đồng. Đơn chuyển khoản số 1
còn đang chờ. Cả hai là dữ liệu diễn tập. Bước cập nhật giỏ và bước checkout
được xác minh bằng hai tài khoản Customer khác nhau.

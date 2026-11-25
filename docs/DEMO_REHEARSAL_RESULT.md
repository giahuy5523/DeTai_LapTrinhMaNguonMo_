# Biên Bản Diễn Tập Máy Demo & Tích Hợp Voucher (Tuấn ↔ Trọng)

- **Ngày thực hiện:** 25/11/2026
- **Nhánh:** `feature/trong-qr-reservation` (chuẩn bị review & merge vào `develop` trên PR #36)
- **Phạm vi phối hợp:**
  - **Trọng:** Hoàn thiện áp voucher ở checkout (`App\Support\VoucherService`, `POST /checkout/voucher`, `DELETE /checkout/voucher`, `POST /checkout`), cập nhật QR chuẩn ISO/IEC 18004 theo số tiền sau giảm, hiển thị thông báo hết hạn 5 phút rõ ràng + nút quay về giỏ hàng, và kiểm thử giới hạn lượt dùng voucher đồng thời.
  - **Tuấn:** Chốt contract voucher (`docs/VOUCHER_HANDOFF.md`), đồng bộ hiển thị giảm giá tại chi tiết lịch sử khách hàng (`/admin/users/{user}`), cập nhật kiểm toán toàn vẹn dữ liệu (`tests/manual/data-integrity-audit.php`), diễn tập toàn bộ 17 bước máy demo theo `docs/DEMO_GUIDE.md` và ghi nhận kết quả.

---

## 1. Môi trường kiểm chứng & giới hạn minh bạch

| Thành phần | Phiên bản / Cấu hình thực tế |
| --- | --- |
| PHP | `8.3.37` x64 (`storage/app/php83/php.exe`, portable local — không đưa vào Git) |
| Framework & Test Runner | Laravel `12.71.0`, PHPUnit `11.5.57`, Composer `2` |
| Trình duyệt kiểm chứng | Google Chrome headless (`--headless=new`), giải mã QR độc lập bằng `jsQR 1.4.0` |
| Server HTTP diễn tập | `php artisan serve --host=127.0.0.1 --port=8025 --no-reload` (`APP_ENV=local`, bật đầy đủ CSRF & cookie session tách biệt) |
| Cơ sở dữ liệu diễn tập trên máy này | SQLite `3.54.1` chế độ `WAL` (`busy_timeout=5000`) cô lập riêng cho buổi diễn tập |

> **Ghi chú trung thực về phạm vi DB:** Máy thực hiện vòng diễn tập này không cài sẵn XAMPP/MariaDB nên toàn bộ 17 bước diễn tập HTTP, quét QR và 3 vòng đua 2 tiến trình giới hạn lượt voucher chạy trên **SQLite WAL cô lập**. Các script `tests/manual/voucher-concurrency.php` (chế độ mặc định không cờ `--sqlite`) và `tests/manual/data-integrity-audit.php` đã sẵn sàng guard cho schema `sneakershop_gd3_test` để chạy bổ sung trực tiếp trên máy demo cài MariaDB 10.4.32 trước buổi thuyết trình.

---

## 2. Kết quả diễn tập đầy đủ 17 bước (`http://127.0.0.1:8025`)

| Bước | Vai trò & Màn hình | Thao tác diễn tập | Kết quả quan sát | Trạng thái |
| --- | --- | --- | --- | --- |
| 1 | Guest · `/` | Mở trang chủ | HTTP 200, hiển thị hero carousel, sản phẩm mẫu và navbar đúng vai trò khách (không lộ nút Quản trị) | **PASS** |
| 2 | Guest · `/san-pham` | Tìm `GD3 Sneaker` và sang trang 2 | Khớp đúng `13` sản phẩm khả dụng (ẩn 1 sản phẩm hết hàng + 1 ngừng bán); trang 2 giữ nguyên từ khóa tìm kiếm | **PASS** |
| 3 | Customer 1 · `/login` & AJAX | Đăng nhập `customer1@demo.test`, thêm `GD3 Sneaker 1` qua AJAX JSON | Trả JSON `{success: true, cart_count: 1, cart_total: "1.210.000"}` | **PASS** |
| 4 | Customer 1 · `/gio-hang` | Đổi số lượng `GD3 Sneaker 1` thành `2` | Máy chủ tính lại tạm tính `2.420.000 đ` | **PASS** |
| 5 | Customer 1 · `/checkout` (COD) | Đặt đơn COD thường | Điều hướng `/checkout/thanh-cong`, tạo **Đơn #1** (`2.420.000 đ`, `discount_amount = 0`), xuất hiện trong lịch sử mua của Customer 1 | **PASS** |
| 6 | Customer 2 · `/login` & `/checkout` | Đăng nhập riêng `customer2@demo.test`, đặt COD `GD3 Sneaker 2` | Tạo **Đơn #2** (`1.220.000 đ`); lịch sử Customer 2 chỉ hiện Đơn #2, không lộ Đơn #1 của Customer 1 | **PASS** |
| 7 | Admin · `/admin/vouchers` | Đăng nhập `admin@demo.test`, mở danh sách voucher | Hiển thị đủ `DEMO10` (`0 / 1`, 10%, trần `150.000 đ`) và `DEMO50K` (`0 / 20`, cố định `50.000 đ`) | **PASS** |
| 8 | Admin · `/admin/vouchers/create` | Tạo voucher phần trăm không có trần → rồi tạo có trần (`rehearse15`, 15%, trần `100.000 đ`) | Bản thiếu trần bị chặn validation (*mức giảm tối đa*); bản hợp lệ chuẩn hóa thành `REHEARSE15` và tạo thành công | **PASS** |
| 9 | Admin · `/admin/vouchers/create` | Nhập trùng mã khác hoa/thường (`ReHeArSe15`) | Bị từ chối sau chuẩn hóa (*Mã voucher này đã được sử dụng*) | **PASS** |
| 10 | Admin · `/admin/vouchers/{id}/edit` | Cập nhật hạn dùng & tăng giới hạn lượt `REHEARSE15` lên `5` | Danh sách `/admin/vouchers` cập nhật đúng `0 / 5` | **PASS** |
| 11 | Admin · `/admin/orders` & `/admin/users/{id}` | Chuyển Đơn #1 `pending → processing → completed`, mở chi tiết `customer1@demo.test` | Hiển thị đúng tổng đơn hoàn thành `2.420.000 đ`, số đơn và lịch sử mua hàng | **PASS** |
| 12 | Guest / Customer / Admin · Navbar | Kiểm tra menu theo vai trò và vị trí nút Đăng xuất | Customer không thấy `Quản trị`, Admin thấy `Quản trị`; nút **Đăng xuất** nằm trước lời chào `Xin chào, ...` ở cả Customer và Admin | **PASS** |
| 13 | Customer 1 · `/checkout` (QR + `DEMO10`) | Áp mã chữ thường ` demo10 ` cho `2 × GD3 Sneaker 3` (`2.460.000 đ`), quét QR và đặt hàng | Chuẩn hóa `DEMO10`, giảm chạm trần `-150.000 đ` → `2.310.000 đ`; quét `<svg class="qr-demo-svg">` bằng `jsQR` giải mã chính xác payload `2.310.000 VND` + `Voucher: DEMO10` (không có TK ngân hàng thật); tạo **Đơn #3**; Admin đổi trạng thái `DEMO10` sang **Hết lượt (`1 / 1`)** | **PASS** |
| 14 | Customer 2 & Admin · Hết lượt & Xóa voucher | Customer 2 thử `DEMO10` → chuyển sang `rehearse15` đặt **Đơn #4** (`1.240.000 đ - 100.000 đ = 1.140.000 đ`) → Admin xóa `REHEARSE15` | `DEMO10` bị chặn (*đã hết lượt*); sau khi xóa `REHEARSE15`, Đơn #4 đặt `voucher_id = null` nhưng vẫn giữ nguyên snapshot `voucher_code = REHEARSE15`, `discount_amount = 100.000 đ`, `total_amount = 1.140.000 đ` | **PASS** |
| 15 | Customer 1 & Customer 2 · Sản phẩm cuối & Hết hạn 5 phút | Tranh mua sản phẩm cuối `GD3 Sneaker 15` (`quantity = 1`): C1 giữ 5 phút → C2 bị chặn → hết hạn 5 phút → C1 gửi đơn trễ bị từ chối → C2 đặt thành công với `DEMO50K` | C2 bị chặn khi C1 đang giữ; C1 gửi trễ bị chuyển về `/gio-hang` báo hết hạn; C2 nhận lượt giữ mới sau hoàn kho, áp `DEMO50K` (`-50.000 đ`) tạo **Đơn #5** (`1.300.000 đ`), tồn kho về `0` | **PASS** |
| 16 | Đồng thời 2 tiến trình · Giới hạn 1 lượt voucher | Chạy `tests/manual/voucher-concurrency.php --sqlite` (3 vòng × 2 worker PHP đồng bộ barrier trên 2 sản phẩm tách biệt, cùng tranh 1 voucher `usage_limit = 1`) | **3/3 vòng** trả đúng `["accepted", "rejected"]`, `used_count = 1`, `orders_created = 1`, `discount_amount = 100000.00`, `total_amount = 900000.00`, tổng kho sau đặt = `9`, sau hủy hoàn kho về `10` nhưng `used_count` giữ nguyên `1` | **PASS** |
| 17 | Kiểm toán SQL toàn vẹn dữ liệu | Chạy 9 truy vấn toàn vẹn (gồm `subtotal_amount - discount_amount = total_amount` và `used_count <= usage_limit`) trên DB sau diễn tập | **0 vi phạm** trên toàn bộ 9 chỉ số (5 đơn hàng, 2 voucher còn lại) | **PASS** |

---

## 3. Ảnh chụp trạng thái DB sau diễn tập

| Mã đơn | Khách hàng | Mã voucher | `voucher_id` | Tạm tính | Giảm giá | Tổng phải trả | Phương thức | Trạng thái |
| --- | --- | --- | --- | ---: | ---: | ---: | --- | --- |
| #1 | Customer 1 | — | `null` | `2.420.000 đ` | `0 đ` | `2.420.000 đ` | COD | `completed` |
| #2 | Customer 2 | — | `null` | `1.220.000 đ` | `0 đ` | `1.220.000 đ` | COD | `pending` |
| #3 | Customer 1 | `DEMO10` | `1` | `2.460.000 đ` | `150.000 đ` | `2.310.000 đ` | QR mô phỏng | `pending` |
| #4 | Customer 2 | `REHEARSE15` *(đã xóa)* | `null` | `1.240.000 đ` | `100.000 đ` | `1.140.000 đ` | COD | `pending` |
| #5 | Customer 2 | `DEMO50K` | `2` | `1.350.000 đ` | `50.000 đ` | `1.300.000 đ` | QR mô phỏng | `pending` |

---

## 4. Kết quả kiểm thử tự động & kiểm tra CI

| Hạng mục | Lệnh chạy | Kết quả |
| --- | --- | --- |
| Toàn bộ bộ test PHPUnit | `php vendor/phpunit/phpunit/phpunit` | **189 tests, 4.366 assertions — PASS** (gồm `QrCodeEncoderTest`, `CheckoutVoucherTest` với ca 2 tiến trình đồng thời, `SimulatedQrCheckoutTest`, `ProductImageDisplayTest`, `VoucherTest`, `StockReservationTest`) |
| Kiểm thử giới hạn lượt dùng voucher đồng thời | `php tests/manual/voucher-concurrency.php --sqlite` | **PASS** (3/3 vòng `["accepted", "rejected"]`) |
| Biên dịch toàn bộ Blade views | `php artisan view:cache` | **PASS** |
| Xác thực Composer & nền tảng | `composer validate --no-check-publish` & `composer check-platform-reqs` | **PASS** |
| Kiểm tra lỗ hổng thư viện | `composer audit --no-interaction` | **PASS** (`No security vulnerability advisories found.`) |
| Kiểm tra khoảng trắng/diff | `git diff --check` | **PASS** |

---

## 5. Lệnh chạy lại trên máy demo có MariaDB (`sneakershop_gd3_test`)

Khi mở máy trình bày có MariaDB cổng `33430` theo `docs/DEMO_GUIDE.md`, chạy bổ sung 3 lệnh xác minh MariaDB sau:

```powershell
.\scripts\php.ps1 tests/manual/voucher-concurrency.php
.\scripts\php.ps1 tests/manual/reservation-concurrency.php
.\scripts\php.ps1 tests/manual/data-integrity-audit.php
```

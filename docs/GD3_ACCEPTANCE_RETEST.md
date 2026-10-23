# Gia Huy — Kiểm thử nghiệm thu độc lập 23/10/2026

> **Hiện hành:** R01–R08 đã đóng; **131 tests / 2.970 assertions đạt**.
> [Báo cáo QA tổng hợp](GD3_FINAL_QA.md). Nội dung bên dưới là lịch sử các vòng trước.

> **Mới nhất:** R06 đã sửa; R01–R06 đã đóng. Suite **103 tests / 748 assertions đạt**.
> [Kiểm chứng phone trên MariaDB](GD3_BOUNDARY_RETEST.md).

> **Mới nhất:** R05 đã sửa và kiểm chứng trên hai worker MariaDB.
> Suite hiện đạt **96 tests / 700 assertions**. Xem [bản sửa R05](GD3_TRANSACTION_RETEST.md).
> R01–R05 đã đóng; các số liệu dưới đây là lịch sử các lượt kiểm thử trước.

**Cập nhật sau sửa: R01–R04 đã được xử lý và kiểm chứng.** Suite mặc định hiện đạt
**83 tests / 575 assertions**. Hai ca HTTP thật trước đây trả 500 nay lần lượt trả
422 validation và 200 hiển thị lại form. Chưa commit/push/merge hoặc triển khai DB ứng dụng.

Các mục nghiệm thu bên dưới giữ bằng chứng ban đầu trước sửa; xem phần cuối để biết
bản sửa và kết quả hiện tại. Không dùng kết luận lỗi mở trước sửa làm trạng thái hiện hành.

## Môi trường và phạm vi

- Nhánh local `codex/gia-huy-gd3`; fetch lại `origin/develop`, HEAD không lệch
  develop (0/0), nền `804fdc629318b35fd17f807d27bb79cc76af2101`.
- PHP 8.3.36 x64, Laravel 12.70.1; SQLite `:memory:` cho PHPUnit;
  MariaDB 10.4.32, schema `sneakershop_gd3_test` cho integration/concurrency/Chrome.
- Đọc lại phân công gốc: GĐ1 nền tảng/Category/Product/upload/layout;
  GĐ2 Home/Admin/dashboard/ERD/CRUD; GĐ3 tích hợp/sửa lỗi/responsive/demo/bản cuối.
- Không ghi DB `ban_giay_the_thao`, không commit/push/merge. Các fixture mới chỉ
  ở DB kiểm thử đã được đồng ý. Không sửa mã nghiệp vụ trong lượt nghiệm thu này.

## Lỗi phát hiện trước sửa — hiện đã đóng R01–R04

| ID | Mức | Lỗi và bằng chứng | Phạm vi |
| --- | --- | --- | --- |
| R01 | P1 | Đăng ký cho phép mật khẩu dài hơn 72 byte với bcrypt. Đăng ký bằng 72 ký tự `a` + `original`, sau đó đăng nhập bằng cùng tiền tố + `different` vẫn thành công. Hai mật khẩu khác nhau được chấp nhận cho cùng tài khoản. Hai test mới thất bại. | Auth của Tuấn; chặn nghiệm thu tích hợp GĐ3 |
| R02 | P2 | Mật khẩu chứa NUL (`secret\0password`) qua validation rồi lỗi khi hash, trả HTTP 500 thay vì lỗi đầu vào. PHPUnit và HTTP thật đều tái hiện. | Auth; tích hợp GĐ3 |
| R03 | P2 | Submit trường scalar dạng array bị validation từ chối nhưng `withInput` giữ array; GET form tiếp theo gọi Blade `old(...)` → `htmlspecialchars(array)` → HTTP 500. Ba test tái hiện ở Category create, Product create, Register. HTTP thật: `email[]=invalid` → POST 302, GET Register 500. | CRUD trực tiếp của Gia Huy và Auth |
| R04 | P3 | Form sửa Category còn `required>>`, tạo ký tự `>` thừa sau ô tên. GET edit Category trả HTML chứa chuỗi này; test markup thất bại. | Giao diện CRUD của Gia Huy |

Vị trí cần xử lý:

- R01/R02: `app/Http/Controllers/AuthController.php`, validation đăng ký và đăng nhập;
  `config/hashing.php` hiện dùng bcrypt. Giới hạn cần tính **byte**, không chỉ số ký tự;
  đồng thời không tự đổi mật khẩu/hash tài khoản hiện có.
- R03: `resources/views/admin/categories/create.blade.php`,
  `resources/views/admin/products/create.blade.php`, `resources/views/auth/register.blade.php`.
  Các form khác cũng dùng `old()` trực tiếp, cần rà cùng mẫu; chưa gán trạng thái lỗi
  cho những trang chưa tái hiện riêng.
- R04: `resources/views/admin/categories/edit.blade.php:19`.

## Kết quả thực thi trong lượt này

| Bộ kiểm tra | Kết quả |
| --- | --- |
| Suite mặc định, vendor chính | **69 tests / 481 assertions: đạt** |
| Suite mặc định, vendor cài sạch | **69 tests / 481 assertions: đạt** |
| Bộ nghiệm thu biên mới | **10 tests / 47 assertions: 3 đạt, 7 thất bại**, gom thành R01–R04 |
| Chống tự gán role Admin khi đăng ký | Đạt |
| Escape tên Product chứa script trên Home/Store | Đạt |
| Customer bị chặn các GET Admin và POST tạo Category/Product | Đạt; redirect `/` đúng thiết kế, không ghi DB |
| MariaDB deep audit | 6 ca đạt: tiền cực đại, overflow, mô tả quá dài, hoàn kho legacy, khoảng trống hoàn hàng |
| Hai người mua sản phẩm cuối/hủy cùng đơn | 3 vòng đạt; mỗi vòng một mua được, một bị từ chối; hoàn đúng một lần |
| Khóa sản phẩm theo thứ tự ngược | Cả hai checkout thành công, tồn kho đúng |
| Admin và Customer hủy cùng đơn | Chỉ một thao tác thành công; tồn kho hoàn đúng một lần |
| Hai worker dùng cùng checkout token, không dùng chung session lock | Một đơn duy nhất, trừ kho một lần |
| Chrome, luồng có đăng nhập | Tạo Category; tạo/upload/sửa Product; mua hàng; pending → processing → cancelled; stock 5 → 3 → 5 |
| Responsive trang công khai | Home/Store/Login/Register ở 320/390/768/1440: 16 lượt đạt, không overflow/JS error; assets offline/menu mobile đạt |
| Responsive luồng có đăng nhập | Home/Products/Dashboard/Cart/Checkout/Success/Orders ở 390/1440: 14 lượt đạt |
| Composer validate / audit | Hợp lệ; không có advisory/abandoned trong kết quả audit hiện tại |
| Autoload/package discovery | Chạy thành công với quyền truy cập phù hợp; lỗi đầu tiên do sandbox, không kết luận thiếu file |
| `git diff --check` | Đạt |

Các luồng đã được suite mặc định bao phủ còn gồm: giá thay đổi, stale form Product,
quote giả/cũ, đơn thuộc người khác, sản phẩm bị xóa/ngừng bán, rollback checkout,
ảnh dùng chung/thay ảnh thất bại, giới hạn tiền/stock/UTF-8, trạng thái đơn terminal.
Đây là kết quả theo các ca đã thực thi, không phải cam kết không còn lỗi khác.

## Đánh giá theo nhiệm vụ

- **GĐ1:** chức năng nền tảng/CRUD/upload đã có và luồng hợp lệ đạt; còn R03/R04,
  nên chưa ký xác nhận chất lượng cuối cho CRUD.
- **GĐ2:** Home/Admin/dashboard và tài liệu ERD/CRUD đã có, các ca hiện chạy đạt.
  Schema test có migration checkout_token; DB ứng dụng chưa áp dụng migration này.
- **GĐ3:** có test/demo/responsive và bằng chứng sửa lỗi cũ; **chưa đạt bản cuối**
  vì R01–R04 còn mở, thay đổi còn local, chưa qua PR/develop và nghiệm thu triển khai.
- Slide/Word/Use Case không tính thiếu trong lượt này theo yêu cầu gốc.

## Chạy lại và bằng chứng

```powershell
.\scripts\php.ps1 vendor/phpunit/phpunit/phpunit
.\scripts\php.ps1 vendor/phpunit/phpunit/phpunit --filter AcceptanceRetestTest
.\scripts\php.ps1 tests/manual/deep-mysql-audit.php
.\scripts\php.ps1 tests/manual/gd3-concurrency.php
.\scripts\php.ps1 tests/manual/deep-lock-audit.php
```

Ca biên ban đầu được chạy riêng để ghi nhận lỗi. Sau sửa, đã chuyển vào
`tests/Feature/AcceptanceRetestTest.php` để chạy trong suite mặc định và mở rộng lên 14 ca.

- Test hồi quy: `tests/Feature/AcceptanceRetestTest.php`.
- HTTP thật: `tests/browser/acceptance-invalid-input.js` (chỉ server demo port 8023).
- XML/log cục bộ đã ignore: `storage/app/gd3-reacceptance.xml`,
  `storage/app/gd3-acceptance-probes.xml`, `storage/app/gd3-acceptance-probes-output.txt`.
- Luồng trình duyệt vừa tạo Product 89 / Order 49 trong DB demo; đơn đã cancelled,
  tồn kho đã hoàn về 5. Giữ fixture để đối chiếu, không xóa dữ liệu có sẵn.
- DB ứng dụng được đọc lại: users=1, categories=2, products=0, orders=0, order_items=0.

Giới hạn: chưa thử MySQL 8, thiết bị iOS thật, tải lớn kéo dài hoặc môi trường triển khai.
Không tự chạy migration mới hoặc tạo Admin trên DB ứng dụng để hoàn tất nghiệm thu.

## Bản sửa theo yêu cầu tiếp theo của Gia Huy

| ID | Xử lý | Kiểm chứng sau sửa |
| --- | --- | --- |
| R01 | Rule `BcryptPassword` dùng `strlen` kiểm tra 72 byte ở cả register/login, không đổi thuật toán hoặc hash tài khoản có sẵn. | ASCII 72 byte và UTF-8 72 byte đăng ký/đăng nhập được; 75 byte UTF-8 bị chặn. Tài khoản legacy mô phỏng vẫn bị chặn khi gửi password >72 byte khác hậu tố; hash giữ nguyên. |
| R02 | Cùng rule chặn NUL trước khi hash hoặc xác thực. | Register/login trả validation; HTTP thật register trả 422 thay vì 500. |
| R03 | Mọi `old()` ở form Blade dùng `FormInput::old`, chỉ đưa scalar/null ra HTML, array thành chuỗi rỗng; dữ liệu hợp lệ được giữ lại. | Category/Product create/edit, Login/Register, Checkout không lỗi với old input dạng array. Token `_version` sai không được thay bằng token mới. HTTP thật email array → POST 302 → GET 200. |
| R04 | Xóa dấu `>` thừa trong Category edit. | Test markup đạt. |

- 14 test liên quan: **94 assertions, đạt**; đã ở suite mặc định.
- Toàn bộ suite: **83 tests / 575 assertions, đạt**, không lỗi/failure.
- Pint các file PHP sửa: đạt.
- XML sau sửa: `storage/app/gd3-acceptance-fixed.xml` (ignored).
- Form Register có hướng dẫn giới hạn byte; server kiểm tra UTF-8 theo byte.
- Phạm vi lượt sửa: validation mật khẩu, render dữ liệu form cũ, markup Category và test/docs;
  không đổi dữ liệu, role, mật khẩu hay schema database ứng dụng.

Giới hạn bcrypt vẫn áp dụng cho dữ liệu legacy: phần mật khẩu sau byte 72 đã bị
hash cũ bỏ qua thì không thể khôi phục. Bản sửa chặn đầu vào >72 byte; không thể
suy ra mật khẩu gốc hoặc âm thầm sửa tài khoản cũ. Nếu có tài khoản đã dùng mật khẩu
quá giới hạn, chủ tài khoản cần chủ động đặt lại mật khẩu theo quy trình riêng.

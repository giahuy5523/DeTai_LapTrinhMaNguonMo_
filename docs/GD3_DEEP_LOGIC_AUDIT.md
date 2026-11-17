# Kiểm tra chuyên sâu SneakerShop — Gia Huy

> **Lịch sử trước sửa. Cập nhật 23/10/2026:** các vấn đề được ghi nhận dưới đây
> đã được sửa và đưa vào kiểm thử hồi quy. Xem [bản kiểm chứng cuối](GD3_FIX_VERIFICATION.md).
> Các số dòng/kết quả đỏ ở tài liệu này mô tả code trước sửa, không phải trạng thái hiện tại.

Ngày kiểm chứng: **22/10/2026**, Asia/Saigon.

**Kết luận: chưa đủ điều kiện xác nhận Gia Huy hoàn thành toàn bộ nhiệm vụ/bản cuối.**
GĐ1–GĐ2 đã có đủ nhóm chức năng được giao và đã tích hợp, nhưng còn lỗi trong CRUD,
ảnh và tích hợp tồn kho. GĐ3 đã kiểm thử nhiều luồng, song kiểm tra sâu lần này tìm
được lỗi thật. Kết luận “hoàn thành GĐ3” ở lượt trước chỉ đúng với các ca đã chạy,
không đủ làm kết luận nghiệm thu tổng thể.

Đợt này chỉ bổ sung bằng chứng/test và cập nhật tài liệu; **chưa sửa các lỗi dưới đây**.
Không sửa code nghiệp vụ của thành viên khác, không commit/push/merge.

## 1. Trạng thái đã kiểm chứng live

- Đúng repo local `C:\Users\Lenovo\DeTai_LapTrinhMaNguonMo_`, origin trỏ đúng GitHub của nhóm.
- Nhánh local `codex/gia-huy-gd3`; HEAD và `origin/develop` sau fetch đều là
  `804fdc629318b35fd17f807d27bb79cc76af2101`.
- GĐ3 là thay đổi chưa commit trong working tree. `git ls-remote --heads origin codex/gia-huy-gd3`
  không có kết quả: nhánh này chưa được push.
- [PR #13](https://github.com/giahuy5523/DeTai_LapTrinhMaNguonMo_/pull/13) và
  [PR #18](https://github.com/giahuy5523/DeTai_LapTrinhMaNguonMo_/pull/18) đã merge vào develop.
- [PR #17](https://github.com/giahuy5523/DeTai_LapTrinhMaNguonMo_/pull/17) vẫn mở, nội dung hai assertion đã nằm trong #18.
  [PR #10](https://github.com/giahuy5523/DeTai_LapTrinhMaNguonMo_/pull/10) vẫn mở và trỏ main.
  Không thao tác hai PR này trong đợt kiểm tra.

## 2. Đối chiếu nhiệm vụ Gia Huy

| Giai đoạn / việc được giao | Đã có | Đánh giá hiện tại |
| --- | --- | --- |
| GĐ1 — nền Laravel/MySQL, migration, seeder | Cấu trúc Laravel 10, 9 bảng, migration đầy đủ | Có; cài đặt mới bị vướng PHP/lock file, xem mục 5 |
| GĐ1 — Eloquent và CRUD 2 bảng quan hệ | Category/Product, Product::category, FK category_id | Đủ chức năng cơ bản; A01/A08/A10 cần sửa |
| GĐ1 — master layout/Blade/Bootstrap | Layout khách/Admin, menu theo role, CSRF form | Có; còn flash lỗi trùng và giá lẻ hiển thị thiếu chính xác |
| GĐ1 — upload/thay/xóa ảnh | Upload PNG; rollback lỗi lưu ảnh/DB được test | Có; xóa cascade chưa dọn ảnh, seeder dùng ảnh chung có lỗi |
| GĐ2 — Home | Active, tối đa 8 sản phẩm, rỗng/hết hàng, route giỏ thật | Có; giá lẻ hiển thị bị làm tròn |
| GĐ2 — Admin/dashboard | Thống kê Product/Category/Order, pending, tổng completed, 5 đơn mới | Có; test thống kê và phân quyền đạt |
| GĐ2 — ERD và CRUD matrix/flow | `ERD.md/.mmd/.svg`, `CRUD_MATRIX.md`, `CRUD.mmd` | Đối chiếu live đủ 69 cột/9 bảng/4 FK; không thiếu Use Case |
| GĐ3 — kiểm thử/sửa lỗi tích hợp | Suite thường, Chrome, MariaDB, concurrency | Chưa nghiệm thu: phát hiện các lỗi logic bên dưới chưa sửa |
| GĐ3 — responsive | Ca bình thường 1440/390; thêm Home/Store/Auth ở 320/390/768/1440, tên dài 255 ký tự | Không tái hiện tràn ngang trong các ca này; không suy rộng thành mọi thiết bị |
| GĐ3 — dữ liệu demo và bản cuối | DB test riêng, tài khoản và ảnh demo; seeder có guard | Demo có A09; GĐ3 chưa commit/push/PR, chưa phải bản cuối tích hợp |

Slide/báo cáo thuyết trình chưa được yêu cầu, không tính thiếu.
Thanh toán online, API ngoài, tìm kiếm public, quản lý User và Use Case không bị tự thêm vào tiêu chí.

## 3. Các lỗi đã tái hiện

P1: cần ưu tiên trước nghiệm thu vì sai dữ liệu/đơn hàng/giá xác nhận.
P2: lỗi chức năng, tính toàn vẹn hoặc xử lý đầu vào; một số chỉ xuất hiện ở biên dữ liệu.
P3: lỗi nhỏ/hành vi UI, vẫn ghi nhận theo yêu cầu kiểm tra kỹ.
“Phụ trách” là vùng code cần phối hợp theo phân công, không quy lỗi cá nhân từ lịch sử commit.

| ID | Mức | Tái hiện / kết quả thực tế | Vùng code và phụ trách |
| --- | --- | --- | --- |
| A01 | P1 | Admin mở form stock=5; Customer mua 2 làm stock=3; Admin lưu form cũ chỉ định sửa mô tả nhưng gửi quantity=5: stock trở lại **5**. Có thể bán vượt hàng thực tế. | `Admin/ProductController.php:140`, form edit; Gia Huy |
| A02 | P1 | Hai HTTP POST checkout cùng cookie/session qua 2 worker, giỏ mua 2, stock=10: **cả hai 302 success, tạo 2 đơn #10/#11, stock=6**. Chưa chống gửi trùng. | `CheckoutController.php:46`, `routes/web.php`; Trọng + tích hợp GĐ3 |
| A03 | P1 | Thêm 2 giày giá 100.000 vào giỏ, DB đổi 150.000; GET checkout vẫn hiển thị tổng **200.000**, POST checkout dùng DB và ghi **300.000**. Giá lưu đúng DB nhưng không khớp giá khách vừa thấy/xác nhận. | `CheckoutController.php:15–26,84`; Trọng + tích hợp GĐ3 |
| A04 | P2 | Hai giỏ A→B / B→A, cả hai còn đủ stock; ép hai tiến trình giữ khóa đầu tiên rồi đi tiếp: một checkout thành công, một ném **QueryException 1213 deadlock**. Không retry hoặc chuyển thành lỗi form. | `CheckoutController.php:48–67`; Trọng |
| A05 | P2 | Product giá hợp lệ **9999999999999.99**, mua chỉ 1: ép tiền sang float làm mất độ chính xác khi bind, checkout lỗi **22003/1264**. | `CheckoutController.php:84,101`, giới hạn giá Product; Gia Huy + Trọng |
| A06 | P2 | Product 6000000000000.00, quantity=2, stock đủ: tổng 12000000000000 vượt decimal(15,2), lỗi **22003/1264** thay vì validation. Transaction rollback, không ghi đơn dở. | `CheckoutController.php:84–101`; Trọng + schema nền |
| A07 | P2 | Mua 1; Admin sửa stock thành **2147483647** (form chấp nhận); Customer hủy đơn: hoàn kho +1 vượt signed int, lỗi **22003/1264**. | `CartController.php:201`, `Admin/OrderController.php:98`, Product validation; Gia Huy + Trọng/Vĩ |
| A08 | P2 | Description 70.000 byte ASCII qua validation `nullable|string`, nhưng cột TEXT chỉ chứa tối đa 65.535 byte: thêm Category hoặc Product đều **22001/1406**. Input Unicode có thể vượt giới hạn byte với ít ký tự hơn. | `Admin/CategoryController.php:30,64`, `Admin/ProductController.php:44,107`; Gia Huy |
| A09 | P2 | Hai sản phẩm cùng `products/gd3-demo.svg`; xóa một sản phẩm thành công nhưng file chung bị xóa, sản phẩm còn lại mất ảnh. Thay ảnh một sản phẩm cũng có cùng nguy cơ ở bước dọn ảnh cũ. | `Gd3DemoSeeder.php:50`, `Admin/ProductController.php:151–164`; phần GĐ3 mới thêm |
| A10 | P2 | Category có Product và ảnh; DELETE Category cascade xóa Product trong DB nhưng file ảnh **vẫn còn**. Không đi qua `ProductController::destroy`. | `Admin/CategoryController.php:89–92`; Gia Huy |
| A11 | P2 | POST thêm giỏ với `quantity=''` được ConvertEmptyStringsToNull, vượt qua `nullable`; `(int)null=0`, session có item **quantity=0** và báo thêm thành công. | `CartController.php:62–68,94`; Trọng |
| A12 | P2 | GET Admin products với `keyword[]=bad` trả **500**, chưa validate scalar/string trước query và render. | `Admin/ProductController.php:17–23`, view input keyword; Vĩ + Gia Huy |
| A13 | P3 | Tìm keyword **"0"** không lọc, vì `when($keyword)` coi chuỗi "0" là false; trả cả tên không chứa số 0. | `Admin/ProductController.php:21`; Vĩ + Gia Huy |
| A14 | P2 | Checkout với phone **"abc"** vẫn tạo đơn; mới kiểm tra required/string/max, chưa kiểm tra định dạng số điện thoại. | `CheckoutController.php:35`; Trọng |
| A15 | P3 | Form chấp nhận và DB lưu **1250.50**, Home/Admin/Cart lại `number_format(...,0)` thành **1.251**. Hai sản phẩm: tổng thật **2.501**, không bằng 1.251×2. Cần thống nhất tiền nguyên VND hoặc hiển thị chính xác phần lẻ. | `home.blade.php:37` và các view tiền; Gia Huy + tích hợp |
| A16 | P3 | Đánh dấu Ghi nhớ đăng nhập, login thành công nhưng không có recaller cookie: `Auth::attempt` không nhận tham số remember. | `AuthController.php:22`, login checkbox; Tuấn |
| A17 | P3 | Session có một error, Cart và Checkout đều render chuỗi lỗi **2 lần** do layout + view con cùng hiển thị. Success flash đã được sửa trước đó nhưng error chưa sửa. | `layouts/app.blade.php:49`, `cart/index.blade.php:10`, `checkout/index.blade.php:10`; tích hợp layout |
| A18 | P3 | Hộp xác nhận hủy đơn còn câu **“Mày có chắc muốn hủy đơn hàng này không?”**, không phù hợp nội dung giao diện khách hàng. | `cart/index.blade.php:242`; Trọng / rà soát GĐ3 |

**A09 là lỗi phát sinh ở dữ liệu demo dùng ảnh chung tôi thêm trong lượt GĐ3 trước.**
Không được coi suite 31 test xanh là bằng chứng seeder tương thích đầy đủ với thao tác xóa/thay ảnh.

Quan sát bổ sung: `status[]=pending` tại filter Orders vẫn trả 200 thay vì báo đầu vào sai;
nên quy định và validate kiểu/giá trị filter. Đây được tách khỏi A12: **không kết luận nó gây 500**.

## 4. Bằng chứng và chạy lại

### Suite thường

`php vendor/phpunit/phpunit/phpunit`: **31 tests / 291 assertions, đạt**.
Đây là bộ test mặc định Unit/Feature, SQLite `:memory:` đã được force trong phpunit.xml.

### Suite kiểm tra sâu riêng

```powershell
php vendor/phpunit/phpunit/phpunit tests/manual/DeepLogicAuditTest.php
```

**17 tests / 58 assertions: 13 thất bại, 4 đạt** ở code hiện tại.
13 failure không đồng nghĩa 13 nguyên nhân độc lập: flash trùng được kiểm ở hai trang;
filter status sai kiểu là một quan sát validation. Các lỗi MariaDB/concurrency nằm ở bộ kiểm riêng.
Suite cố ý đặt trong `tests/manual` để lưu cách tái hiện lỗi chưa sửa, không trộn vào
suite mặc định rồi quảng cáo tất cả đã xanh. Khi sửa phải đưa các ca hồi quy phù hợp vào Feature.
JUnit cục bộ: `storage/app/gd3-deep-audit.xml` (Git ignore).

4 ca đạt kiểm tra: chống tự đăng ký role admin; Admin/Customer bị chặn route chéo;
rollback khi sản phẩm thứ hai thiếu stock; hủy sau khi Product đã xóa vẫn giữ lịch sử item.

### MariaDB và concurrency

```powershell
php tests/manual/deep-mysql-audit.php
php tests/manual/deep-lock-audit.php
```

Cần MySQL XAMPP đang chạy, DB `sneakershop_gd3_test` đã migrate/seed và các tài khoản
`admin@gd3.example.test`, `customer@gd3.example.test`, `customer2@gd3.example.test`.
Script tự force và kiểm tra database; không chạy fresh/wipe.

- `deep-mysql-audit.php`: 5 tình huống A05/A06/A07/A08 (Category/Product), tất cả tái hiện
  QueryException; mỗi ca nằm trong transaction và rollback. Script còn đọc schema/count DB ứng dụng.
- `deep-lock-audit.php`: 2 process / 2 DB connection; barrier sau khóa sản phẩm đầu tiên
  để tái hiện thứ tự khóa đối nghịch; 1 success + 1 exception 1213. Đây là lịch chạy có
  chủ đích để chứng minh lỗi có thể xảy ra, không phải đo tần suất production.
- Cùng script thử Admin và Customer cùng hủy đơn vừa tạo: 1 success + 1 validation rejection,
  đơn cancelled và **stock của cả hai sản phẩm trở về 5**, đạt. Sản phẩm kiểm thử được ẩn;
  bản ghi giữ để đối chiếu, không xóa dữ liệu demo.
- A02 được thử HTTP thật tại 127.0.0.1:8013/8014 bằng Playwright, cùng cookie, form hợp lệ,
  token CSRF, hai POST chạy Promise.all. Row lock của sản phẩm test được giữ tạm để tăng
  khả năng hai request cùng nạp giỏ trước khi xử lý. Sau xác minh đã hủy cả hai đơn thử,
  phục hồi stock và ẩn sản phẩm. Không cần đưa mật khẩu/session vào repo để tái hiện.
- MariaDB **10.4.32**, không tuyên bố đã chạy MySQL 8.

### Các kiểm tra không phát hiện lỗi trong phạm vi đã chạy

- PHP lint **81 file**, Blade compile/cache đạt, composer.json validate đạt.
- ERD Mermaid liệt kê đúng **69/69 cột**, không thiếu/thừa so với schema ứng dụng live.
  9 bảng, 4 FK và CASCADE/SET NULL khớp tài liệu; model relations hiện có khớp mô tả.
- Phân trang 25 sản phẩm cùng timestamp, 5 trang×5: **25 ID khác nhau**, không tái hiện lặp/mất
  trong lần chạy. Dùng thêm id để phá hòa sẽ ổn định hơn, nhưng không ghi thành lỗi đã chứng minh.
- Home/Store với tên sản phẩm và danh mục dài 255 ký tự, và Login/Register ở 320/390/768/1440:
  `scrollWidth == viewport`, không tái hiện tràn ngang. Ảnh bổ sung `gd3-audit-long-*.png`.
- Dữ liệu ứng dụng chỉ đọc, trước/sau: users=1, categories=2, products=0, orders=0, order_items=0.
  Không tạo Admin, sửa mật khẩu/quyền, seed hay migrate ứng dụng.

## 5. Môi trường và giới hạn bàn giao

`composer check-platform-reqs --lock` xác nhận **PHP 8.1.25 hiện tại không đáp ứng lock file**.
Một số dependency yêu cầu PHP 8.2; Pint trong lock yêu cầu PHP 8.3. Composer install đã thất bại
ở lượt trước; chạy được vendor sẵn có không chứng minh máy mới có thể dựng cùng runtime.
`composer.json` vẫn cho phép PHP ^8.1 và tắt platform-check. Trước bản cuối cần chọn runtime
PHP phù hợp và thực sự thử cài từ lock; không tự update dependency hoặc đổi PHP/XAMPP trong đợt review.

README vẫn là mặc định Laravel, chưa phải hướng dẫn dựng/chạy SneakerShop hoàn chỉnh.
Checklist GĐ3 có hướng dẫn DB demo nhưng chưa thay thế việc kiểm chứng cài mới.
Đây là công việc bàn giao, không tự biến thành yêu cầu chức năng mới của giảng viên.

Admin chưa có ô bật/tắt is_active; code có hỗ trợ trạng thái ở Home/Checkout nhưng phải đổi qua DB/code.
Ghi nhận giới hạn vận hành, không tự tính thành yêu cầu CRUD bị thiếu của giảng viên.

## 6. Thứ tự xử lý đề xuất

1. Gia Huy xử lý A01 (chống lưu form tồn kho cũ), A09/A10 (vòng đời ảnh và ảnh chung),
   A08 và A15. Đây là các việc trực tiếp thuộc phần được giao và demo mới thêm.
2. Phối hợp Trọng/Vĩ xử lý A02/A03/A04, giới hạn tiền/stock A05–A07, giỏ và validation.
   Khóa theo thứ tự product ID thống nhất; xử lý deadlock có retry hữu hạn; chống checkout trùng;
   cập nhật/đòi xác nhận lại khi giá thay đổi, không âm thầm thay tổng khách vừa xác nhận.
3. Sửa các điểm nhỏ A12–A18 ở đúng phần phụ trách, thống nhất định dạng tiền và nội dung UI.
4. Chạy lại các ca fail trên code sửa, suite thường, HTTP/MariaDB và cài mới đúng runtime;
   cập nhật checklist theo bằng chứng mới rồi mới commit/push/PR develop.

Đạt các ca trên cũng không phải chứng minh tuyệt đối không còn mọi lỗi. Tuy nhiên với những
lỗi đã tái hiện hiện tại, **không được nghiệm thu “đã hoàn thành tất cả nhiệm vụ/bản final”**.

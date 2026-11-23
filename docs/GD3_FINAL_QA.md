# GĐ3 — Trạng thái kiểm chứng hiện hành

Ngày 23/10/2026 (Asia/Saigon). Nhánh local `codex/gia-huy-gd3`.
Nền `origin/develop` đã fetch: `804fdc629318b35fd17f807d27bb79cc76af2101` (HEAD lệch 0/0).

**Đã sửa R07, sửa thêm lỗi responsive tên tài khoản dài (R08), và hoàn thành vòng
kiểm thử tổng hợp. Không còn ca tái hiện đã ghi nhận nào đang thất bại trong vòng này.**
Các báo cáo audit cũ giữ bằng chứng trước sửa; dùng tài liệu này để đọc trạng thái hiện tại.
Đã merge [PR #19](https://github.com/giahuy5523/DeTai_LapTrinhMaNguonMo_/pull/19) vào develop
và triển khai migration ứng dụng sau khi Gia Huy đồng ý. [Biên bản và backup](GD3_HANDOFF_STATUS.md).

## Thay đổi trong vòng sửa tổng hợp

### R07 — Bán vượt kho qua ID alias: đã đóng

- `CartSnapshot::productId` chỉ nhận ID nguyên dương dạng chữ số trong giới hạn PHP int,
  chuẩn hóa `123`/`0123`/`000123` thành cùng một ID. Không đưa `1e0`, `1.0`, `1abc`,
  số âm hoặc số tràn xuống database để bị ép kiểu.
- Add/update/remove dùng ID chuẩn; giỏ hiện có được chuẩn hóa trước thao tác.
- Giỏ legacy có dòng trùng được gộp quantity, không bỏ mất dòng một cách im lặng.
  Tổng vượt stock phải giảm trước khi mua; quantity vượt giới hạn vẫn mở được Cart
  và sửa xuống số hợp lệ, không overflow PHP hoặc ghi SQL.
- Nếu POST dùng quote của giỏ legacy chưa chuẩn hóa, server cập nhật giỏ và yêu cầu
  xem lại Checkout, không tự mua với một snapshot đã đổi.
- Checkout có thêm kiểm tra trùng Product ID thực trước khi tạo đơn/trừ kho.
- Test lỗi cũ chuyển từ `tests/manual` vào `tests/Feature/SessionAndAliasRetestTest.php`.

Bằng chứng MariaDB sau sửa (mọi fixture trong script đều rollback):

| Ca | Kết quả |
| --- | --- |
| Kho 5, add 3 qua ID chuẩn rồi add 3 qua ID alias | Chỉ giữ một dòng quantity 3; mua 3; stock 2 |
| Giỏ cũ 3 + 3 cùng Product | Gộp 6; từ chối mua, 0 đơn mới; stock vẫn 5 |
| Giỏ cũ 2 + 2 cùng Product | Gộp 4, một OrderItem; stock 1 |
| POST quote cũ của giỏ trùng, không GET lại | Từ chối và yêu cầu xác nhận lại; 0 đơn mới; stock vẫn 5 |
| HTTP thật qua Chrome/CSRF | ID alias không tạo dòng thứ hai; mua 3 rồi hủy, stock về 5 |

### R08 — Tên tài khoản dài làm tràn navbar: đã đóng

Tài khoản hợp lệ có tên 255 ký tự không có khoảng trắng làm viewport 320px bị tràn
đến 3347px khi mở menu. Thêm `account-name` và CSS `overflow-wrap:anywhere`, giới hạn
chiều rộng của tên trong flex layout. Chrome xác nhận tên thật trong DB test không
còn tràn ở 320/390/768/1440, gồm menu mobile mở.

## Kiểm thử tổng hợp đã thực thi

| Hạng mục | Kết quả hiện hành |
| --- | --- |
| Suite mặc định, vendor chính | **131 tests / 2.970 assertions, đạt** |
| Suite trên vendor cài sạch | **131 tests / 2.970 assertions, đạt** |
| Ma trận Product create/update | 48 lượt submit sai giá/quantity/FK/name/description; validation, không ghi sai |
| Ma trận Checkout | 21 lượt submit sai kiểu/độ dài/payment/token; không ghi đơn/trừ kho |
| Trạng thái Admin | Kiểm tra đủ 16 cặp trạng thái; trạng thái và stock đúng |
| Hủy đơn Customer | 4 trạng thái × 2 chủ sở hữu; chỉ chủ đơn pending được hủy |
| Chuỗi tồn kho | 60 thao tác add/update/remove/checkout/cancel trên 3 Product, có alias; sau mỗi bước stock không âm và stock + lượng giữ trong đơn = tồn ban đầu |
| Chuẩn hóa giỏ | 30 tổ hợp, mỗi tổ hợp 20 dòng alias; giữ tổng quantity, không trùng ID, chuẩn hóa lần hai không đổi kết quả |
| ID không hợp lệ | 8 biểu diễn × add/update/remove đều trả 404, không để SQL ép kiểu |
| Transaction | Giữ các ca rollback OrderItem thứ hai, lưu trạng thái hủy, overflow hoàn sản phẩm thứ hai, lỗi xóa Category và lỗi upload |
| MariaDB cạnh tranh | 3 vòng mua sản phẩm cuối/hủy cùng đơn; thứ tự khóa đảo; Admin/Customer cùng hủy; token lặp khi còn dư hàng và khi mua sản phẩm cuối: đạt |
| MariaDB biên lưu trữ | Phone 20/21, tiền cực đại/overflow, TEXT UTF-8, khoảng trống hoàn kho: đạt |
| HTTP lỗi cũ | NUL password → 422; email array → POST 302 rồi GET form 200 |
| Chrome Admin/Customer | Tạo Category, upload/sửa Product, checkout, processing → cancelled, stock hoàn đúng |
| Responsive Chrome | 16 lượt công khai + 14 lượt có đăng nhập + 4 lượt tên tài khoản cực dài: **34 lượt đạt** |
| Assets/menu/JS | Local Bootstrap/offline assets/menu mobile đạt; smoke không có JS error |
| Cú pháp và Blade | 103 file PHP lint và Blade compile đạt ở vòng tổng hợp; file lifecycle mới lint đạt riêng |
| Dependencies | Composer validate đạt; audit không có advisory/abandoned trong kết quả trả về |
| Git | Pint các file sửa và `git diff --check` đạt; credential/JS private được Git ignore |

Test hệ thống mới: `SystemBoundaryMatrixTest`, `InventoryInvariantTest`,
`CartNormalizationTest`, `SessionLifecycleRetestTest`, mở rộng `SessionAndAliasRetestTest`. Toàn bộ ở suite mặc định,
không còn giữ ca R07 đỏ ở `tests/manual`.

## Tính toàn vẹn dữ liệu sau kiểm thử

`tests/manual/data-integrity-audit.php` chỉ đọc DB demo và trả **0** cho cả 7 loại:
tồn âm, quantity Item không hợp lệ, subtotal sai, tổng Order sai, Product trùng trong
một đơn, trạng thái không hợp lệ và OrderItem mồ côi.

DB ứng dụng `ban_giay_the_thao` chỉ được đọc; counts không đổi:
users=1, categories=2, products=0, orders=0, order_items=0.
Không tạo Admin, đổi mật khẩu/role hoặc chạy migration trên DB ứng dụng.

Các ca ghi dùng `sneakershop_gd3_test`. Fixture HTTP riêng đã hủy đơn và hoàn kho;
Product HTTP fixture đã ẩn. Luồng UI Admin dùng Product 139 / Order 97, đơn đã hủy
và stock hoàn về 5; giữ dữ liệu demo để đối chiếu. Credentials không ghi trong báo cáo/Git.

## Chạy lại

PowerShell đang chặn chạy `.ps1` có thể gọi trực tiếp PHP portable; không cần đổi
Execution Policy hệ thống:

```powershell
& ./storage/app/php83/php.exe vendor/phpunit/phpunit/phpunit
& ./storage/app/php83/php.exe tests/manual/cart-id-alias-audit.php
& ./storage/app/php83/php.exe tests/manual/phone-length-audit.php
& ./storage/app/php83/php.exe tests/manual/gd3-concurrency.php
& ./storage/app/php83/php.exe tests/manual/deep-lock-audit.php
& ./storage/app/php83/php.exe tests/manual/last-unit-replay.php
& ./storage/app/php83/php.exe tests/manual/deep-mysql-audit.php
& ./storage/app/php83/php.exe tests/manual/data-integrity-audit.php
```

PHPUnit tự guard SQLite `:memory:`; các script MariaDB tự guard schema test.
XML/log ignored: `storage/app/gd3-comprehensive-final.xml`,
`storage/app/gd3-comprehensive-clean.xml`; lượt mới nhất:
`storage/app/gd3-lifecycle-final.xml`, `storage/app/gd3-lifecycle-clean.xml`.

Browser có `tests/browser/smoke.js`, `acceptance-invalid-input.js`, `full-acceptance.js`,
`session-lifecycle.js`. Hai file cuối là template, cần thay placeholder bằng fixture riêng trong bản JS ignored;
không chạy template chưa thay placeholder hoặc đưa credentials vào Git.

## Kiểm thử tiếp vòng đời session và nhiều tab

Lượt bổ sung cùng ngày 23/10, **8 test / 86 assertions mới đều đạt**, không tìm thấy
lỗi mới trong các tình huống sau:

1. Đăng nhập thay session ID và CSRF token, giữ dữ liệu guest không nhạy cảm.
2. Logout đổi remember token, xóa xác thực, route Customer trở lại yêu cầu login.
3. Hai form checkout cùng giỏ: form cũ bị từ chối, không tạo đơn/trừ kho và không
   làm mất quote mới; form mới vẫn mua được đúng một đơn.
4. Đổi quantity ở tab khác làm cả hai form cũ hết hiệu lực; xác nhận lại mua đúng quantity mới.
5. Sản phẩm bị xóa sau khi đơn commit: replay trả chính đơn đã tạo, không thêm item;
   snapshot tên/giá và lịch sử vẫn tồn tại dù product_id thành NULL.
6. Product ngừng bán sau quote: không thể gửi is_active giả để mua.
7. Hết hàng sau quote rồi được bổ sung kho: khi hết hàng không tạo đơn; sau kiểm tra
   và xác nhận lại chỉ mua đúng phần kho vừa có.
8. Tên/địa chỉ/note chứa script được lưu như văn bản; Cart/Success/Admin Orders escape khi hiển thị.

Chrome/HTTP thật dùng fixture riêng và CSRF:

- Form cũ trả 422 lỗi cart; form mới trả 302 success; chỉ một đơn quantity=3.
- Hủy đơn và logout thành công, kho trở về 5; Product fixture đã được ẩn.
- Lấy remember cookie trước logout, đưa riêng cookie này vào browser context mới:
  GET Cart vẫn chuyển Login. Cookie cũ không xác thực lại được.
- Không ghi cookie/password vào báo cáo hoặc Git; JS private được ignore.

Kiểm tra MariaDB sau lượt này: cả 7 loại vi phạm integrity vẫn **0**; DB ứng dụng
giữ nguyên counts nêu trên. Toàn bộ suite chính và clean vendor đều **131/2.970 đạt**.
Lượt bổ sung chỉ thêm test và cập nhật báo cáo, không đổi mã nghiệp vụ.

## Phạm vi kết luận và phần bàn giao còn lại

R01–R08 và A01–A18 đã có bản sửa/bằng chứng sau sửa. Kết luận dựa trên các ca đã
thực thi, không phải bảo đảm toán học rằng phần mềm không thể còn lỗi khác.
Chưa kiểm thử MySQL 8, iOS thật hoặc tải lớn kéo dài. Server cục bộ phục vụ kiểm thử
là port 8023, trỏ DB demo, có CSRF.

GĐ3 đã tích hợp vào develop và triển khai migration checkout_token trên DB ứng dụng,
schema 9 bảng/70 cột, dữ liệu nghiệp vụ/tài khoản không đổi. Nghiệm thu HTTP ứng dụng đạt.
Chưa phát hành lên main hoặc hosting bên ngoài.

## 2026-11-17 — Kiểm thử tích hợp sau PR #27

Bản kiểm thử là `origin/develop` tại `492f2db688a1df7774ec31a843fa61f8ad664c68`.
[CI sau merge](https://github.com/giahuy5523/DeTai_LapTrinhMaNguonMo_/actions/runs/44099627977) đạt.
Môi trường Windows, PHP 8.3.33, Composer 2.11.2, Laravel 12.71.0,
PHPUnit 11.5.57, MariaDB 10.4.32 và Chrome 157 với Playwright CLI 0.1.22.
MariaDB chạy trên instance loopback riêng, cổng 33429 và thư mục dữ liệu mới.
Migration và seeder chạy trên schema kiểm thử `sneakershop_gd3_test`.

| Kịch bản | Mong đợi và kết quả quan sát |
| --- | --- |
| PHPUnit trên SQLite trong bộ nhớ | `php vendor/phpunit/phpunit/phpunit` đạt 151 test, 3.079 assertions. |
| MariaDB migration và khóa ngoại | 12 migration ở trạng thái Ran. Có 4 khóa ngoại; thử chèn sản phẩm trỏ danh mục không tồn tại nhận mã 1452. |
| MariaDB đơn hàng, số tiền và tồn kho | Có Order/OrderItem. Giá biên 9999999999999.99 lưu chính xác. COD hai sản phẩm ghi 2420000.00 và hủy thành công; chuyển khoản demo một sản phẩm ghi 1210000.00 và sang processing. Audit dữ liệu trả 0 vi phạm ở 7 chỉ số. |
| MariaDB rollback | Giả lập lỗi khi tạo OrderItem thứ hai. Số Order/OrderItem và tồn kho hai sản phẩm trở về giá trị trước checkout. |
| MariaDB nhiều tiến trình | `gd3-concurrency.php` đạt 3 vòng mua sản phẩm cuối và hủy đồng thời. `deep-lock-audit.php` đạt ca khóa sản phẩm đảo thứ tự và hủy Admin/Customer. `last-unit-replay.php` trả cùng mã đơn cho hai worker, một đơn duy nhất và stock về 0 trước khi hoàn kho. |
| Chrome khách | Trang chủ, danh sách và tìm kiếm mở được. Giỏ hàng và Admin yêu cầu đăng nhập. |
| Chrome Customer | Đăng ký, đăng nhập, đổi tên và mật khẩu, đăng xuất rồi đăng nhập bằng mật khẩu mới. Thêm giỏ có phản hồi AJAX JSON, sửa và xóa dòng hàng. Vượt tồn kho có thông báo và giữ nguyên số lượng. Checkout COD và chuyển khoản demo đến trang thành công. Customer hủy COD đang chờ. |
| Chrome Admin | Đăng nhập, tìm tài khoản và đổi role. Tạo, sửa, xóa danh mục và sản phẩm. Ảnh upload và ảnh thay thế trả HTTP 200, URL ảnh cũ trả 404. Đơn pending từ chối chuyển thẳng completed với HTTP 422 và chấp nhận chuyển processing. |
| Chrome phân trang | Nhấp liên kết đang hiển thị trên trang tìm kiếm sản phẩm, sang trang 2 và giữ keyword. |
| Chrome responsive | Đo document scrollWidth ở 390, 768, 1440 px trên trang chủ, cửa hàng, đăng nhập, đăng ký, giỏ, checkout, dashboard Admin và trang danh sách danh mục, sản phẩm, tài khoản, đơn hàng. Không ghi nhận tràn ngang toàn trang ở các viewport này. |

Không ghi nhận lỗi ứng dụng cần sửa trong phạm vi đã chạy. Lượt Chrome đầu có
bộ chọn mơ hồ và kỳ vọng sai mã HTTP của form vượt kho. Sau khi sửa kịch bản
kiểm thử, cả 6 nhóm đều đạt. PHPUnit bảo vệ cấu hình SQLite; ca rollback
MariaDB dùng bản kiểm thử tạm, không sửa bộ test đã commit.

Giới hạn: đồng thời được xác minh bằng worker PHP kết nối MariaDB. Chưa chạy
race qua hai HTTP server, thiết bị vật lý hoặc trình duyệt khác. Responsive là
kết quả đo trên Chrome; chưa rà từng thành phần một cách trực quan.

## Nâng cấp giữ hàng và giao diện — 24/11/2026

- PHPUnit SQLite đạt 155 test / 3.088 assertions.
- MariaDB 10.4.32, schema sneakershop_gd3_test trên instance demo cổng 33430:
  migration reservation chạy thành công. Hai tiến trình tranh đôi cuối trả
  accepted/rejected, quantity giữ nguyên. Lượt hết hạn không tính vào khả dụng.
- Chrome trên localhost:8027 đăng nhập Customer, thêm giỏ AJAX, mở checkout
  thấy 5:00, reload giữ nguyên token, đặt COD tới trang thành công.
- Blade compile và git diff --check đạt.
- Guard SQLite từng chặn lần chạy suite với DB_CONNECTION=mysql từ terminal.
  Sau khi đặt đúng SQLite trong bộ nhớ, toàn bộ suite đạt.
- Chưa xác minh đủ responsive của toàn bộ giao diện mới, chưa chạy QR mô phỏng
  và voucher. Hai module này còn cần Trọng/Tuấn triển khai theo phân công.
- Banner dùng ảnh Unsplash lưu local:
  https://images.unsplash.com/photo-1542291026-7eec264c27ff
  và https://images.unsplash.com/photo-1549298916-b41d501d3772.
  Cần kiểm tra attribution/nguồn trước khi phân phối chính thức.

### Xác minh bổ sung sau sửa consume

Phát hiện nhánh tạo đơn vẫn dùng transaction cũ, đã chuyển sang consume của
StockReservationService. Test mới xác nhận reservation có trạng thái consumed
sau COD; hủy đơn rồi expire không hoàn kho lần hai; POST hết hạn không tạo đơn.
Bộ hồi quy tại thời điểm chạy đạt 157 test / 3.104 assertions. Sau đó bổ sung
hai ca nhiều sản phẩm và quyền sở hữu; toàn bộ nhóm reservation đạt 8 test /
32 assertions. CI của PR là nguồn kết quả toàn suite cuối cùng.

Chrome đo 12 tổ hợp trang chủ/cửa hàng/login/register tại 390/768/1440 px
không tràn ngang toàn trang. Navbar tìm kiếm chuyển đúng keyword.

### Chốt nghiệm thu phần Gia Huy — 24/11/2026

PR #34, commit chức năng a2a7246 có CI đạt 159 test / 3.110 assertions,
Composer audit không có advisory. Sau đó chạy lại Chrome bằng Playwright
trực tiếp để lấy kết quả độc lập, thay cho lượt CLI trả kết quả trống.

Kết quả Chrome PASS:
- Hai tab trong cùng phiên dùng chung checkout token.
- COD tới trang thành công và hủy đơn đang chờ thành công.
- Giỏ hàng, checkout, trang thành công và năm trang Admin đều HTTP 200.
- 24 tổ hợp các trang trên tại 390/768/1440 px không tràn ngang toàn trang.
- Menu Admin mobile mở được. Đã xem ảnh chụp mobile để kiểm tra bố cục.
- Cộng với 12 phép đo public trước đó, có tổng cộng 36 phép đo responsive.
Ảnh bằng chứng: gia-huy-admin-mobile.png. Bảng mobile cuộn trong vùng table-responsive.

Các yêu cầu server còn lại có test: nhiều sản phẩm rollback nguyên lượt giữ
khi một món không đủ, từ chối user khác, hết hạn chính xác, consume rollback,
COD consumed, hủy đơn và expire không hoàn kho lần hai. MariaDB hai worker
accepted/rejected và tự loại lượt hết hạn đã đạt.

Phần lập trình và kiểm tra của Gia Huy đã có bản bàn giao để review.
Nghiệm thu sau merge trên develop và tích hợp QR/voucher của các thành viên
là các bước tiếp theo của nhóm. Chưa chạy QR/voucher và chưa có bằng chứng
race qua hai HTTP server. Không dùng các kết quả đó trong kết luận hiện tại.

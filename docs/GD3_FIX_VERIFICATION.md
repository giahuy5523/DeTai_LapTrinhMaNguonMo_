# GĐ3 — Kết quả sửa lỗi và kiểm thử bàn giao

> **Trạng thái hiện hành:** R01–R08 đã đóng; **131 tests / 2.970 assertions đạt**.
> [Bản sửa và QA tổng hợp](GD3_FINAL_QA.md). Phần dưới giữ kết quả các vòng trước.

> **Mới nhất:** R06 đã sửa; **103 tests / 748 assertions đạt**, phone 21 ký tự
> bị chặn trước SQL. [Kiểm chứng MariaDB](GD3_BOUNDARY_RETEST.md).

> **Mới nhất:** đã sửa R05; **96 tests / 700 assertions đạt**. Hai worker MariaDB
> mua sản phẩm cuối bằng cùng token nhận cùng một đơn. [Bằng chứng](GD3_TRANSACTION_RETEST.md).

> **Cập nhật sau nghiệm thu lại 23/10:** đã sửa cả 4 nhóm lỗi bổ sung R01–R04.
> Suite hiện tại **83 tests / 575 assertions, đạt**; HTTP validation/form đã kiểm chứng.
> Xem [bản sửa sau kiểm thử độc lập](GD3_ACCEPTANCE_RETEST.md).
> Các số liệu 69 test bên dưới ghi nhận vòng sửa trước.

Ngày hoàn tất kiểm chứng: **23/10/2026** (Asia/Saigon), tiếp nối audit ngày 22/10.
Nhánh local: `codex/gia-huy-gd3`; nền `origin/develop` đã fetch là
`804fdc629318b35fd17f807d27bb79cc76af2101`.

**Đã sửa toàn bộ 18 vấn đề được ghi nhận trong audit, đưa các ca hồi quy vào suite mặc định,
và chạy lại trên SQLite, MariaDB và trình duyệt. Chưa commit/push/tạo PR/merge.**
Báo cáo audit cũ là bằng chứng trước sửa, không phải danh sách lỗi đang mở.

## Đối chiếu các lỗi cũ

| ID | Thay đổi và bằng chứng sau sửa | Trạng thái |
| --- | --- | --- |
| A01 | Form Product có phiên bản HMAC, kiểm dưới row lock. Token cũ được giữ khi validation thất bại. SQLite và Chrome/MariaDB: khách mua trong lúc Admin giữ form; lưu cũ hai lần đều bị chặn, không ghi đè stock. | Đạt |
| A02 | Laravel khóa session bằng file cache trước nạp/lưu session, quote được tiêu thụ sau checkout; orders.checkout_token có unique index làm chốt chống trùng bền vững. Hai HTTP worker 8023/8024 cùng cookie: chỉ 1 POST success, tạo 1 đơn, stock 10→8 thay vì 10→6. | Đạt |
| A03 | GET checkout đọc giá mới; quote gắn snapshot + token ngẫu nhiên. POST sau khi giá/giỏ thay đổi bị từ chối, yêu cầu xác nhận lại. Không âm thầm ghi tổng khác giá vừa xác nhận. | Đạt |
| A04 | Khóa Product theo ID tăng dần; transaction retry tối đa 3 lần. Hai process mua cùng hai sản phẩm theo thứ tự ngược: cả hai thành công, stock đúng. | Đạt |
| A05 | Tiền tính bằng integer theo 1/100 đơn vị, lưu decimal string. MariaDB mua 1 sản phẩm 9999999999999.99: tổng lưu chính xác cùng giá trị. | Đạt |
| A06 | Kiểm tra tích/tổng trước phép nhân vượt giới hạn và trước ghi SQL. Cộng nhiều dòng vượt decimal(15,2) trả validation; không ghi đơn dở. | Đạt |
| A07 | Admin phải chừa chỗ cho stock có thể hoàn từ đơn pending/processing. Hai luồng hủy dùng chung kiểm tra giới hạn. Dữ liệu legacy bị sửa trực tiếp sai giới hạn trả validation yêu cầu chỉnh stock, không exception SQL hoặc hoàn kho thiếu. | Đạt |
| A08 | Rule SqlText giới hạn 65.535 byte UTF-8 ở create/update Category/Product. 70.000 byte và 16.384 emoji bị chặn, biên hợp lệ được chấp nhận. | Đạt |
| A09 | Dọn file chỉ khi không còn Product tham chiếu; seeder mới dùng đường dẫn riêng cho mỗi sản phẩm. Test thay/xóa một sản phẩm vẫn giữ ảnh dùng chung của sản phẩm khác, xóa tham chiếu cuối mới dọn. | Đạt |
| A10 | Xóa Category khóa và thu thập ảnh Product trước cascade; sau transaction dọn ảnh không còn tham chiếu. | Đạt |
| A11 | Quantity có mặt thì required/integer/min 1/max int. Không tạo item 0; giỏ legacy có item 0 được làm sạch an toàn. | Đạt |
| A12 | Keyword/page/FK được validate kiểu trước query; array không gây 500. | Đạt |
| A13 | Keyword "0" vẫn lọc tên, có nút Xóa lọc. Sort có ID phá hòa để phân trang ổn định. | Đạt |
| A14 | Điện thoại có định dạng cho phép và 9–15 chữ số; "abc" và "0-------0" bị chặn. | Đạt |
| A15 | Các view tiền dùng formatter chính xác, không làm tròn mất phần lẻ. 1250.50 hiển thị 1.250,50; 2 sản phẩm tổng 2.501. | Đạt |
| A16 | Remember được validate và truyền vào Auth::attempt; test xác nhận recaller cookie. | Đạt |
| A17 | Error flash dùng layout chung, không render lại ở Cart/Checkout/Store. Hai ca Cart/Checkout xác nhận xuất hiện đúng một lần. | Đạt |
| A18 | Xác nhận hủy dùng “Bạn”; lịch sử đơn có nhãn trạng thái tiếng Việt. | Đạt |

## Lỗi bổ sung tìm được trong vòng sửa và đã xử lý

- Form bị từ chối không được ghép giá trị cũ với token mới; thêm test bấm lưu cũ lần hai.
- Quote cũ không dùng lại được khi xóa/thêm hoặc đổi số lượng rồi trả về như ban đầu.
- Giá tăng làm giỏ vượt giới hạn không gây vòng lặp redirect; Cart vẫn mở và cho giảm số lượng.
- Một giỏ tổng vượt giới hạn không ghi đè giỏ hợp lệ đang có; FK category_id phải là một integer, không nhận array.
- Store Admin hiển thị Quản lý sản phẩm, không hiển thị nút mua bị middleware từ chối. Lỗi quantity hiển thị tại Store.
- Orders chỉ hiển thị bước chuyển hợp lệ; completed/cancelled không có form sửa. Server vẫn chặn nếu cố gửi request trực tiếp.
- Login/register dùng guest; logout dùng auth. Đường Home của middleware sửa từ /home không tồn tại về /.
- Test boot có guard xác minh connection thực tế là SQLite :memory: trước RefreshDatabase, kể cả khi có config cache/DB URL sai.
- Windows artisan serve reloader lọc biến môi trường của child process và có thể làm rơi cấu hình DB test; wrapper tự thêm --no-reload. Đã mở server theo README và xác nhận trả dữ liệu GD3, không phải DB ứng dụng.
- Bootstrap được lưu local, có LICENSE và kiểm SRI theo nguồn chính thức; UI không phụ thuộc CDN/internet.

## Bằng chứng cuối

| Kiểm tra | Kết quả |
| --- | --- |
| Suite mặc định, PHP 8.3.36 / PHPUnit 11.5.57 | **69 tests / 481 assertions — đạt** |
| Cài sạch dependency từ composer.lock vào storage/app/clean-vendor | **117 packages cài thành công** |
| Chạy lại suite bằng autoload/vendor cài sạch | **69 tests / 481 assertions — đạt** |
| MariaDB biên dữ liệu | **6 ca đạt**: giá tối đa chính xác; tích vượt giới hạn; mô tả Category/Product; stock legacy; giới hạn Admin chừa chỗ hoàn hàng |
| Mua sản phẩm cuối và hủy cùng đơn | **3 vòng đạt**, mỗi vòng 1 chấp nhận/1 từ chối đúng nghiệp vụ, stock hoàn đúng 1 |
| Giỏ A→B / B→A | **2 checkout đều đạt**, không deadlock; tổng stock sau cả hai đúng |
| Admin/Customer hủy cùng đơn | **1 chấp nhận/1 từ chối**, stock hoàn một lần; dọn các đơn thử còn lại khôi phục đúng 5 cho mỗi sản phẩm |
| HTTP double-submit | **1 đơn**, stock **10→8**, request còn lại về giỏ trống; không có đơn thứ hai |
| Hai worker cùng quote, không dùng chung session lock | **1 đơn**, stock chỉ trừ 1 lần; cả hai nhận kết quả đơn gốc |
| CSRF thực (APP_ENV=local) | Token sai trả **419** |
| Chrome E2E CRUD/upload/checkout/COD/hủy | **Đạt**, stock fixture **5→3→5**; sửa/thay/xóa ảnh và danh mục được kiểm qua UI |
| Chrome form Admin cũ | **Bị từ chối 2 lần**, khách hủy sau đó hoàn đúng stock ban đầu |
| Chrome bank_transfer demo → processing → completed | **Đạt**; hủy completed trực tiếp trả **422**, success hiển thị trạng thái mới nhất và bỏ hướng dẫn chuyển khoản |
| Responsive Admin/Cart/Checkout/Success | 1440px và 390px: **không tràn ngang** trong các ca đã chạy |
| Smoke public/auth không dùng mạng ngoài localhost | **16 tổ hợp**: 4 trang×320/390/768/1440; không tràn ngang, không lỗi JS, menu mobile đạt |
| Composer audit --locked | **0 advisory**, không package abandoned tại thời điểm kiểm chứng |
| Composer validate / check-platform-reqs | **Đạt**, PHP 8.3 x64 |
| PHP lint 89 file, Blade compile, git diff --check | Đạt; xem nhật ký kiểm tra cuối |

Các đơn thử cạnh tranh được hủy và sản phẩm thử được ẩn, giữ lịch sử để đối chiếu.
Một đơn bank_transfer demo completed được giữ làm dữ liệu demo hợp lệ; GD3 Sneaker 11 còn 9 sau khi bán 1.
Không có xác nhận tiền ngân hàng tự động hay giao dịch tài chính thật.

## Thay đổi môi trường bắt buộc

Composer phát hiện 6 advisory trên Laravel cũ/CommonMark. Đã cập nhật **Laravel 12.70.1**,
Sanctum 4.3.3, CommonMark 2.10.4, Collision 8 và PHPUnit 11 theo các phụ thuộc tương thích;
bật chặn advisory và platform-check. Giữ cấu trúc ứng dụng Kernel/Providers hiện có.
Yêu cầu PHP 8.3+ x64 được khai báo/enforce; PHP portable chỉ nằm cục bộ, không đưa binary lên Git.

Nguồn đối chiếu: [Laravel 10→11](https://laravel.com/docs/11.x/upgrade),
[Laravel 11→12](https://laravel.com/docs/12.x/upgrade),
[advisory email validation](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq),
[Bootstrap/SRI](https://getbootstrap.com/docs/5.3/getting-started/download/).
Password rehash tự động khi login được tắt để giữ nguyên hash tài khoản có sẵn; không đổi mật khẩu/quyền.

## Database và Git

- DB ứng dụng **ban_giay_the_thao chỉ đọc**. Số dòng giữ nguyên users=1, categories=2,
  products=0, orders=0, order_items=0. Không seed, tạo Admin, nâng quyền, đổi mật khẩu hay reset dữ liệu ứng dụng.
- DB test sau migration mới: 9 bảng, 70 cột, 4 FK; ERD cập nhật checkout_token nullable/unique. DB ứng dụng vẫn 69 cột vì chỉ đọc. Khi triển khai bản sửa phải chạy migration add_checkout_token bằng php artisan migrate; không fresh/wipe và không sửa các đơn cũ.
- Test viết dữ liệu chỉ dùng SQLite memory hoặc **sneakershop_gd3_test** đã được trưởng nhóm đồng ý.
- Mọi thay đổi còn local trên codex/gia-huy-gd3; **chưa commit/push/PR/merge**, develop/main không bị ghi trực tiếp.
- File credential, runtime, clean-vendor và các script riêng có mật khẩu nằm trong storage/app được Git ignore.

## Đánh giá nhiệm vụ Gia Huy

GĐ1–GĐ2: các chức năng được phân công đã có và được kiểm thử hồi quy sau sửa.
GĐ3: phần sửa code, kiểm thử tích hợp/responsive và dữ liệu demo tách biệt đã thực hiện.
**Còn bước bàn giao Git qua commit/push/PR develop và review/merge theo quyết định trưởng nhóm**;
không được đồng nhất “đạt local” với “đã tích hợp/bản main final”.

Không làm slide/Word/Use Case hoặc mở rộng thanh toán online.
Hướng dẫn dựng/chạy lại ở [README](../README.md). Test hồi quy ở
[DeepLogicRegressionTest](../tests/Feature/DeepLogicRegressionTest.php),
[FinalLogicRegressionTest](../tests/Feature/FinalLogicRegressionTest.php),
[MoneyTest](../tests/Unit/MoneyTest.php); các script MariaDB trong tests/manual và smoke trong tests/browser.

Giới hạn kiểm chứng: MariaDB 10.4.32, Chrome và các kích thước đã nêu; chưa kiểm thử MySQL 8,
thiết bị iOS thật hoặc tải production lớn. Các vòng kiểm tra hiện tại không phát hiện lỗi chưa xử lý;
đây không phải chứng minh tuyệt đối không còn lỗi trong mọi điều kiện.

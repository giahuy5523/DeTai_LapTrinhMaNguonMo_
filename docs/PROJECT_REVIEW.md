# Kiểm tra SneakerShop – 11/10/2026

## Nền code được kiểm tra

- Remote develop: f2929e185550377868f3561ca4eed3f642536486.
- Local trước kiểm tra: fd7f931, chỉ mới tới PR #1; working tree sạch.
- Branch triển khai Gia Huy: feature/gia-huy-gd2-ui-erd, tạo từ origin/develop.
- Auth: 1620818, ahead develop 7 / behind 2.
- Cart và search branch đều đã nằm trong lịch sử develop (ahead 0 / behind 26).
- .env local: MySQL, DB_DATABASE=ban_giay_the_thao.
- DB local có 2 categories, 1 user, chưa có product/order/order_item tại lúc kiểm tra.
- 8 migration cũ đều đã chạy; users.role lại chưa tồn tại do sửa migration sau khi đã chạy.

## Đối chiếu yêu cầu

| Hạng mục | Kết quả / việc cần làm |
|---|---|
| Laravel 10, PHP 8, MySQL | Có, home đọc được DB local |
| Eloquent, migration, quan hệ | Có Category/Product/User/Order/OrderItem; kiểm thử DB mới bằng SQLite |
| Seeder | Có, chạy thành công trên DB test mới; chưa idempotent khi chạy lại |
| Blade + Bootstrap 5 | Có; hoàn thiện layout khách/admin và responsive GĐ2 |
| CRUD Category/Product + upload | Có backend validation; kiểm thử render các form; chưa test file upload trong lượt này |
| Auth | Kiểm thử register/login/logout thành công trên DB test |
| Admin/Customer | develop có AdminMiddleware; CustomerMiddleware và route customer còn ở branch Tuấn |
| Search + pagination | Admin product keyword + paginate(5); order status + paginate(10); trang khách chưa có search |
| Cart/checkout/stock | Có session cart, transaction, lockForUpdate; test đặt hàng trừ stock và clear cart đạt |
| Validation | Có Auth, Category/Product, Checkout; chưa rà hết edge case |
| Home/admin/ERD Gia Huy | Hoàn thiện trong branch GĐ2 này |
| Báo cáo/ảnh/E2E GĐ3 | Chưa chốt; chưa có sản phẩm thật để demo toàn luồng MySQL |
| Use Case | Bỏ theo yêu cầu giảng viên |

## Những điểm còn lại của nhóm

1. Tuấn: merge GĐ2 qua PR về develop sau khi sửa migration thêm role trùng. Branch Tuấn thêm role không kiểm tra tồn tại, trong khi DB mới từ develop đã có role; DB local sau migration sửa cũng sẽ có role.
2. Gia Huy: migration ensure_role bổ sung cho DB cũ, mặc định customer. Không tự đổi quyền tài khoản hiện có.
3. Vĩ: search mới ở admin; nếu cần người mua tìm kiếm thì bổ sung StoreController và store/index. Lọc order đang chưa giữ status khi paginate từ controller nhưng view appends nên vẫn giữ.
4. Category/Product dùng slug unique nhưng validate theo name; hai tên khác nhau cùng slug có thể lỗi DB. Chưa sửa nghiệp vụ CRUD trong lượt UI này.
5. Route resource có show, controller chưa có show; UI không dùng route show. Có thể giới hạn resource trước bản cuối.
6. Order cancelled chưa có cơ chế hoàn stock. Cần nhóm thống nhất nghiệp vụ trước khi sửa.
7. Login/register views của Tuấn vẫn là giao diện riêng; checkbox remember chưa được backend Auth::attempt truyền vào.
8. Chạy Seeder lại trên DB đã seed có thể trùng email/name; không chạy Seeder trên DB local có dữ liệu.
9. Không kết luận đã hoàn thành GĐ3: cần test MySQL với sản phẩm/ảnh thật, báo cáo, screenshot và tích hợp auth GĐ2.

## GĐ2 Gia Huy đã làm

- Home mới, chỉ hiển thị sản phẩm active; xử lý rỗng/hết hàng; form thêm giỏ dùng cart.add + CSRF thật.
- Layout chung có navbar collapse, login/register/user/logout, giỏ hàng và liên kết quản trị theo role.
- Layout admin có menu dashboard/category/product/order và trạng thái mục đang mở.
- Dashboard đọc số liệu DB: sản phẩm, danh mục, đơn, pending, tổng completed, 5 đơn mới.
- Các bảng admin cuộn ngang trên mobile; phân trang Bootstrap 5; bỏ block product pagination trùng.
- ERD với đủ cột, FK, nullable, quy tắc xóa và mô tả chức năng.
- Test dùng SQLite memory bắt buộc, không dùng DB ứng dụng.

## Kiểm thử

- php artisan test: 8 tests, 60 assertions, gồm migration sửa DB cũ và schema mới.
- Test fresh migration + Seeder, register/login/logout, cart/checkout/trừ stock.
- Test home rỗng/active/inactive/hết hàng, role bảo vệ dashboard, số liệu dashboard, render admin forms và một thanh pagination.
- Home đã kiểm tra bằng trình duyệt tại 127.0.0.1:8013, DB MySQL local.
- Bootstrap tải qua CDN; môi trường demo cần internet hoặc chuẩn bị asset local.

Không merge main/develop trong lượt triển khai này; thay đổi đưa qua PR.

Migration ensure_role đã chạy thành công trên MySQL local; role mặc định customer. Ảnh home: home-preview.jpg.

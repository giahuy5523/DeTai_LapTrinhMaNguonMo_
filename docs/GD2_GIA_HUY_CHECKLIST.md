# Checklist GĐ2 Gia Huy – 17/10/2026

Nền: origin/develop c566220. Nhánh làm việc: feature/gia-huy-gd2-completion.
Không commit/push/merge trong lượt này trước khi báo user. Không thay đổi DB hiện tại.

| Hạng mục | Trạng thái ban đầu | Phần triển khai |
|---|---|---|
| HomeController, dashboard | Đã có | Rà logic hiển thị active/hết hàng/số liệu và test |
| Layout Home/Admin | Đã có Bootstrap 5 | Rà responsive/menu/role; bỏ flash trùng ở index Category/Product |
| CRUD Category/Product | Đã có | Giới hạn giá/stock theo kiểu SQL, test validation |
| Upload và hiển thị ảnh | Đã có nhưng xóa ảnh cũ trước khi lưu thay thế | Giữ ảnh cũ khi upload/lưu DB thất bại, dọn file mới khi DB lỗi |
| Form | Label chưa gắn input, giá thiếu step thập phân | Bổ sung id/for, step, invalid-feedback, alt ảnh edit |
| ERD | Thiếu thuộc tính/hệ thống, ghi chú cũ | ERD.mmd đầy đủ PK/FK/UK/default/null, tài liệu nguồn và SVG |
| Sơ đồ/ma trận CRUD | Chưa có | CRUD_MATRIX.md và CRUD.mmd theo quyền và route thực tế |
| Test redirect | 2 assertion cũ trên develop | Áp dụng đúng hai thay đổi đã có ở PR #17, không sửa Auth |
| MySQL hiện tại | Đã bật XAMPP | Đã đối chiếu 9 bảng/68 cột, 4 FK; không thay đổi dữ liệu |
| Use Case | Không yêu cầu | Không tạo |
## Kết quả hoàn thiện

- ERD có đủ 9 bảng (5 nghiệp vụ + 4 hệ thống), đúng cột/PK/FK/UK/nullable/default; nguồn Mermaid chỉnh sửa được.
- Ma trận CRUD mô tả quyền thực tế và phân biệt thao tác UI, thao tác controller gián tiếp, hành vi FK.
- HomeController/DashboardController đã kiểm tra, giữ nguyên vì không tìm thấy lỗi trong phạm vi đợt này.
- Không sửa AuthController, middleware, CartController, CheckoutController, OrderController hay phần search của Vĩ.
- Không tạo bảng/migration mới, không thay đổi model hoặc DB hiện tại, không tạo Use Case.

## File đã sửa và lý do

| File | Lý do |
|---|---|
| app/Http/Controllers/Admin/ProductController.php | Validation giá/stock theo SQL; kiểm tra upload thất bại; giữ ảnh cũ tới khi lưu thành công; dọn file mới nếu lưu lỗi; xóa bản ghi trước khi dọn ảnh |
| resources/views/admin/products/create.blade.php | id/for, step giá 0.01, giới hạn số, invalid-feedback |
| resources/views/admin/products/edit.blade.php | Như form create, thêm alt ảnh hiện tại |
| resources/views/admin/categories/create.blade.php | Liên kết label/input và required/maxlength |
| resources/views/admin/categories/edit.blade.php | Liên kết label/input và required/maxlength |
| resources/views/admin/products/index.blade.php | Bỏ thông báo success trùng layout chung |
| resources/views/admin/categories/index.blade.php | Bỏ thông báo success trùng layout chung |
| docs/ERD.md, docs/ERD.mmd, docs/ERD.svg | Cập nhật ERD và nguồn đối chiếu đầy đủ |
| docs/CRUD_MATRIX.md, docs/CRUD.mmd | Thêm ma trận quyền và sơ đồ luồng CRUD |
| tests/Feature/GiaHuyCrudTest.php | Đồng bộ assertion redirect đã có ở PR #17 |
| tests/Feature/GiaHuyPhaseTwoTest.php | Đồng bộ assertion redirect đã có ở PR #17 |
| tests/Feature/GiaHuyImageSafetyTest.php | Regression upload lỗi, lưu DB lỗi, dọn file mới, giới hạn số |
| docs/GD2_GIA_HUY_CHECKLIST.md | Checklist, file thay đổi, bằng chứng kiểm tra và việc còn lại |

## Kết quả kiểm thử

- php artisan test: 18 tests passed, 139 assertions, SQLite memory (phpunit.xml force sqlite/:memory:).
- Test Home: rỗng, active/inactive, hết hàng, form cart thật.
- Test dashboard: guest/customer bị chặn, Admin xem được, số đếm và tổng completed đúng.
- Test CRUD: create/update/delete danh mục/sản phẩm, slug trùng/rỗng, form render, pagination, validation tên/mô tả/giá/stock/file, thay/xóa ảnh.
- Test ảnh lỗi: upload false không mất ảnh cũ; lỗi lưu DB không mất ảnh cũ và không để file mới mồ côi; lỗi tạo Product dọn upload.
- Test Seeder chạy hai lần giữ nguyên tài khoản tồn tại.
- Blade compile/cache thành công.
- Không chạy migrate:fresh, db:wipe hoặc Seeder trên DB ứng dụng. Test RefreshDatabase chỉ dùng SQLite memory.
- Đã kiểm tra Home qua Chrome/Playwright với MySQL: 1440x1000 và 390x844 không tràn ngang; menu mobile mở và aria-expanded=true.
- MySQL đã kết nối; schema khớp ERD. DB hiện có 0 tài khoản role admin nên chưa login Admin để thử upload thật qua HTTP; test Admin/upload trên SQLite riêng đã đạt.

## Đối chiếu yêu cầu giảng viên

| Yêu cầu | Bằng chứng |
|---|---|
| Laravel + MySQL | composer Laravel 10, config/.env dùng mysql; kết nối local và Home đọc DB thành công |
| Eloquent ORM | Model Product/Category/Order/OrderItem/User; FK và quan hệ theo ERD |
| Migration + Seeder | Migration đã đọc; fresh schema + Seeder kiểm thử trên SQLite tách biệt |
| CRUD hai bảng có quan hệ | Category/Product đầy đủ qua middleware auth + admin; Product.category_id là FK |
| Blade + Bootstrap 5 | Shared/admin layouts, Home/Admin views và bootstrap@5.3.2 |
| Validation | CRUD và test dữ liệu không hợp lệ đạt |
| ERD và mô tả chức năng | ERD.md/ERD.mmd/ERD.svg, CRUD_MATRIX.md/CRUD.mmd |
| Use Case | Không yêu cầu, không tạo |

## Trước GĐ3

1. Đã đối chiếu schema MySQL. Cần tài khoản Admin được user xác định để kiểm thử giao diện Admin/upload qua HTTP; không tự tạo hay cấp quyền tài khoản.
2. Home desktop/mobile và storage link đã kiểm tra. Upload thật qua browser chờ có tài khoản Admin phù hợp.
3. PR #16 cần nhóm chốt chuyển trạng thái và hoàn kho đúng một lần trước tích hợp. payment_method chưa thuộc schema develop hiện tại.
4. PR #17 đã có cùng hai assertion redirect: khi tạo PR nhánh này cần tránh xử lý hai lần, không sửa lại middleware để làm test xanh.
5. Xóa Category hiện cascade Product theo FK; không thay đổi nghiệp vụ xóa trong đợt này. Dọn ảnh khi cascade Category còn là việc có thể cân nhắc riêng (không chặn việc schema/CRUD cơ bản).
6. Chưa commit/push/merge các thay đổi này; cần báo user trước theo quy định.

Kiểm tra cuối: lint 57 file PHP đạt; git diff --check đạt; SVG XML hợp lệ; public/storage là junction tới storage/app/public.

Xác minh khi XAMPP đã bật: users.role đã tồn tại; migration 2026_10_11_131014 vẫn Pending nhưng có guard hasColumn, chưa chạy vì schema không thiếu role. Không có thao tác nâng quyền, seed hay ghi dữ liệu nghiệp vụ. Ảnh: home-desktop.png và home-mobile.png.

# Checklist GĐ2 SneakerShop – 17/10/2026

Nhánh làm việc: feature/gia-huy-gd2-completion.
Nền tích hợp: develop d68e14c (PR #16 của Trọng đã merge).
Không merge PR hoặc thay đổi main/develop trong lượt sửa này.

## Bốn việc đã xử lý

| Việc | Kết quả |
|---|---|
| Trang đặt hàng thành công hiển thị sai trạng thái | Badge đọc orders.status, đủ pending/processing/completed/cancelled; không hiện hướng dẫn chuyển khoản khi đơn đã hủy/hoàn thành |
| Test cũ không khớp code | Hai redirect Customer về /; checkout gửi payment_method=cod; thêm test tích hợp COD/bank_transfer, quyền hủy, chuyển trạng thái và hoàn kho |
| Tích hợp phần Gia Huy và ERD/CRUD | Đã ghép develop mới vào feature; ERD thêm orders.payment_method; CRUD thêm xem/hủy đơn của Customer và cập nhật stock gián tiếp |
| DB local thiếu migration mới | Chạy hai migration pending: add_role có guard và add_payment_method; tất cả migration hiện Ran |

## Kiểm thử cuối

- php artisan test: 25 tests passed, 259 assertions, dùng SQLite memory bắt buộc theo phpunit.xml.
- PHP lint: 59 file đạt; Blade compile/cache đạt.
- Test CRUD Category/Product, validation, slug, phân trang, upload/thay/xóa ảnh và trường hợp lưu ảnh/DB lỗi đạt.
- Test trạng thái đơn: pending → processing/cancelled; processing → completed/cancelled; completed/cancelled không mở lại.
- Hủy bởi Admin hoặc đúng Customer hoàn stock một lần; hủy lặp/khác chủ bị từ chối.
- Checkout thiếu stock không tạo Order/OrderItem và giữ giỏ.
- Trang success đọc trạng thái mới nhất; thông báo success hiển thị đúng một lần sau tích hợp layout.
- MySQL ban_giay_the_thao: 9 bảng / 69 cột, 4 FK và quy tắc xóa khớp ERD.
- Số dòng trước/sau migrate không đổi: users=1, categories=2, products=0, orders=0, order_items=0.
- Không chạy migrate:fresh, db:wipe, Seeder trên DB ứng dụng; không tạo/nâng quyền tài khoản.
- Home desktop 1440 và mobile 390 đã kiểm tra trước đó; ảnh home-desktop.png và home-mobile.png.
- DB hiện chưa có Admin và sản phẩm thật: E2E MySQL qua UI Admin/upload vẫn cần dữ liệu/tài khoản do user xác định; không coi test SQLite là test concurrency MySQL.

## File GĐ2 Gia Huy

- app/Http/Controllers/Admin/ProductController.php: giới hạn giá/stock theo SQL; giữ ảnh cũ tới khi lưu thành công, dọn file mới khi DB lỗi.
- resources/views/admin/categories/{create,edit,index}.blade.php: label/input và bỏ flash trùng.
- resources/views/admin/products/{create,edit,index}.blade.php: label/input, step giá, lỗi form, alt ảnh và bỏ flash trùng.
- HomeController/DashboardController đã rà, giữ nguyên nghiệp vụ.
- docs/ERD.md, ERD.mmd, ERD.svg: đủ bảng/cột/PK/FK/unique/nullable/default theo schema.
- docs/CRUD_MATRIX.md, CRUD.mmd: quyền và luồng thao tác dữ liệu thực tế, không phải Use Case.
- tests/Feature/GiaHuyImageSafetyTest.php: tình huống upload/DB lỗi và giới hạn kiểu số.
- tests/Feature/GiaHuyCrudTest.php, GiaHuyPhaseTwoTest.php: đồng bộ auth/checkout hiện tại.

## File bổ sung để chốt tích hợp

- resources/views/checkout/success.blade.php: trạng thái động và hướng dẫn chuyển khoản phù hợp trạng thái.
- resources/views/layouts/app.blade.php: khôi phục success flash dùng chung.
- resources/views/cart/index.blade.php, store/index.blade.php, admin/orders/index.blade.php: bỏ success flash trùng layout.
- tests/Feature/Gd2IntegrationTest.php: 7 kiểm tra hồi quy cho luồng mới.
- Các controller auth/cart/checkout/order từ PR #16 được giữ nguyên; không viết lại phần thành viên khác.

## Trước GĐ3

1. Review PR tích hợp từ feature/gia-huy-gd2-completion về develop; chỉ merge khi user đồng ý.
2. PR #17 có cùng hai assertion đã nằm trong nhánh này; không cần áp dụng lại hai lần.
3. Kiểm thử E2E MySQL bằng tài khoản Admin/sản phẩm/ảnh thật và test đồng thời khi cần.
4. Xóa Category vẫn cascade Product theo FK hiện có; không đổi quy tắc xóa trong đợt này.
5. Chưa yêu cầu thanh toán online tự động; bank_transfer là lựa chọn/hướng dẫn, không xác nhận tiền tự động.
6. Không tạo Use Case. Slide/báo cáo môn học chờ user yêu cầu.

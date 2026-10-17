# SneakerShop – ERD và thiết kế dữ liệu

## Phạm vi xác minh ngày 17/10/2026

Nền code: develop c566220b0e579bc4b9e54eecfa00b747fa5150c9.
Đã đọc toàn bộ migration, model và routes đang tích hợp. MySQL cấu hình là ban_giay_the_thao;
đã kết nối MySQL và đối chiếu information_schema: đủ 9 bảng, 68 cột; kiểu cơ bản, nullable, PK/UK và 4 FK/các quy tắc xóa khớp sơ đồ. Không thay đổi schema hay dữ liệu.

Nguồn chỉnh sửa đầy đủ: [ERD.mmd](ERD.mmd). Hình SVG được tạo lại từ cùng dữ liệu: [ERD.svg](ERD.svg).
PK: khóa chính; FK: khóa ngoại SQL; UK: chỉ mục unique trong DB.
Chú thích nullable/default/độ dài nằm trên từng thuộc tính.

## Khóa ngoại SQL

| Cha → con | Null ở FK | Khi xóa cha |
|---|---|---|
| categories.id → products.category_id | Không | CASCADE |
| users.id → orders.user_id | Có | SET NULL |
| orders.id → order_items.order_id | Không | CASCADE |
| products.id → order_items.product_id | Có | SET NULL |

- Model thực tế: Product::category(), Order::items(), Order::user(), OrderItem::order(), OrderItem::product().
- Category chưa khai báo products(); User chưa khai báo orders(). Quan hệ SQL vẫn có từ migration, không tự thêm model method trong đợt này.
- products.name được kiểm tra unique tại form, nhưng không có unique index SQL; ERD không đánh dấu UK.
- orders.status, users.role là varchar, không phải SQL ENUM; giới hạn role/status nằm trong controller/middleware.
- orders → order_items được vẽ 0..N vì DB không bắt buộc đơn phải có item. Checkout hợp lệ luôn tạo ít nhất một item.
- order_items lưu tên và giá tại lúc đặt; product_id có thể NULL nếu sản phẩm bị xóa.
- created_at/updated_at do timestamps() tạo đều nullable.
- Không có bảng carts: giỏ hàng lưu session. Không có bảng roles hoặc payments trong develop.

## Các bảng hệ thống

- personal_access_tokens: tokenable_type + tokenable_id là quan hệ đa hình của Sanctum (User dùng HasApiTokens), có index nhưng không có SQL FK. Đường nét đứt trong ERD chỉ là liên kết logic; không giới hạn tokenable chỉ được là User ở mức DB.
- password_reset_tokens.email không có SQL FK sang users.email. Có cấu trúc bảng nhưng chưa có UI quên mật khẩu trong routes hiện tại.
- failed_jobs: bảng hàng đợi lỗi; không có FK nghiệp vụ.
- migrations: bảng do Laravel DatabaseMigrationRepository tạo, không phải một model nghiệp vụ hay migration create_* riêng của nhóm.

## Nguồn migration

- 2014_10_12_000000_create_users_table.php
- 2014_10_12_100000_create_password_reset_tokens_table.php
- 2019_08_19_000000_create_failed_jobs_table.php
- 2019_12_14_000001_create_personal_access_tokens_table.php
- 2026_09_16_130909_create_categories_table.php
- 2026_09_16_130928_create_products_table.php
- 2026_09_18_133810_create_orders_table.php
- 2026_09_18_133815_create_order_items_table.php
- Hai migration bổ sung role ngày 11/10 đều kiểm tra hasColumn; không tạo bảng hay quan hệ mới.

## Chức năng và dữ liệu

Home dùng Product + category, lấy tối đa 8 sản phẩm active. Store hiển thị sản phẩm active còn kho.
Admin CRUD Category/Product, upload ảnh, tìm sản phẩm và phân trang.
Dashboard đếm Product/Category/Order, đếm pending và cộng total_amount của completed.
Customer đặt hàng qua transaction: kiểm tra giá/stock từ DB, tạo Order/OrderItem, trừ stock, sau đó mới xóa giỏ.
Admin sửa trạng thái Order; chưa có giới hạn chuyển trạng thái và hoàn kho trong develop hiện tại.

Bảng quyền CRUD và sơ đồ luồng: [CRUD_MATRIX.md](CRUD_MATRIX.md), [CRUD.mmd](CRUD.mmd).
Không tạo Use Case.

## Việc chưa tích hợp

PR #16 đang đề xuất payment_method trên orders, lịch sử/hủy đơn và hoàn kho.
Chưa merge nên payment_method không nằm trong ERD hiện hành. Khi tích hợp phải cập nhật lại sơ đồ và kiểm tra luồng trạng thái trước GĐ3.

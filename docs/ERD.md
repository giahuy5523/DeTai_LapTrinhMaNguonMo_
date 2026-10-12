# SneakerShop – Thiết kế cơ sở dữ liệu

ERD đối chiếu 8 migration trên develop f2929e1. Database: ban_giay_the_thao.
Mọi bảng nghiệp vụ có created_at, updated_at.

~~~mermaid
erDiagram
    CATEGORIES ||--o{ PRODUCTS : category_id
    USERS |o--o{ ORDERS : "user_id nullable"
    ORDERS ||--o{ ORDER_ITEMS : order_id
    PRODUCTS |o--o{ ORDER_ITEMS : "product_id nullable"
    CATEGORIES {
        bigint id PK
        varchar name UK
        varchar slug UK
        text description "nullable"
    }
    PRODUCTS {
        bigint id PK
        bigint category_id FK
        varchar name
        varchar slug UK
        decimal price "15,2"
        int quantity "default 0"
        varchar image "nullable"
        text description "nullable"
        boolean is_active "default true"
    }
    USERS {
        bigint id PK
        varchar name
        varchar email UK
        timestamp email_verified_at "nullable"
        varchar password
        varchar role "default customer"
        varchar remember_token "nullable"
    }
    ORDERS {
        bigint id PK
        bigint user_id FK "nullable"
        varchar customer_name
        varchar phone "20"
        text address
        text note "nullable"
        decimal total_amount "15,2"
        varchar status "default pending"
    }
    ORDER_ITEMS {
        bigint id PK
        bigint order_id FK
        bigint product_id FK "nullable"
        varchar product_name
        int quantity "unsigned"
        decimal unit_price "15,2"
        decimal subtotal "15,2"
    }
~~~

## Quan hệ và ràng buộc

| Quan hệ | Khi xóa bản ghi cha |
|---|---|
| categories → products | CASCADE: xóa sản phẩm thuộc danh mục |
| users → orders | SET NULL: giữ đơn, user_id thành NULL |
| orders → order_items | CASCADE: xóa chi tiết đơn |
| products → order_items | SET NULL: giữ chi tiết, product_id thành NULL |

- Mỗi sản phẩm thuộc đúng một danh mục; một danh mục có nhiều sản phẩm.
- Một user có nhiều đơn; đơn có thể không còn user liên kết.
- Một đơn có nhiều chi tiết; mỗi chi tiết lưu tên và giá sản phẩm lúc đặt.
- subtotal = quantity × unit_price; total_amount là tổng subtotal.
- Checkout đọc lại giá và stock trong transaction, dùng lockForUpdate.
- role: admin/customer. status: pending/processing/completed/cancelled được validation trong OrderController.
- Giỏ hàng lưu session, không có bảng carts.
- Bảng hỗ trợ Laravel: migrations, password_reset_tokens, failed_jobs, personal_access_tokens.
- Xóa danh mục hiện xóa luôn sản phẩm: cần cân nhắc trước khi thao tác dữ liệu thật.
- Không yêu cầu Use Case theo hướng dẫn trực tiếp của giảng viên.

## Chức năng

Khách xem home và danh sách sản phẩm; khách hàng thêm/cập nhật/xóa giỏ và đặt hàng.
Admin quản lý danh mục, sản phẩm, ảnh upload, tìm kiếm/phân trang, trạng thái đơn.
Dashboard hiển thị số sản phẩm, danh mục, đơn, đơn chờ xử lý và tổng giá trị đơn completed.

## Lưu ý tích hợp

Branch auth có migration 2026_10_11_131014_add_role_to_users_table.php,
trong khi create_users_table trên develop đã khai báo role.
Cần giải quyết trùng cột trước khi tích hợp branch auth; ERD này mô tả schema develop.

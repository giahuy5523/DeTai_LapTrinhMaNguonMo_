# SneakerShop – Ma trận CRUD thực tế

Đối chiếu develop c566220 ngày 17/10/2026 và các sửa Home/Admin trên nhánh feature/gia-huy-gd2-completion.
C = tạo; R = xem; U = sửa; D = xóa. “Gián tiếp” nghĩa là controller thao tác dữ liệu,
không có màn hình CRUD riêng cho người dùng. Không mô tả chức năng chưa tồn tại như đã hoàn thành.

| Dữ liệu/chức năng | Khách chưa đăng nhập | Customer | Admin | Điểm vào / giới hạn thực tế |
|---|---|---|---|---|
| users – đăng ký | C, role luôn customer | C qua route register chưa có guest guard | C qua route register chưa có guest guard | POST /register; chưa có trang quản lý/sửa/xóa User |
| users – thông tin tài khoản | Không | R chính mình | R chính mình | Navbar tên user; GET /api/user cần auth:sanctum; không phải danh sách User |
| categories | R gián tiếp tên danh mục sản phẩm trên Home | R gián tiếp như khách | C/R/U/D | admin.categories.*; auth + admin |
| products | R sản phẩm public | R sản phẩm public | C/R/U/D và R public | Home/Store; admin.products.*; không có route show riêng |
| products.quantity | Không | U gián tiếp lúc checkout trừ kho | U qua form Product | Không cho Customer gửi trực tiếp stock vào CRUD Admin |
| giỏ hàng session | Không, chuyển login | C/R/U/D | Không, CustomerMiddleware từ chối | cart.add/index/update/remove; không có bảng carts |
| orders | Không | C khi checkout; R giới hạn mã đơn vừa đặt trên trang success | R danh sách, U status | Không có sửa/xóa đơn của Customer hay lịch sử đơn trong develop |
| order_items | Không | C gián tiếp khi checkout; chưa có trang đọc lịch sử item từ DB | Chưa có UI C/R/U/D riêng | Nội dung giỏ/checkout trước đặt là session; không coi là trang chi tiết OrderItem |
| products khi xóa Category | Không | Không | D gián tiếp do FK CASCADE | Quy tắc DB hiện có, xóa danh mục sẽ xóa sản phẩm thuộc danh mục |
| order_items khi xóa Product | Không | Không | U gián tiếp: product_id thành NULL | Giữ product_name/unit_price/subtotal lịch sử |
| order_items khi xóa Order | Không | Không | Không có route xóa Order | FK CASCADE là quy tắc DB, không phải chức năng UI đã cung cấp |
| bảng hệ thống | Không | Không có UI CRUD | Không có UI CRUD | migrations/password_reset_tokens/failed_jobs/personal_access_tokens do framework dùng |

## Category và Product: các thao tác cụ thể

| CRUD | Category | Product | Quyền |
|---|---|---|---|
| C | create form + POST admin/categories | create form + POST admin/products, multipart upload | Admin |
| R | GET admin/categories, paginate(10) | GET admin/products, keyword, paginate(5) | Admin |
| U | edit form + PUT/PATCH admin/categories/{category} | edit form + PUT/PATCH admin/products/{product}; thay ảnh sau khi lưu thành công | Admin |
| D | DELETE admin/categories/{category} | DELETE admin/products/{product}; dọn ảnh sau khi xóa bản ghi | Admin |

- Navigation chỉ hiển thị liên kết phù hợp; quyền server vẫn được middleware kiểm tra.
- Validation: tên, unique name theo form, slug không rỗng/unique, FK danh mục, giá không âm/khớp decimal(15,2), stock nguyên không âm/khớp int, mô tả chuỗi, ảnh jpeg/png/jpg/webp tối đa 2MB.
- Schema không có soft delete; thao tác D xóa thật. Không thực hiện thao tác xóa trên DB hiện tại trong lượt rà soát.
- PR #16 chưa tích hợp: không gán quyền hủy/xem lịch sử đơn cho Customer trong ma trận này.
- Auth routes chưa dùng guest middleware; tài liệu ghi đúng code, không thay đổi phần Tuấn.

Nguồn sơ đồ chỉnh sửa: [CRUD.mmd](CRUD.mmd). Sơ đồ này mô tả luồng thao tác dữ liệu, không phải UML Use Case.

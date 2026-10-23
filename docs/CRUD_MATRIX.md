# SneakerShop – Ma trận CRUD thực tế

Đối chiếu develop d68e14c ngày 17/10/2026 và các sửa Home/Admin trên nhánh feature/gia-huy-gd2-completion.
C = tạo; R = xem; U = sửa; D = xóa. “Gián tiếp” nghĩa là controller thao tác dữ liệu,
không có màn hình CRUD riêng cho người dùng. Không mô tả chức năng chưa tồn tại như đã hoàn thành.

| Dữ liệu/chức năng | Khách chưa đăng nhập | Customer | Admin | Điểm vào / giới hạn thực tế |
|---|---|---|---|---|
| users – đăng ký | C, role luôn customer | Không, guest guard chuyển về Home | Không, guest guard chuyển về Home | POST /register; chưa có trang quản lý/sửa/xóa User |
| users – thông tin tài khoản | Không | R chính mình | R chính mình | Navbar tên user; GET /api/user cần auth:sanctum; không phải danh sách User |
| categories | R gián tiếp tên danh mục sản phẩm trên Home | R gián tiếp như khách | C/R/U/D | admin.categories.*; auth + admin |
| products | R sản phẩm public | R sản phẩm public | C/R/U/D và R public | Home/Store; admin.products.*; không có route show riêng |
| products.quantity | Không | U gián tiếp khi checkout trừ kho/hủy đơn hoàn kho | U qua form Product hoặc hủy đơn hoàn kho | Không cho Customer gửi trực tiếp stock vào CRUD Admin |
| giỏ hàng session | Không, chuyển login | C/R/U/D | Không, CustomerMiddleware từ chối | cart.add/index/update/remove; không có bảng carts |
| orders | Không | C checkout; R chi tiết đơn mới nhất ở success và 10 đơn gần nhất chưa hủy ở cart; U hủy đơn pending của mình | R danh sách, U status theo bước hợp lệ | Customer không xóa đơn; cancelled/completed không đổi tiếp |
| order_items | Không | C gián tiếp khi checkout; R items trong các đơn gần đây của mình | Chưa có UI C/R/U/D riêng | Cart truy vấn Order::with(items) giới hạn theo user_id |
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
- PR #16 đã tích hợp: PATCH don-hang/{id}/huy cần auth + customer, tìm đơn theo user_id và pending; hủy giữ đơn trong DB và hoàn kho một lần.
- Cập nhật GĐ3 23/10/2026: login/register dùng guest; logout dùng auth. Request cùng session được khóa bằng file cache trước khi nạp/lưu session.
- Form sửa Product có phiên bản dữ liệu ký HMAC; từ chối form cũ và giữ token cũ khi validation lỗi. Stock phải chừa chỗ cho các đơn pending/processing có thể hoàn hàng.
- Mô tả bị giới hạn 65.535 byte UTF-8, FK danh mục phải là một integer. Giá/tổng tính bằng số nguyên theo đơn vị nhỏ nhất và hiển thị đúng phần lẻ.
- Checkout phát hành xác nhận riêng theo từng lần mở trang; thay đổi giỏ hoặc giá làm xác nhận cũ không còn hợp lệ. Khóa Product theo ID tăng dần, transaction retry tối đa 3 lần.
- Xóa Category thu thập/dọn ảnh sản phẩm bị cascade; chỉ xóa file không còn Product nào tham chiếu.

Nguồn sơ đồ chỉnh sửa: [CRUD.mmd](CRUD.mmd). Sơ đồ này mô tả luồng thao tác dữ liệu, không phải UML Use Case.

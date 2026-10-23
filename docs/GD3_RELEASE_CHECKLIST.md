# GĐ3 — Bàn giao và triển khai

## Bản bàn giao

Đã merge [PR #19](https://github.com/giahuy5523/DeTai_LapTrinhMaNguonMo_/pull/19) vào develop sau khi Gia Huy đồng ý.
Xem [trạng thái bàn giao, CI và backup](GD3_HANDOFF_STATUS.md).

Nhánh `codex/gia-huy-gd3` → PR vào `develop`. Không phát hành trực tiếp lên `main`.
Kết quả kiểm thử và phạm vi: [QA hiện hành](GD3_FINAL_QA.md).
Workflow GitHub Actions chạy PHP 8.3, Composer install từ lock, PHPUnit SQLite,
compile Blade và audit dependency cho PR develop/main và push develop.

## Yêu cầu môi trường

- PHP 8.3+ x64, extensions trong README; không dùng PHP 8.1 của XAMPP cũ.
- Không đổi APP_KEY của ứng dụng đã có dữ liệu.
- Đảm bảo public/storage liên kết tới storage/app/public, storage/bootstrap/cache ghi được.
- Khởi chạy PHP tại đúng public document root; không phục vụ toàn bộ thư mục repository.
- Giữ SESSION_DRIVER=file và file lock store trên cùng máy khi dùng cấu hình hiện tại.

## Migration ứng dụng — đã thực thi

Database `ban_giay_the_thao` đã được Gia Huy cho phép triển khai migration ngày 23/10.
Migration đã chạy là `2026_10_23_001000_add_checkout_token_to_orders_table.php`:
thêm `orders.checkout_token` nullable VARCHAR(64), UNIQUE, không sửa đơn hiện có.
Đây là thay đổi schema từ 69 → 70 cột; số bảng/khóa ngoại giữ nguyên.

Checklist đã dùng sau khi có xác nhận (giữ để tái kiểm tra, không chạy seed/fresh/wipe):

1. Xác minh database thực tế và migrate:status, runtime PHP và code đã review.
2. Sao lưu đầy đủ bằng mysqldump (single transaction), lưu ngoài Git, kiểm tra backup
   có schema/bản ghi và SHA256. Không hiển thị/đưa dữ liệu tài khoản vào báo cáo.
   Có thể chạy `php scripts/prepare-release.php` để tạo backup và preview; script không áp dụng migration.
3. Kiểm tra SQL bằng migrate --pretend --path đúng migration mới.
4. Chạy migrate --force --path đúng migration mới; không chạy seed/fresh/wipe.
5. Xác minh nullable/unique, 70 cột, counts nghiệp vụ và tài khoản không đổi.
6. Nghiệm thu Home/Login/public assets; không tự tạo Admin hoặc đổi mật khẩu/role.

Không tự rollback schema sau khi có đơn dùng token: bỏ unique/cột làm mất chốt
idempotency. Nếu cần rollback, dừng checkout và review dữ liệu/quy trình trước.

## Demo đã được phép

Demo đầy đủ hiện dùng `sneakershop_gd3_test`, tài khoản demo đã chuẩn bị và CSRF bật
(APP_ENV=local khi phục vụ HTTP). Không chuyển fixture test vào DB ứng dụng.

Luồng nghiệm thu: Home → Login Customer → thêm/sửa giỏ → Checkout COD hoặc chuyển khoản
demo → xem lịch sử → Admin processing/cancel → xác nhận stock hoàn đúng. Thử thêm
quantity vượt kho, form cũ và ID có số 0 đầu. Xem screenshot và script browser trong repo.

Mật khẩu demo chỉ lưu cục bộ trong storage ignored. Không ghi vào PR/Git/slide.
Không chạy seeder mặc định trên DB ứng dụng vì có tài khoản Admin mẫu.

## Cổng bàn giao cuối

- PR đúng base develop, diff đã review, không có credentials/runtime/database dump.
- GitHub Actions đạt hoặc báo rõ giới hạn nếu dịch vụ CI không chạy được.
- Merge PR và triển khai schema ứng dụng là các bước chốt riêng cần xác nhận theo
  quy tắc trưởng nhóm đã đặt; đã được xác nhận và hoàn tất cho PR #19/migration này.
  Không tự merge main.
- Chưa làm slide/Word; không tính Use Case là thiếu theo yêu cầu gốc.

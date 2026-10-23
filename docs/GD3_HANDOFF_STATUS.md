# Gia Huy — Trạng thái bàn giao

Ngày 23/10/2026 (Asia/Saigon).

## Đã hoàn thành

- Commit bản code/QA: `c0777e5d8b931bec98fe9300b4b468affb0eceb6` trên `codex/gia-huy-gd3`.
- Đã push lên origin; [PR #19](https://github.com/giahuy5523/DeTai_LapTrinhMaNguonMo_/pull/19)
  đúng base `develop`, không phải `main`.
- [CI đầu tiên](https://github.com/giahuy5523/DeTai_LapTrinhMaNguonMo_/actions/runs/39972437696)
  trên code commit thành công: install lock, platform/Composer, PHPUnit, Blade, dependency audit.
- Local: 131 tests / 2.970 assertions đạt, gồm vendor cài sạch; SQL/HTTP/browser theo
  [báo cáo QA](GD3_FINAL_QA.md).
- Quét 121 file trong code commit: không có .env, credential demo, cookie, runtime,
  vendor hoặc database dump. Credentials private và backup thuộc storage ignored.
- Chuẩn bị đầy đủ [checklist triển khai](GD3_RELEASE_CHECKLIST.md) và
  `scripts/prepare-release.php` (chỉ sao lưu + pretend, không áp dụng migration).
- Demo tách biệt đã nghiệm thu ở `http://127.0.0.1:8023`, DB `sneakershop_gd3_test`.

## Backup và migration chờ triển khai

Đã dùng mysqldump single transaction để sao lưu `ban_giay_the_thao`, gồm 9 bảng.
Kiểm tra dump đầy đủ 9 CREATE TABLE, có dấu kết thúc và SHA256; chưa thử restore backup.

- File cục bộ ignored: `storage/app/backups/before-gd3-20261023-144816-c7ba76.sql`.
- Dung lượng: 11.555 byte.
- SHA256: `9d3482b1a9d12f331022f38c799a3f3d67d43a06961cb76a65ba390dcc92a49e`.
- Manifest và SQL preview ở cùng thư mục; không đưa nội dung dump lên Git.

SQL preview duy nhất của migration pending:

```sql
alter table `orders` add `checkout_token` varchar(64) null;
alter table `orders` add unique `orders_checkout_token_unique`(`checkout_token`);
```

Chưa chạy ALTER thật. Counts ứng dụng vẫn users=1, categories=2, products=0,
orders=0, order_items=0. Không tạo/nâng quyền Admin, đổi mật khẩu hoặc seed ứng dụng.

## Hai bước chốt cần trưởng nhóm xác nhận

1. Merge PR #19 vào **develop**, sau khi check trên head cuối cùng đạt và base không đổi.
2. Cho phép ghi schema `ban_giay_the_thao`: chạy đúng migration checkout_token sau
   backup, rồi xác minh unique/nullable, 70 cột, counts và tài khoản không đổi.

Chưa merge main. Quy tắc gốc yêu cầu đồng ý trước merge và DB ứng dụng chỉ đọc trong
đợt kiểm thử; vì vậy bản bàn giao được chuẩn bị đầy đủ để duyệt hai bước trên.
Không còn thay đổi code cần đợi trước khi duyệt, trừ khi CI của commit tiếp theo báo lỗi.

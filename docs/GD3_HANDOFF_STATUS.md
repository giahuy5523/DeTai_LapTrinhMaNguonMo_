# Gia Huy — Trạng thái bàn giao

Ngày 23/10/2026 (Asia/Saigon).

**Đã chốt bàn giao sau khi Gia Huy đồng ý merge và triển khai trong chat.**
PR #19 đã merge vào develop; migration ứng dụng đã hoàn tất và nghiệm thu HTTP đạt.

## Đã hoàn thành

- Commit bản code/QA: `c0777e5d8b931bec98fe9300b4b468affb0eceb6` trên `codex/gia-huy-gd3`.
- [PR #19](https://github.com/giahuy5523/DeTai_LapTrinhMaNguonMo_/pull/19) đã merge vào `develop`.
  Merge commit: `9f1a3a0d527a0d7b34991f5d3d15b59675f08178`; không thay đổi `main`.
- [CI đầu tiên](https://github.com/giahuy5523/DeTai_LapTrinhMaNguonMo_/actions/runs/39972437696)
  trên code commit thành công: install lock, platform/Composer, PHPUnit, Blade, dependency audit.
- Local: 131 tests / 2.970 assertions đạt, gồm vendor cài sạch; SQL/HTTP/browser theo
  [báo cáo QA](GD3_FINAL_QA.md).
- Quét 121 file trong code commit: không có .env, credential demo, cookie, runtime,
  vendor hoặc database dump. Credentials private và backup thuộc storage ignored.
- Chuẩn bị đầy đủ [checklist triển khai](GD3_RELEASE_CHECKLIST.md) và
  `scripts/prepare-release.php` (chỉ sao lưu + pretend, không áp dụng migration).
- Demo tách biệt đã nghiệm thu ở `http://127.0.0.1:8023`, DB `sneakershop_gd3_test`.

## Backup và migration đã triển khai

Đã dùng mysqldump single transaction để sao lưu `ban_giay_the_thao`, gồm 9 bảng.
Kiểm tra dump đầy đủ 9 CREATE TABLE, có dấu kết thúc và SHA256; chưa thử restore backup.

- Backup mới ngay trước triển khai, ignored: `storage/app/backups/before-gd3-20261023-145758-e7db8c.sql`.
- Dung lượng: 11.555 byte.
- SHA256: `fc9b17dd866480286c784a57dc7b2b3e4a03009844b1814cfb63f5e9c23a3db5`.
- Manifest và SQL preview ở cùng thư mục; không đưa nội dung dump lên Git.

Migration đã thực hiện đúng hai thay đổi:

```sql
alter table `orders` add `checkout_token` varchar(64) null;
alter table `orders` add unique `orders_checkout_token_unique`(`checkout_token`);
```

`migrate:status` xác nhận Ran, batch 4. Schema ứng dụng **9 bảng / 70 cột**;
checkout_token VARCHAR(64), nullable, unique index hiện diện.
So sánh SHA256 của toàn bộ bản ghi nghiệp vụ trước/sau (bỏ cột mới khi so Order):
**không thay đổi dữ liệu**, gồm password/role/token tài khoản.
Counts vẫn users=1, categories=2, products=0, orders=0, order_items=0.
Không tạo/nâng quyền Admin, đổi mật khẩu hoặc seed ứng dụng.

## Nghiệm thu sau triển khai

Server PHP 8.3 tại `http://127.0.0.1:8024` trỏ DB ứng dụng, cookie riêng
`sneakershop_app_session` để không dùng lẫn phiên demo ở port 8023.
Home, Store, Login, Register và CSS/JS Bootstrap đều HTTP 200.
DB ứng dụng không có Product/Admin demo nên luồng mua/Admin đầy đủ tiếp tục được
nghiệm thu trên DB demo đã được cho phép; không tự thêm tài khoản hay dữ liệu vào app.

Biên bản máy đọc được ở `storage/app/gd3-deployment-result.json` (ignored), có kết quả
backup/hash/schema/unchanged=true. Đây là ghi nhận triển khai local đã đồng ý,
không phải phát hành lên main hoặc một hosting bên ngoài.

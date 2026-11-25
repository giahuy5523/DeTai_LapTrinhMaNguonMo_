# SneakerShop isolated demo

The demo database is a dedicated MariaDB instance, separate from the local
SQLite preview and any application database:

- MariaDB data directory: `storage/app/gd3-mariadb-demo/data`
- Loopback port: `33430`
- Schema: `sneakershop_gd3_test`
- App URL: `http://127.0.0.1:8025`
- Local-only accounts: `admin@demo.test`, `customer1@demo.test`,
  `customer2@demo.test`
- Shared demo password and database root password are stored under
  `storage/app/private/`; those files are ignored by Git.

## Start the demo

Start MariaDB in a hidden PowerShell process:

```powershell
$gd3MysqlIni = (Resolve-Path 'storage/app/gd3-mariadb-demo/my.ini').Path
Start-Process -FilePath 'C:/xampp/mysql/bin/mysqld.exe' `
  -ArgumentList "--defaults-file=$gd3MysqlIni", '--console' -WindowStyle Hidden
```

Create/confirm the dedicated schema on that instance:

```powershell
$gd3RootPassword = (Get-Content 'storage/app/private/gd3-mariadb-root-password.txt' -Raw).Trim()
$env:MYSQL_PWD = $gd3RootPassword
try {
    & C:/xampp/mysql/bin/mariadb.exe --protocol=tcp --host=127.0.0.1 --port=33430 --user=root --execute="CREATE DATABASE IF NOT EXISTS sneakershop_gd3_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
} finally {
    Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    Remove-Variable gd3RootPassword -ErrorAction SilentlyContinue
}
```

Run migrations and the idempotent demo seeder against the isolated schema. Read
the shared demo password from the ignored local credential file; do not paste it
into a document or source file.

```powershell
$gd3RootPassword = (Get-Content 'storage/app/private/gd3-mariadb-root-password.txt' -Raw).Trim()
$gd3CredentialLine = Get-Content 'storage/app/private/demo-credentials.txt' | Where-Object { $_ -like 'Password shared:*' }
$env:GD3_DEMO_PASSWORD = $gd3CredentialLine.Substring('Password shared: '.Length)
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '33430'
$env:DB_DATABASE = 'sneakershop_gd3_test'
$env:DB_USERNAME = 'root'
$env:DB_PASSWORD = $gd3RootPassword
try {
    .\scripts/php.ps1 artisan migrate --force
    .\scripts/php.ps1 artisan db:seed --class=Gd3DemoSeeder --force
} finally {
    Remove-Item Env:GD3_DEMO_PASSWORD, Env:APP_ENV, Env:DB_CONNECTION, Env:DB_HOST, Env:DB_PORT, Env:DB_DATABASE, Env:DB_USERNAME, Env:DB_PASSWORD -ErrorAction SilentlyContinue
    Remove-Variable gd3RootPassword, gd3CredentialLine, gd3MysqlIni -ErrorAction SilentlyContinue
}
```

Serve the app with the demo DB in the same PowerShell terminal:

```powershell
$gd3RootPassword = (Get-Content 'storage/app/private/gd3-mariadb-root-password.txt' -Raw).Trim()
$env:APP_ENV = 'local'
$env:APP_URL = 'http://127.0.0.1:8025'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '33430'
$env:DB_DATABASE = 'sneakershop_gd3_test'
$env:DB_USERNAME = 'root'
$env:DB_PASSWORD = $gd3RootPassword
$env:SESSION_DRIVER = 'file'
$env:SESSION_COOKIE = 'sneakershop_gd3_demo_session'
.\scripts/php.ps1 artisan serve --host=127.0.0.1 --port=8025 --no-reload
```

## Demo checklist

| Step | Role and page | Action and expected result |
| --- | --- | --- |
| 1 | Guest · `/` | View the storefront, hero, and featured products (all bundled shoe photos load cleanly). |
| 2 | Guest · `/san-pham` | Search for `GD3 Sneaker`; page 2 keeps the search term and shows 13 available shoes. |
| 3 | Customer 1 · `/login` | Sign in, add `GD3 Sneaker 1` via AJAX, and see the cart badge & toast update. |
| 4 | Customer 1 · `/gio-hang` | Change quantity to 2; subtotal updates from server data (`2.420.000 đ`). |
| 5 | Customer 1 · `/checkout` | Place a normal COD order and confirm the order appears in purchase history. |
| 6 | Customer 2 · `/login` and `/checkout` | Sign in separately, place a second normal COD order, and confirm account histories stay separate. |
| 7 | Admin · `/login` | Sign in and open `/admin/vouchers`; review seed vouchers `DEMO10` (`0 / 1`) and `DEMO50K` (`0 / 20`). |
| 8 | Admin · `/admin/vouchers` | Create a percentage voucher without a cap (validation error), then create one with a cap (`REHEARSE15`). |
| 9 | Admin · `/admin/vouchers` | Try a duplicate code with different letter case (`ReHeArSe15`); it is rejected after normalization. |
| 10 | Admin · `/admin/vouchers` | Edit dates/status and limit, then confirm the list reflects the changes. |
| 11 | Admin · `/admin/users` | Open a Customer's details; see order count, completed-order total, voucher discount, and purchase history. |
| 12 | Guest/customer/admin · any page | Verify the role-specific navigation and that logout appears before the greeting. |
| 13 | Customer 1 · `/checkout` (QR + `DEMO10`) | Apply `demo10` (normalized to `DEMO10`, preview does not increment usage), scan ISO/IEC 18004 QR showing discounted demo payload, confirm simulated QR order; Admin shows `1 / 1` (`Hết lượt`). |
| 14 | Customer 2 & Admin · Voucher limits & deletion | Customer 2 is rejected on exhausted `DEMO10`, checks out with another voucher; Admin deletes the voucher and existing orders keep `voucher_code` & `discount_amount` snapshot. |
| 15 | Customer 1 & Customer 2 · Last unit & 5-min expiry | Account 1 holds the last unit (`quantity = 1`), Account 2 is blocked; after 5-min expiry, clear red notice + **Quay về giỏ hàng** button appear, late POST is rejected, and Account 2 claims the released unit. |
| 16 | Multi-process concurrency (`tests/manual/voucher-concurrency.php`) | Two concurrent PHP workers race on a `usage_limit = 1` voucher across 3 rounds: every round returns `['accepted', 'rejected']`, `used_count = 1`, stock conserved before & after cancellation. |
| 17 | SQL integrity audit (`tests/manual/data-integrity-audit.php`) | All 9 invariants (including `subtotal_amount - discount_amount = total_amount` and `used_count <= usage_limit`) return **0 violations**. |

The seeded products include 13 available shoes, one out-of-stock shoe, and one
inactive shoe. `DEMO10` is limited to one successful order; cancellations do not
restore its use. `DEMO50K` is a fixed-amount example. Checkout voucher
integration (`App\Support\VoucherService`, `POST /checkout/voucher`,
`DELETE /checkout/voucher`, `POST /checkout`) is now complete and verified end-to-end;
see `docs/VOUCHER_HANDOFF.md` and the latest rehearsal report in
`docs/DEMO_REHEARSAL_RESULT.md`. Database passwords and the shared demo login
password stay in ignored local files only.

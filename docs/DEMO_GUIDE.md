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
| 1 | Guest · `/` | View the storefront, hero, and featured products. |
| 2 | Guest · `/san-pham` | Search for `GD3 Sneaker`; page 2 keeps the search term. |
| 3 | Customer 1 · `/login` | Sign in, add `GD3 Sneaker 1`, and see the cart badge update. |
| 4 | Customer 1 · `/gio-hang` | Change quantity to 2; subtotal updates from server data. |
| 5 | Customer 1 · `/checkout` | Place a normal COD order and confirm the order appears in purchase history. |
| 6 | Customer 2 · `/login` and `/checkout` | Sign in separately, place a second normal COD order, and confirm account histories stay separate. |
| 7 | Admin · `/login` | Sign in and open `/admin/vouchers`; review seed vouchers `DEMO10` and `DEMO50K`. |
| 8 | Admin · `/admin/vouchers` | Create a percentage voucher without a cap (validation error), then create one with a cap. |
| 9 | Admin · `/admin/vouchers` | Try a duplicate code with different letter case; it is rejected after normalization. |
| 10 | Admin · `/admin/vouchers` | Edit dates/status and limit, then confirm the list reflects the changes. |
| 11 | Admin · `/admin/users` | Open a Customer's details; see order count, completed-order total, and purchase history. |
| 12 | Guest/customer/admin · any page | Verify the role-specific navigation and that logout appears before the greeting. |

The seeded products include 13 available shoes, one out-of-stock shoe, and one
inactive shoe. `DEMO10` is limited to one successful order; cancellations do not
restore its use. `DEMO50K` is a fixed-amount example. Checkout voucher
application is pending Trọng's service/API integration; until then the codes can
be inspected and managed in Admin but are not applied to orders. See
`docs/VOUCHER_HANDOFF.md`. Database passwords and the shared demo login password
stay in ignored local files only.

# Dashboard Monitor

Laravel 13 + Inertia 3 + Svelte 5, PostgreSQL 18, dan FrankenPHP (PHP 8.5).
Frontend memakai Vite 8 dan Tailwind CSS 4.

Konfigurasi default: aplikasi dan PostgreSQL berjalan langsung di PC Windows.
`composer run dev` menjalankan FrankenPHP, Vite, dan worker antrean bersama.
Docker tetap tersedia sebagai opsi.

## Jalankan project

PostgreSQL Windows harus aktif. Service di PC ini bernama `postgresql-x64-18`.
Jika berhenti, jalankan dari PowerShell sebagai Administrator:

```powershell
Start-Service postgresql-x64-18
```

Kemudian dari direktori project:

```powershell
composer run dev
```

Buka **http://localhost:8000**. Dependency, application key, build frontend,
user/database PostgreSQL lokal, dan migrasi sudah disiapkan di PC ini.
Tekan **Ctrl+C** untuk menghentikan server, Vite, dan worker.
Jika Windows menampilkan `Terminate batch job (Y/N)?`, ketik `Y` lalu Enter.

PHP 8.5 dan Composer sudah tersedia pada PATH di PC ini. Runtime FrankenPHP
Windows ada di `.tools/frankenphp` dan dikecualikan dari Git. Konfigurasi
server lokal menggunakan `Caddyfile`, dengan document root `public/`.

## Database lokal

| Pengaturan | Nilai |
| --- | --- |
| Host dari aplikasi lokal | `127.0.0.1:5432` |
| Host dari FrankenPHP Docker | `host.docker.internal:5432` |
| Host dari pgAdmin/DBeaver di PC | `127.0.0.1:5432` |
| Database development | `dashboard_monitor` |
| Database testing | `dashboard_monitor_testing` |
| User aplikasi | `dashboard` |
| Password development | `dashboard_local` |

User `dashboard` memiliki dua database project dan tidak diberi hak superuser.
Kredensial development dapat dilihat di `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=dashboard_monitor
DB_USERNAME=dashboard
DB_PASSWORD=dashboard_local
```

`APP_PORT` mengatur port aplikasi. Bila diganti, sesuaikan `APP_URL` juga.
Untuk database PC pada port lain, ubah `DB_PORT` di `.env`.
Setelah perubahan konfigurasi:

```powershell
php artisan config:clear
```

Kemudian mulai ulang `composer run dev`. Data PostgreSQL lokal disimpan di
instalasi PostgreSQL Windows. Menghentikan aplikasi tidak menghentikan
service PostgreSQL Windows.

## Password admin PostgreSQL

Password admin `postgres` sudah dipulihkan. Password baru disimpan sebagai
kredensial terenkripsi Windows di `.postgres-admin.credential.xml` dan
file tersebut dikecualikan dari Git. User aplikasi tetap `dashboard`.

Untuk melihat password admin di terminal lokal, dari direktori project:

```powershell
$postgresCredential = Import-Clixml .postgres-admin.credential.xml
$postgresCredential.GetNetworkCredential().Password
```

File tersebut hanya bisa didekripsi oleh akun Windows yang sama pada PC
ini. Simpan password dalam password manager sebelum menghapus file
kredensial atau mengganti akun/PC.

Pemulihan menggunakan aturan autentikasi sementara yang dibatasi ke
`postgres` pada database `postgres` dari `127.0.0.1`, lalu memulihkan
`pg_hba.conf` asli. Backup konfigurasi ada di `.postgres-recovery/`, yang
juga dikecualikan dari Git. Script `docker/postgres/Recover-LocalAdmin.ps1`
disediakan untuk pemulihan manual dengan Administrator dan menolak
pengulangan bila file kredensial sudah ada.

## Setup dari clone baru

Prasyarat: PHP 8.5, Composer, Node.js 24 LTS, serta PostgreSQL Windows aktif.
PHP CLI perlu ekstensi `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`, `curl`,
`intl`, dan `zip`, selain ekstensi standar PHP. Pastikan `php --ini`
menunjukkan file konfigurasi yang dimuat.
Pada instalasi PostgreSQL baru, buat user dan
database project sekali menggunakan admin `postgres`:

```powershell
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -h 127.0.0.1 -p 5432 -U postgres -d postgres -W -f docker/postgres/setup-local.sql
```

SQL membuat user `dashboard`, database development, dan database testing.
Jangan jalankan ulang bila user/database tersebut sudah ada.

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
npm ci
powershell -NoProfile -ExecutionPolicy Bypass -File setup-frankenphp.ps1
npm run build
php artisan migrate
composer run dev
```

Installer project mengunduh FrankenPHP Windows versi yang sudah diuji,
memverifikasi checksum resmi GitHub, dan menyiapkan ekstensi PHP untuk
runtime server. Jalankan installer saat `composer run dev` berhenti.
Konfigurasi ini untuk development lokal. Pada Linux/macOS, pasang
`frankenphp` pada PATH menggunakan installer resmi.

## Development dan pemeriksaan

Hot reload Svelte sudah termasuk dalam:

```powershell
composer run dev
```

Buka aplikasi di http://localhost:8000. Vite menggunakan port 5173.
Untuk build aset tanpa dev server, jalankan `npm run build`.
Jika proses berhenti paksa dan Laravel masih mengarah ke Vite yang mati,
hapus file `public/hot`.

```powershell
npm run check
npm run build
composer test
php vendor/bin/pint --format agent
```

Test menggunakan `dashboard_monitor_testing`, terpisah dari development.

## Perintah sehari-hari

```powershell
php artisan migrate
php artisan route:list
composer install
```

Session, cache, dan antrean menggunakan PostgreSQL. Worker sudah berjalan
melalui `composer run dev`. Jika ingin menjalankannya terpisah:

```powershell
php artisan queue:work
```

## Opsi aplikasi di Docker

Hentikan `composer run dev` terlebih dahulu agar port 8000 tersedia:

```powershell
docker compose up -d --build --wait
```

FrankenPHP Docker mengakses PostgreSQL PC melalui `host.docker.internal`;
`.env` tetap menggunakan `127.0.0.1` untuk aplikasi lokal. Override host
Docker menggunakan `DOCKER_DB_HOST` bila diperlukan.
Untuk hot reload frontend pada mode Docker, jalankan `npm run dev`.

Untuk kembali ke aplikasi lokal:

```powershell
docker compose stop app
composer run dev
```

## Opsi aplikasi dan PostgreSQL di Docker

Database Docker lama tetap tersimpan dalam named volume. Untuk memakai
PostgreSQL Docker, jalankan kedua file Compose:
Hentikan `composer run dev` terlebih dahulu agar port 8000 tersedia.

```powershell
docker compose -f compose.yaml -f compose.postgres.yaml up -d --wait
docker compose -f compose.yaml -f compose.postgres.yaml exec app php artisan config:clear
docker compose -f compose.yaml -f compose.postgres.yaml exec app php artisan migrate
```

Mode ini menimpa host aplikasi menjadi `postgres:5432`. Dari PC,
PostgreSQL Docker bisa diakses pada `127.0.0.1:5433`, yang diatur oleh
`FORWARD_DB_PORT`. Gunakan kedua file Compose juga untuk stop/test pada
mode ini. Database PC dan Docker terpisah; pergantian mode tidak menyalin data.

Untuk kembali ke database PC:

```powershell
docker compose -f compose.yaml -f compose.postgres.yaml stop postgres
docker compose up -d --wait app
docker compose exec app php artisan config:clear
```

Hindari `down -v` jika ingin mempertahankan data PostgreSQL Docker.

## File utama

- `routes/web.php`: route Laravel.
- `app/Http/Controllers/HomeController.php`: response Inertia.
- `app/Http/Middleware/HandleInertiaRequests.php`: shared props.
- `resources/js/Pages/Home.svelte`: halaman frontend.
- `resources/js/app.js`: entry point Inertia.
- `resources/views/app.blade.php`: template root.
- `Caddyfile`: FrankenPHP lokal.
- `dev.mjs`: menjalankan proses development bersama.
- `setup-frankenphp.ps1`: memasang runtime FrankenPHP Windows.
- `compose.yaml`: opsi FrankenPHP Docker dengan database PC.
- `compose.postgres.yaml`: opsi database Docker.
- `docker/frankenphp/Caddyfile`: document root `public/`.

Referensi: [Laravel](https://laravel.com/docs/13.x),
[Inertia + Svelte](https://inertiajs.com/docs/v3/installation/client-side-setup),
[Docker Desktop ke host](https://docs.docker.com/desktop/features/networking/networking-how-tos/),
[kredensial terenkripsi Windows](https://learn.microsoft.com/en-us/powershell/module/microsoft.powershell.utility/export-clixml),
[FrankenPHP Windows](https://frankenphp.dev/docs/).

# Security deployment checklist

Hardening API ini mengurangi risiko kebocoran konfigurasi, SQL injection, token palsu, dan akses langsung ke file internal. Tidak ada aplikasi yang bisa dijamin 100% kebal; konfigurasi server dan secret tetap wajib dijaga.

## Konfigurasi production

Simpan secret di environment server atau file di luar document root, lalu arahkan API dengan `APP_ENV_FILE`:

```ini
APP_ENV=production
APP_ENV_FILE=C:/secure/rest_api_sso.env
SSO_JWT_SECRET=isi-random-minimal-32-karakter
CORS_ALLOWED_ORIGINS=https://aplikasi-resmi.example
API_DOCS_ACCESS_CODE=kode-dokumentasi-kuat
```

`SSO_JWT_SECRET` wajib berbeda dari secret development. Jika secret JWT tidak ada, server production akan menolak proses autentikasi. File `.env` lokal tetap diblokir Apache, tetapi praktik yang lebih aman adalah tidak menyimpannya di folder publik.

## Proteksi yang aktif

- `.htaccess` menolak `.env`, metadata Git, Composer, file konfigurasi, SQL/log/backup, dan folder internal.
- Semua endpoint melewati `index.php` dan route allowlist. Controller internal tidak dapat dipanggil langsung melalui URL.
- CORS tidak lagi memakai wildcard `*`; hanya origin yang terdaftar yang diberi akses browser.
- PDO memakai prepared statement dan emulated prepares dimatikan.
- Error database dan SMTP dicatat ke log server, bukan dikirim bersama detail internal ke client.
- JWT memvalidasi signature HS256, format Base64URL, expiry, `nbf`, dan tipe token akses. Token OTP/reset tidak dapat dipakai sebagai Bearer token.
- `swagger.json` hanya disajikan melalui `docs/openapi.php` setelah login dokumentasi.

## Endpoint yang memang publik

`POST /api/auth/login`, alur reset password, dan `GET /api/simpeg/pegawai/search` tetap publik sesuai kebutuhan integrasi saat ini. Endpoint AO, profile, whoami, dan perubahan password membutuhkan Bearer JWT.

Gunakan HTTPS di server, user database dengan privilege minimum, backup secret secara aman, serta matikan `display_errors` pada PHP production.

# Security deployment checklist

Hardening API ini mengurangi risiko kebocoran konfigurasi, SQL injection, token palsu, dan akses langsung ke file internal. Tidak ada aplikasi yang bisa dijamin 100% kebal; konfigurasi server dan secret tetap wajib dijaga.

## Konfigurasi production

Simpan secret di environment server atau file di luar document root, lalu arahkan API dengan `APP_ENV_FILE`:

```ini
APP_ENV=production
APP_ENV_FILE=C:/secure/rest_api_sso.env
SSO_JWT_SECRET=isi-random-minimal-32-karakter
CORS_ALLOWED_ORIGINS=https://aplikasi-resmi.example
APP_TOKEN_REQUIRED=true
```

`SSO_JWT_SECRET` wajib berbeda dari secret development. Jika secret JWT tidak ada, server production akan menolak proses autentikasi. File `.env` lokal tetap diblokir Apache, tetapi praktik yang lebih aman adalah tidak menyimpannya di folder publik.

## Proteksi yang aktif

- `.htaccess` menolak `.env`, metadata Git, Composer, file konfigurasi, SQL/log/backup, dan folder internal.
- Semua endpoint melewati `index.php` dan route allowlist. Controller internal tidak dapat dipanggil langsung melalui URL.
- CORS tidak lagi memakai wildcard `*`; hanya origin yang terdaftar yang diberi akses browser.
- PDO memakai prepared statement dan emulated prepares dimatikan.
- Error database dan SMTP dicatat ke log server, bukan dikirim bersama detail internal ke client.
- JWT memvalidasi signature HS256, format Base64URL, expiry, `nbf`, dan tipe token akses. Token OTP/reset tidak dapat dipakai sebagai Bearer token.
- App token global divalidasi terhadap registry `api_applications`; database hanya menyimpan hash token.
- Control plane memakai akun `user_sso`, permission per menu, cookie HttpOnly/SameSite, timeout, CSRF token, serta pembatasan percobaan login berbasis IP di database.
- Audit hit tidak merekam query string, request body, JWT, atau token aplikasi.
- Dokumentasi interaktif dan `swagger.json` hanya disajikan melalui session admin yang memiliki permission docs terkait.

## Endpoint yang memang publik

`POST /api/auth/login`, alur reset password, dan `GET /api/simpeg/pegawai/search`
tidak membutuhkan login pegawai, tetapi semuanya tetap membutuhkan app token
ketika `APP_TOKEN_REQUIRED=true`. Endpoint AO, profile, whoami, dan perubahan
password membutuhkan app token serta Bearer JWT.

Gunakan HTTPS di server, user database dengan privilege minimum, backup secret secara aman, serta matikan `display_errors` pada PHP production. Buat user pertama melalui `tools/create_sso_user.php`; jangan menjadikan password atau token sebagai source code.

Pada Nginx, `.htaccess` tidak berlaku. Letakkan rule berikut sebelum blok PHP
umum di virtual host agar file internal tidak dapat dieksekusi langsung:

```nginx
location ~ ^/(?:api/(?:Core|global|legacy|modules|routes\.php)|config|helpers|middlewares|database|tools)(?:/|$) {
    return 404;
}

location ~ ^/(?:admin|docs)/.*\.md$ {
    return 404;
}

location = /check.php {
    return 404;
}
```

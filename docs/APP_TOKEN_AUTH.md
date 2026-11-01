# Autentikasi Aplikasi Global

Semua aplikasi pemanggil API, seperti `simpeg`, `monbis`, dan `notaris`,
menggunakan kredensial aplikasi terpisah dari token login pegawai.

## Header request

```http
App: simpeg
X-App-Token: app_<token-rahasia>
Authorization: Bearer <jwt-user>
```

`Authorization` hanya dikirim untuk endpoint yang memang memerlukan login
pegawai. `App` dan `X-App-Token` dikirim pada semua endpoint setelah proteksi
diaktifkan. Token aplikasi tidak dikirim lewat body karena body pada request
GET tidak konsisten dan raw token dapat ikut tersimpan di log payload.

Nilai `App` pada header login harus sama dengan field `app` pada body login.
Untuk endpoint yang membutuhkan JWT, aplikasi pada header juga harus sama dengan
claim `app` di JWT tersebut.

## Menyiapkan tabel

Jalankan migration berikut pada database API:

```text
database/migrations/001_create_api_applications.sql
```

Migration ini hanya membuat tabel registry aplikasi. Token mentah tidak pernah
disimpan; database hanya menyimpan hash SHA-256.

Token manual minimal 8 karakter dapat dipakai untuk kompatibilitas development,
tetapi token hasil generator lebih aman karena memiliki entropy yang lebih tinggi.

## Mendaftarkan aplikasi

Dari root project, jalankan melalui PHP CLI:

```powershell
C:\xampp\php\php.exe tools/create_app_token.php --code=simpeg --name="SIMPEG"
```

Simpan token yang tercetak di password manager atau secret manager. Token hanya
ditampilkan saat pendaftaran. Ulangi untuk `monbis`, `notaris`, dan aplikasi
lainnya dengan `--code` yang berbeda.

## Mengaktifkan proteksi

Tambahkan pada environment server, bukan pada source code:

```dotenv
APP_TOKEN_REQUIRED=true
```

Saat `false`, middleware tetap terpasang tetapi meneruskan request agar proses
migrasi client dapat dilakukan tanpa downtime. Setelah semua client sudah
mengirim header dan seluruh token sudah terdaftar, ubah ke `true` lalu restart
PHP-FPM/Apache sesuai konfigurasi server.

## Response error

| Kondisi | HTTP | Message |
| --- | ---: | --- |
| Header aplikasi tidak ada | 401 | `App dan X-App-Token wajib diisi.` |
| Token salah, nonaktif, atau kedaluwarsa | 403 | `Token aplikasi tidak valid atau tidak aktif.` |
| Registry aplikasi/database tidak tersedia | 503 | `Validasi aplikasi sedang tidak tersedia.` |

Endpoint dan response bisnis yang sudah dipakai client tetap dipertahankan.
Middleware berjalan sebelum controller sehingga controller tidak perlu
mengulang validasi app token.

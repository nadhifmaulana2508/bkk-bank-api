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

Nilai `App` pada header login harus sudah terdaftar di Registry API. Field `app`
tidak dikirim di body login; login pegawai memakai `id_peg` dan `password`.
Tidak ada lagi daftar aplikasi yang harus ditambahkan manual ke
source code. Untuk endpoint yang membutuhkan JWT, aplikasi pada header juga
harus sama dengan claim `app` di JWT tersebut.

## Menyiapkan tabel

Jalankan migration registry pada database SSO, lalu migration vault pada
database Bank API (`BANK_API_DB_NAME`, default `db_bank_api`):

```text
database/migrations/001_create_api_applications.sql
database/migrations/007_create_api_application_token_vault.sql
```

Migration registry menyimpan hash SHA-256 untuk validasi. Migration vault
menambahkan salinan token terenkripsi agar admin berizin dapat menyalinnya
kembali. Token aplikasi lama otomatis dimasukkan ke vault setelah request valid
berikutnya, jika `APP_TOKEN_ENCRYPTION_KEY` atau `SSO_JWT_SECRET` acak minimal
32 karakter tersedia. Pada development lokal tanpa keduanya, key disimpan di
`storage/app-token-encryption.key` dan harus dipertahankan.

Token manual minimal 8 karakter dapat dipakai untuk kompatibilitas development,
tetapi token hasil generator lebih aman karena memiliki entropy yang lebih tinggi.

## Mendaftarkan aplikasi

Untuk mendaftarkan aplikasi lewat Control Plane, buka halaman berikut setelah
login admin:

```text
/admin/registry
```

Klik **Tambah aplikasi**, isi kode `monbis` dan nama `MONBIS`, lalu simpan.
Dialog setelah penyimpanan menyediakan tiga opsi salin. Pilih **Salin untuk
developer** untuk menyalin konfigurasi `.env` dan header request sekaligus.
Token mentah tersedia pada dialog create/rotate. Jika salinan terenkripsi ada
di vault, ikon Registry menyalinnya tanpa rotasi. Jika belum ada, ikon tetap
hanya mencoba menyalin dan menampilkan alasan token belum tersedia. Rotasi
dilakukan lewat ikon rotasi terpisah setelah konfirmasi; token baru tampil satu
kali jika vault belum siap. Audit log tidak menyimpan nilai token.

Dari root project, jalankan melalui PHP CLI:

```powershell
C:\xampp\php\php.exe tools/create_app_token.php --code=simpeg --name="SIMPEG"
```

Simpan token yang tercetak di password manager atau secret manager. Token hanya
ditampilkan saat pendaftaran. Ulangi untuk `monbis`, `notaris`, dan aplikasi
lainnya dengan `--code` yang berbeda.

Contoh konfigurasi pada aplikasi MONBIS:

```dotenv
SSO_APP=monbis
SSO_APP_TOKEN=app_<token-rahasia>
```

Contoh request ke endpoint global:

```http
GET /api/simpeg/pegawai/search?q=rifqi
App: monbis
X-App-Token: app_<token-rahasia>
```

Endpoint publik dan response bisnis lama tetap memakai URL yang sama. URL
bersih seperti `/admin/registry` dan `/docs/global` hanya dipakai untuk
Control Plane dan dokumentasi.

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

# Global SSO

`SsoService` menjadi implementasi pusat untuk login, identitas pegawai,
perubahan profil, perubahan password, dan reset password.

`api/legacy/AuthController.php` hanya adapter agar endpoint lama tetap memakai
path, request, response, dan status code yang sama.

Login pegawai menggunakan `id_peg` dan password. Kode
aplikasi tidak lagi dikirim di body; identitas aplikasi tetap dibaca dari
header `App` dan `X-App-Token`.

## Login dummy untuk pengujian

Untuk XAMPP, cukup atur `APP_ENV=local` pada `.env` milik `bank-api`.
Endpoint dummy otomatis aktif hanya untuk request localhost; token aplikasi
terdaftar dan akun pegawai aktif tetap wajib. Password dummy lokal memakai
default development, atau dapat diganti dengan `SSO_DUMMY_PASSWORD`.
`SSO_DUMMY_AUTH_ENABLED=false` dapat dipakai untuk menonaktifkannya secara
eksplisit.

Untuk pengujian dari KPI/SLA lokal ke SSO yang sudah di-deploy, konfigurasi
environment dev di aaPanel dengan `APP_ENV=development`,
`SSO_DUMMY_AUTH_ENABLED=true`, `SSO_DUMMY_PASSWORD`, dan
`SSO_DUMMY_ALLOWED_IPS` berisi IP publik penguji (pisahkan beberapa IP dengan
koma). Server harus melihat IP tersebut sebagai `REMOTE_ADDR`. Production
tetap menolak endpoint dummy. Jangan gunakan kunci JWT production pada
environment dev.

`POST /api/auth/login-dummy` memakai ID pegawai dengan akun login dan jabatan
aktif; akun `tb_apk.pass` tidak diubah. Header `App` dan `X-App-Token` dari
aplikasi aktif yang terdaftar tetap wajib dikirim.

`GET|POST /api/auth/auth-dummy` menerima token itu dan mengembalikan profil
serta jabatan pegawai. JWT memakai format dan kunci SSO environment tersebut
agar endpoint aplikasi yang dilindungi SSO dapat diuji. Token dev hanya
digunakan pada layanan development. Setelah pengujian, matikan flag dan
arahkan aplikasi kembali ke `/api/auth/login` dan `/api/auth/whoami`.

## Reset password oleh admin

`POST /api/auth/admin-reset-password` menerima `id_peg`, mengambil `tgl_lhr`
dari `tb_pegawai`, lalu menetapkan password sementara dengan format `ddmmyy`.
Endpoint ini menerima token dari aplikasi aktif mana pun yang sudah terdaftar
di Registry. Response mengembalikan password sementara; sampaikan secara
pribadi dan minta pegawai mengganti password setelah berhasil login.

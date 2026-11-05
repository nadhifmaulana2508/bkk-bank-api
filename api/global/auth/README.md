# Global SSO

`SsoService` menjadi implementasi pusat untuk login, identitas pegawai,
perubahan profil, perubahan password, dan reset password.

`api/legacy/AuthController.php` hanya adapter agar endpoint lama tetap memakai
path, request, response, dan status code yang sama.

Login pegawai menggunakan `id_peg`, nomor HP yang terdaftar pada `tb_pegawai`,
dan password. `id_peg` dan nomor HP harus berasal dari pegawai yang sama. Kode
aplikasi tidak lagi dikirim di body; identitas aplikasi tetap dibaca dari
header `App` dan `X-App-Token`.

# Global SSO

`SsoService` menjadi implementasi pusat untuk login, identitas pegawai,
perubahan profil, perubahan password, dan reset password.

`api/legacy/AuthController.php` hanya adapter agar endpoint lama tetap memakai
path, request, response, dan status code yang sama.

# Legacy API Adapters

Folder ini menampung adapter untuk endpoint lama yang sudah dipakai aplikasi
lain. Adapter boleh berubah secara internal, tetapi kontrak publiknya harus
tetap sama.

Adapter saat ini:

- `AuthController.php` meneruskan `/api/auth/*` ke Global SSO;

AO sekarang berada di modul SIMPEG dan memakai route baru
`/api/simpeg/ao/list` karena endpoint tersebut belum dipakai client.

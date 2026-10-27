# Legacy API Adapters

Folder ini menampung adapter untuk endpoint lama yang sudah dipakai aplikasi
lain. Adapter boleh berubah secara internal, tetapi kontrak publiknya harus
tetap sama.

Adapter saat ini:

- `AuthController.php` meneruskan `/api/auth/*` ke Global SSO;
- `AoController.php` mempertahankan `/api/ao/list`.

Router tetap berada di `index.php` agar URL lama tidak berubah.

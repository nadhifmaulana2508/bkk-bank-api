# SSO Control Plane

Dashboard internal untuk mengelola registry aplikasi dan membaca audit hit SSO.

## URL lokal

```text
http://localhost/bank-api/admin/registry
```

URL development:

```text
https://devsso.bkkjateng.co.id/admin/registry
```

Production tetap memakai `https://apisso.bkkjateng.co.id` setelah seluruh
perubahan selesai diuji di development.

Halaman control plane:

```text
/admin/registry          Registry aplikasi dan create/edit token
/admin/audit             Hit endpoint terbaru
/admin/recap             Rekap hit harian
/admin/rbac              User SSO dan permission per menu
/admin/docs/global       Dokumentasi API Global
/admin/docs/simpeg       Dokumentasi endpoint API Simpeg
/admin/docs/nora         Dokumentasi endpoint API NORA
/admin/docs/monbis       Placeholder dokumentasi API Monbis
/admin/docs/dummy        Dokumentasi endpoint login dan profil dummy
```

Menu API Docs membuka dokumentasi di bawah route `/admin/docs`. Halaman docs
memakai daftar endpoint sendiri tanpa sidebar Control Plane, sedangkan halaman
admin lain tetap menampilkan sidebar tersebut. Sesi dan permission admin tetap
sama. URL lama `/docs/global` dan kelompok dokumentasi lain mengarah ke route baru.
Pilih **Unduh semua dokumentasi (.md)** pada menu yang sama untuk mengunduh satu
file Markdown gabungan dari dokumen di folder `docs`, `api`, dan `admin`.

Login memakai tabel `user_sso`. Password disimpan sebagai hash dan permission
menu dapat dicentang per user dari menu **RBAC**.

## Fitur

- melihat aplikasi terdaftar, status, masa berlaku, hit 7 hari, dan hit terakhir;
- membuat aplikasi baru dengan token random 256-bit;
- menyalin token aplikasi dari registry tanpa rotasi jika salinan vault tersedia;
- memperpanjang atau mengubah status tanpa menghapus riwayat hit;
- merotasi token sehingga token lama langsung tidak berlaku;
- melihat hit terbaru dan rekap hit 14 hari terakhir;
- mencatat aksi admin create, update, rotate, dan reveal tanpa menyimpan token mentah di audit log.
- membuat atau menonaktifkan user web serta mengatur permission per menu;
- memilih pegawai internal dari pencarian direktori berdasarkan nama atau `id_peg`,
  atau membuat user eksternal dengan form manual;
- mendeteksi `app_code` yang sudah terdaftar saat mengetik dan tetap memvalidasinya
  di backend untuk mencegah duplikasi karena request bersamaan;
- membuka dokumentasi API Global, Simpeg, NORA, Monbis, dan Dummy memakai session admin yang sama. Tab Dummy memakai permission `docs.global`.
- mengunduh semua dokumen Markdown repository dalam satu file untuk implementasi dan referensi.

Token divalidasi memakai hash SHA-256. Salinan terenkripsi di tabel
`api_application_token_vault` dapat dibuka oleh admin berizin. Token aplikasi
lama masuk ke vault otomatis setelah aplikasi mengirim request valid pertama
sesudah migration dan `APP_TOKEN_ENCRYPTION_KEY` disiapkan. Proses ini tidak
merotasi token.

Jika vault belum siap saat aplikasi dibuat atau token dirotasi, registry tetap
berhasil menyimpan hash dan dialog menampilkan token mentah satu kali. Salin
token sebelum dialog ditutup. Ikon salin pada aplikasi yang belum punya salinan
vault tetap hanya mencoba menyalin dan menampilkan alasan jika salinan belum
tersedia. Rotasi selalu dilakukan lewat aksi rotasi terpisah setelah konfirmasi;
token lama langsung tidak berlaku setelah rotasi berhasil.

`APP_TOKEN_ENCRYPTION_KEY` dapat diisi dengan Base64 32-byte key untuk memakai
kunci khusus. Jika kosong, vault menurunkan kunci terpisah memakai HKDF dari
`SSO_JWT_SECRET` yang acak dan minimal 32 karakter. Pada `APP_ENV=local` atau
`development`, bila secret kuat belum tersedia, aplikasi membuat key lokal di
`storage/app-token-encryption.key`; pertahankan file itu agar vault lokal tetap
bisa dibuka. Jangan simpan key di repository atau database. Jika memakai HKDF
fallback lalu beralih ke `APP_TOKEN_ENCRYPTION_KEY`, vault akan mencoba kunci
lama yang masih tersedia dan mengenkripsi ulang token saat token berhasil dibuka.
Pertahankan secret lama sampai token tersalin setidaknya sekali. Jika semua
kunci lama sudah hilang, ciphertext tidak dapat dipulihkan.

## Migration

Jalankan berurutan:

```text
database/migrations/001_create_api_applications.sql
database/migrations/002_create_api_app_request_logs.sql
database/migrations/003_create_api_admin_audit_logs.sql
database/migrations/004_create_user_sso_rbac.sql
database/migrations/005_add_admin_actor_to_audit_logs.sql
database/migrations/006_add_employee_id_to_user_sso.sql
database/migrations/007_create_api_application_token_vault.sql
database/migrations/008_scope_login_limits_by_user.sql
```

Jalankan migration `001`–`006` dan `008` pada database registry SSO. Migration
`008` menghapus penghitung percobaan login sementara yang lama dan membatasi
percobaan berdasarkan pasangan alamat IP dan username. Jalankan migration `007`
pada database Bank API yang ditunjuk `BANK_API_DB_NAME` (default:
`db_bank_api`). Vault memakai `APP_ENV` dan `DB_NAME` sebagai namespace agar
token development dan production tidak tertukar jika database Bank API sama.

Buat user pertama melalui PHP CLI:

```powershell
C:\xampp\php\php.exe tools/create_sso_user.php --username=admin --name="BKK Administrator" --role=admin --password="password-minimal-12-karakter"
```

`api_app_request_logs` menyimpan method, path tanpa query string, status HTTP,
IP, user-agent, aplikasi, dan waktu. Body request, query parameter, password,
JWT, dan app token tidak dicatat.

Identity internal Control Plane memakai kode aplikasi `sso`. Jika perlu
menyiapkannya di environment lain, jalankan dan simpan token yang ditampilkan:

```powershell
C:\xampp\php\php.exe tools/create_app_token.php --code=sso --name="SSO Control Plane"
```

## Operasional aman

1. Gunakan `APP_ENV_FILE` di luar document root pada production.
2. Buat password user SSO random panjang dan berikan permission minimum yang diperlukan.
3. Gunakan HTTPS sehingga cookie admin memiliki flag `Secure`.
4. Rotasi token bila aplikasi pindah server, credential bocor, atau ownership berubah.
5. Terapkan retention database untuk log sesuai kebijakan internal; log tidak boleh dibiarkan tumbuh tanpa batas.


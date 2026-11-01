# SSO Control Plane

Dashboard internal untuk mengelola registry aplikasi dan membaca audit hit SSO.

## URL lokal

```text
http://localhost/rest_api_sso/admin/
```

Halaman control plane:

```text
?page=registry       Registry aplikasi dan create/edit token
?page=audit          Hit endpoint terbaru
?page=recap          Rekap hit harian
?page=rbac           User SSO dan permission per menu
../docs/?group=global    Dokumentasi API Global
../docs/?group=simontok  Dokumentasi API Simontok
../docs/?group=monbis    Dokumentasi API Monbis
```

Menu API Docs pada navbar membuka dokumentasi interaktif terproteksi untuk testing endpoint.

Login memakai tabel `user_sso`. Password disimpan sebagai hash dan permission
menu dapat dicentang per user dari menu **RBAC**.

## Fitur

- melihat aplikasi terdaftar, status, masa berlaku, hit 7 hari, dan hit terakhir;
- membuat aplikasi baru dengan token random 256-bit;
- memperpanjang atau mengubah status tanpa menghapus riwayat hit;
- merotasi token sehingga token lama langsung tidak berlaku;
- melihat hit terbaru dan rekap hit 14 hari terakhir;
- mencatat aksi admin create, update, dan rotate tanpa menyimpan token mentah.
- membuat atau menonaktifkan user web serta mengatur permission per menu;
- memilih pegawai internal dari pencarian direktori berdasarkan nama atau `id_peg`,
  atau membuat user eksternal dengan form manual;
- mendeteksi `app_code` yang sudah terdaftar saat mengetik dan tetap memvalidasinya
  di backend untuk mencegah duplikasi karena request bersamaan;
- membuka dokumentasi API Global, Simontok, dan Monbis memakai session admin yang sama.

Token baru hanya dikirim pada response create/rotate dan hanya ditampilkan pada
dialog one-time. Simpan di secret manager; dashboard tidak dapat mengambil token
itu kembali.

## Migration

Jalankan berurutan:

```text
database/migrations/001_create_api_applications.sql
database/migrations/002_create_api_app_request_logs.sql
database/migrations/003_create_api_admin_audit_logs.sql
database/migrations/004_create_user_sso_rbac.sql
database/migrations/005_add_admin_actor_to_audit_logs.sql
database/migrations/006_add_employee_id_to_user_sso.sql
```

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

# TTD IN BKK

Prototype aplikasi web PHP Native untuk TTD Digital Terpusat PT BPR BKK JATENG.

## Arsitektur
- Login consume BKK SSO 2026.
- Database TTD terpisah: `db_ttd_in_bkk`.
- Password pegawai tidak disimpan.
- Setelah login, middleware mengecek profil TTD dan PIN.
- Belum punya TTD -> wajib registrasi.
- Signing selalu meminta PIN.
- Output: gambar TTD + QR verifikasi + SHA-256 + audit trail.

## Instalasi
1. Copy `.env.example` menjadi `.env`.
2. Import `database/schema.sql`.
3. Isi konfigurasi DB, `APP_URL`, `SSO_BASE_URL`, dan `SSO_APP`. Untuk struktur folder pada XAMPP di repository ini gunakan `http://localhost/rest_api_sso/ttd_in_bkk`.
4. Pastikan PHP cURL aktif.
5. PHP GD direkomendasikan untuk background transparan.
6. Pastikan folder `storage/signatures` writable oleh PHP.
7. Buka aplikasi dan login menggunakan akun yang punya akses `simpeg` pada BKK SSO.

Jika QR akan dipindai dari HP, jangan gunakan `localhost` pada `APP_URL`. Gunakan IP laptop dalam satu jaringan, misalnya `http://192.168.1.10/rest_api_sso/ttd_in_bkk`, lalu pastikan Apache dan Windows Firewall dapat diakses dari HP.

Saat tombol **Print Surat** ditekan, aplikasi mencoba mengambil nama kota dari lokasi perangkat melalui geolocation + reverse map. Izin lokasi bersifat opsional, koordinat tidak disimpan, dan jika izin/lokasi tidak tersedia sistem memakai value kantor/cabang dari profil SSO.

## Halaman
- `index.php`: login SSO
- `register-signature.php`: gambar/upload TTD + create PIN
- `dashboard.php`: dashboard
- `signature.php`: TTD Saya
- `documents.php`: menu **Coba TTD Surat**, dokumen dummy + signing
- `activity.php`: audit trail
- `verify.php`: QR verification

## Keamanan MVP
- PIN menggunakan `password_hash()`.
- 5 kali PIN salah -> lock 15 menit.
- CSRF pada form dan API signing.
- ID pegawai berasal dari session hasil SSO.
- Audit IP dan user-agent.

## Alur penggunaan cepat

Setelah login berhasil dan profil TTD berstatus `ACTIVE`, buka **Dashboard → Coba TTD Surat**. Masukkan PIN TTD untuk membuat signature image, hash SHA-256, QR verifikasi, dokumen, dan audit log. Gunakan **Print Surat** untuk melihat/mencetak bentuk surat formal; versi cetak menempatkan tanggal, nama instansi, QR kecil berlogo, nama, dan jabatan penandatangan. Jika user sudah memiliki record aktif di `signature_profiles` dan `signature_security`, user langsung diarahkan ke dashboard; user baru diarahkan ke registrasi TTD.

Setiap dokumen signing baru juga menyimpan `signature_path` sebagai snapshot referensi TTD saat signing, sehingga QR lama tetap menunjuk ke hasil TTD yang sesuai.

## Pengecekan lokal

Lint PHP seluruh aplikasi:

```bash
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Uji SSO tanpa password asli dapat dilakukan dengan memasukkan kredensial dummy; aplikasi harus menerima respons HTTP dari `https://apisso.bkkjateng.co.id` dan tidak menyimpan password.

## Sebelum produksi
Gunakan private storage di luar webroot, HTTPS + secure cookie, rate limit, revoke/versioning TTD, server-side QR, CORS allowlist, serta Signature API terpisah. Jika dibutuhkan TTE tersertifikasi, integrasikan PSrE.

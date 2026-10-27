# CODEX IMPLEMENTATION PLAN - TTD IN BKK

Dokumen ini dibuat agar Codex dapat langsung melanjutkan, menjalankan, menguji, memperbaiki, dan menyempurnakan prototype **TTD IN BKK** tanpa perlu menebak-nebak arsitektur.

---

## 1. TUJUAN

Bangun aplikasi web **TTD IN BKK** berbasis PHP Native yang:

- menggunakan **BKK SSO 2026** untuk login;
- tidak menyimpan password pegawai;
- memakai database terpisah `db_ttd_in_bkk`;
- mewajibkan registrasi TTD untuk user yang belum punya TTD;
- mendukung gambar TTD langsung atau upload PNG/JPG;
- mewajibkan PIN TTD 6 digit;
- meminta PIN setiap kali proses signing;
- menghasilkan:
  1. gambar TTD pegawai;
  2. QR verifikasi;
- mencatat document hash SHA-256;
- mencatat audit trail;
- responsive desktop, tablet, Android, dan iPhone.

---

## 2. REPOSITORY DAN BRANCH

Repository:

`nadhifmaulana2508/rest_api_sso`

Branch development:

`feature/ttd-in-bkk-app`

Folder aplikasi:

`ttd_in_bkk/`

JANGAN mengubah flow login SSO production yang sudah berjalan kecuali memang dibutuhkan.

---

## 3. SUMBER AUTENTIKASI

Gunakan BKK SSO 2026:

Base URL:

`https://apisso.bkkjateng.co.id`

Login:

`POST /api/auth/login`

Payload:

```json
{
  "id_peg": "ID_PEGAWAI",
  "password": "PASSWORD",
  "app": "simpeg"
}
```

Ambil profil:

`GET /api/auth/whoami`

Header:

`Authorization: Bearer <token>`

Gunakan hasil whoami sebagai identitas user di aplikasi TTD IN BKK.

Jangan buat tabel password/user login lokal.

---

## 4. FLOW UTAMA

### Login

```
Pegawai
  -> Login TTD IN BKK
  -> POST BKK SSO
  -> Token
  -> GET whoami
  -> Session lokal
  -> cek signature profile
```

Jika TTD belum ada:

```
Login berhasil
  -> register-signature.php
  -> gambar/upload TTD
  -> preview
  -> buat PIN 6 digit
  -> konfirmasi PIN
  -> simpan profile
  -> ACTIVE
  -> dashboard
```

Jika TTD sudah ada:

```
Login berhasil
  -> dashboard
```

---

## 5. FLOW SIGNING

```
Dokumen
  -> klik Tanda Tangani
  -> modal PIN
  -> validasi PIN server-side
  -> jika salah: tolak
  -> jika benar:
       create SHA-256
       create verification token
       save signature document
       save audit log
       return signature image
       return verification URL
```

Response yang diinginkan:

```json
{
  "success": true,
  "data": {
    "signature_url": "...",
    "verification_url": "...",
    "document_hash": "...",
    "signed_at": "..."
  }
}
```

---

## 6. DATABASE

Gunakan database terpisah:

`db_ttd_in_bkk`

Schema ada di:

`ttd_in_bkk/database/schema.sql`

Tabel utama:

- `signature_profiles`
- `signature_security`
- `signature_documents`
- `signature_logs`

Jangan copy tabel master pegawai SSO ke database ini.

Identitas pegawai cukup berdasarkan `id_peg` hasil SSO.

---

## 7. SECURITY MINIMAL MVP

Wajib:

- PIN disimpan pakai `password_hash()`;
- verifikasi pakai `password_verify()`;
- PIN 6 digit;
- maksimal 5 kali salah;
- lock 15 menit setelah gagal 5 kali;
- CSRF untuk form penting;
- ID pegawai jangan diambil dari request user;
- ID pegawai harus dari session hasil SSO;
- audit IP;
- audit user-agent;
- session regenerate setelah login;
- jangan simpan password SSO;
- jangan taruh secret production di source code.

---

## 8. FILE YANG SUDAH ADA

Periksa file berikut:

```
ttd_in_bkk/
├── index.php
├── dashboard.php
├── register-signature.php
├── signature.php
├── documents.php
├── activity.php
├── verify.php
├── logout.php
│
├── api/
│   └── sign.php
│
├── middleware/
│   ├── auth.php
│   └── signature.php
│
├── lib/
│   ├── helpers.php
│   ├── csrf.php
│   ├── sso.php
│   └── signature.php
│
├── config/
│   └── bootstrap.php
│
├── database/
│   └── schema.sql
│
├── assets/
│   └── app.css
│
├── partials/
│   └── header.php
│
├── .env.example
├── README.md
└── CODEX_IMPLEMENTATION_PLAN.md
```

---

## 9. ENV LOCAL

Buat:

`ttd_in_bkk/.env`

Isi contoh:

```env
APP_NAME="TTD IN BKK"
APP_ENV="development"
APP_URL="http://localhost/rest_api_sso/ttd_in_bkk"

DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME="db_ttd_in_bkk"
DB_USER="root"
DB_PASS=""

SSO_BASE_URL="https://apisso.bkkjateng.co.id"
SSO_APP="simpeg"

SESSION_NAME="TTDINBKKSESSID"
SESSION_SECURE="0"
```

---

## 10. SETUP XAMPP

Project local diperkirakan berada di:

`C:\xampp\htdocs\rest_api_sso`

Pastikan:

- Apache ON
- MySQL ON
- PHP cURL aktif
- PHP GD aktif jika tersedia

PHP extensions:

```ini
extension=curl
extension=gd
```

Setelah edit php.ini, restart Apache.

---

## 11. DATABASE SETUP

Import:

`ttd_in_bkk/database/schema.sql`

Pastikan muncul:

`db_ttd_in_bkk`

Dengan tabel:

```
signature_profiles
signature_security
signature_documents
signature_logs
```

---

## 12. URL LOCAL

Aplikasi:

`http://localhost/rest_api_sso/ttd_in_bkk/`

Jika test dari HP satu jaringan, ubah APP_URL menjadi IP laptop, contoh:

`http://192.168.1.10/rest_api_sso/ttd_in_bkk`

Cek IP Windows:

`ipconfig`

---

## 13. UI YANG DIHARAPKAN

### Login
- branding BKK;
- login ID Pegawai;
- password;
- tombol Masuk melalui SSO;
- responsive.

### Registrasi TTD
- canvas draw;
- upload PNG/JPG;
- preview;
- hapus/reset;
- PIN 6 digit;
- konfirmasi PIN;
- tombol Simpan & Aktifkan TTD.

### Dashboard
- status TTD ACTIVE;
- jumlah dokumen ditandatangani;
- terakhir tanda tangan;
- profil pegawai dari SSO;
- tombol Coba Tanda Tangani;
- tombol TTD Saya.

### TTD Saya
- preview signature;
- status;
- tanggal registrasi;
- tombol ganti TTD/PIN.

### Dokumen
- dokumen dummy;
- tombol Tanda Tangani;
- modal input PIN;
- hasil TTD;
- QR verifikasi;
- document hash.

### Aktivitas
- audit history;
- waktu;
- aksi;
- status;
- document ID;
- IP.

---

## 14. QR VERIFIKASI

QR harus mengarah ke:

`verify.php?token=<verification_token>`

Landing page verifikasi minimal menampilkan:

- status dokumen tercatat;
- document ID;
- id_peg;
- aplikasi;
- waktu signing;
- SHA-256 hash.

Untuk MVP boleh pakai client-side QR.

Untuk production nanti migrasikan ke server-side QR.

---

## 15. YANG HARUS CODEX LAKUKAN

Tolong kerjakan urut:

1. checkout branch `feature/ttd-in-bkk-app`;
2. audit semua file di `ttd_in_bkk/`;
3. cari syntax error PHP;
4. cari broken path;
5. cek include/require;
6. cek session flow;
7. cek CSRF;
8. cek integrasi SSO;
9. cek format response login SSO;
10. cek format whoami;
11. pastikan redirect login -> register/dashboard benar;
12. pastikan database query valid;
13. pastikan upload/draw signature tersimpan;
14. pastikan PIN hash tersimpan;
15. pastikan signing berhasil;
16. pastikan 5x PIN salah lock 15 menit;
17. pastikan document hash tersimpan;
18. pastikan audit log tersimpan;
19. pastikan verify page bekerja;
20. perbaiki UI responsive;
21. test desktop;
22. test mobile width 360px;
23. test tablet width 768px;
24. jangan ubah behavior SSO production;
25. buat catatan perubahan di README jika ada.

---

## 16. TEST CASE

### Test 1 - Login Valid
Expected:
- SSO berhasil;
- whoami berhasil;
- session tersimpan.

### Test 2 - Belum Ada TTD
Expected:
- diarahkan ke register-signature.php.

### Test 3 - Registrasi TTD
Expected:
- signature tersimpan;
- PIN hash tersimpan;
- profile ACTIVE.

### Test 4 - Login Berikutnya
Expected:
- langsung dashboard.

### Test 5 - Sign dengan PIN benar
Expected:
- sign sukses;
- document tersimpan;
- audit log tersimpan;
- TTD tampil;
- QR tampil.

### Test 6 - PIN salah
Expected:
- ditolak;
- failed_attempt bertambah.

### Test 7 - PIN salah 5 kali
Expected:
- locked 15 menit.

### Test 8 - Verify QR
Expected:
- token ditemukan;
- metadata dokumen tampil.

### Test 9 - Mobile
Expected:
- tidak overflow;
- canvas dapat dipakai touch;
- modal usable.

---

## 17. ACCEPTANCE CRITERIA

Prototype dianggap selesai jika:

- login SSO real berjalan;
- tidak menyimpan password;
- user baru wajib register TTD;
- TTD bisa digambar atau upload;
- PIN dibuat dan di-hash;
- sign pakai PIN;
- output TTD muncul;
- QR muncul;
- QR verification page valid;
- audit log tersimpan;
- layout responsive;
- tidak ada PHP fatal error;
- tidak merusak SSO existing.

---

## 18. CATATAN PRODUKSI

Prototype ini bukan klaim TTE tersertifikasi.

Untuk production nanti pertimbangkan:

- PSrE;
- private object storage;
- Signature API terpisah;
- API key/client credential antar aplikasi;
- rate limit;
- revoke/versioning signature;
- approval perubahan TTD;
- HSM/key management bila dibutuhkan;
- server-side QR;
- document immutable hash;
- retention audit;
- HTTPS wajib;
- secure cookie;
- CORS allowlist;
- monitoring dan logging terpusat.

---

## 19. PRIORITAS CODEX

Prioritas 1:
- aplikasi jalan tanpa error.

Prioritas 2:
- login SSO dan middleware TTD benar.

Prioritas 3:
- registrasi TTD + PIN.

Prioritas 4:
- signing + QR + audit.

Prioritas 5:
- polish UI responsive.

Jangan over-engineer sebelum flow utama berhasil end-to-end.

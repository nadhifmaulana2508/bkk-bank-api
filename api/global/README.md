# Global API

Folder ini khusus untuk endpoint yang menjadi fondasi bersama semua aplikasi.
TTD tetap disiapkan sebagai area global karena dipakai oleh Monbis, Notaris,
dan aplikasi internal lainnya. Pada branch `develop`, route TTD dinonaktifkan
sementara karena database dan UI TTD belum menjadi dependency backend.

## Endpoint publik saat ini

```text
POST /api/auth/login
GET  /api/auth/whoami
POST /api/auth/whoami
POST /api/auth/change-password
POST /api/auth/update-profile
POST /api/auth/forgot-password
POST /api/auth/resend-otp
POST /api/auth/verify-otp
POST /api/auth/reset-password
```

Global API tidak menyimpan aturan bisnis spesifik Monbis atau Notaris. Ia
menyediakan identitas, akses, metadata bersama, dan kemampuan teknis umum.

Path di atas sengaja tetap menggunakan kontrak `/api/...` yang sudah dipakai
client. Perpindahan kode ke folder `api/global/` tidak mengubah URL, method,
atau format response.

## Rencana struktur implementasi

```text
api/global/
├── auth/
├── ttd/
├── master/
├── middleware/
└── README.md
```

Controller dan service dibuat setelah kontrak request/response disetujui.

Saat ini `api/global/routes.php` hanya mendaftarkan endpoint Auth ke
`ApiRouter`. Integrasi TTD tetap berada di `api/global/ttd/` untuk diaktifkan
setelah kontrak database/service TTD siap.

## Adapter route

Route publik tetap memakai path lama. Route tersebut boleh memanggil service
global yang sama, tetapi response-nya harus diserialisasi kembali ke format
yang sudah dipakai client.

## Dokumentasi Swagger

UI `ttd_in_bkk` tidak menjadi bagian branch backend ini. Endpoint TTD tidak
ditampilkan pada dokumentasi aktif sampai dependency database/service TTD siap.

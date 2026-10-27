# Global API

Folder ini khusus untuk endpoint yang menjadi fondasi bersama semua aplikasi.
TTD berada di area global karena dipakai oleh Monbis, Notaris, dan aplikasi
internal lainnya.

## Endpoint publik saat ini

```text
POST /api/auth/login
GET  /api/auth/whoami
POST /api/auth/whoami
POST /api/auth/change-password
POST /api/auth/update-profile
POST /api/auth/forgot-password
POST /api/auth/verify-otp
POST /api/auth/reset-password
GET  /api/ao/list
POST /api/ttd/status
POST /api/ttd/sign
```

Global API tidak menyimpan aturan bisnis spesifik Monbis atau Notaris. Ia
menyediakan identitas, akses, metadata bersama, TTD, dan kemampuan teknis
umum.

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

## Adapter route

Route publik tetap memakai path lama. Route tersebut boleh memanggil service
global yang sama, tetapi response-nya harus diserialisasi kembali ke format
yang sudah dipakai client.

## Dokumentasi Swagger

Jangan menampilkan `ttd_in_bkk/` sebagai grup API. Folder tersebut adalah UI
internal. Endpoint-nya didokumentasikan sebagai `Global TTD`.

# Global API

Folder ini khusus untuk endpoint yang menjadi fondasi bersama semua aplikasi.
TTD berada di area global karena dipakai oleh Monbis, Notaris, dan aplikasi
internal lainnya.

## Kandidat endpoint

```text
GET  /api/v1/health
POST /api/v1/auth/login
GET  /api/v1/auth/me
GET  /api/v1/global/scopes
POST /api/v1/ttd/status
POST /api/v1/ttd/sign
GET  /api/v1/ttd/verify/{token}
```

Global API tidak menyimpan aturan bisnis spesifik Monbis atau Notaris. Ia
menyediakan identitas, akses, metadata bersama, TTD, dan kemampuan teknis
umum.

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

## Legacy route

Route berikut tetap dipertahankan tanpa perubahan kontrak:

```text
POST /api/auth/login
GET  /api/auth/whoami
POST /api/ttd/status
POST /api/ttd/sign
```

Route legacy boleh memanggil service global yang sama, tetapi response-nya
harus diserialisasi kembali ke format yang sudah dipakai client lama.

## Dokumentasi Swagger

Jangan menampilkan `ttd_in_bkk/` sebagai grup API. Folder tersebut adalah UI
internal. Endpoint-nya didokumentasikan sebagai `Global TTD`.

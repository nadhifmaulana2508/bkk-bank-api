# Global API

Folder ini khusus untuk endpoint yang menjadi fondasi bersama semua aplikasi.

## Kandidat endpoint

```text
GET  /api/v1/health
POST /api/v1/auth/login
GET  /api/v1/auth/me
GET  /api/v1/global/scopes
```

Global API tidak menyimpan aturan bisnis spesifik Monbis atau Notaris. Ia hanya
menyediakan identitas, akses, metadata bersama, dan kemampuan teknis umum.

## Rencana struktur implementasi

```text
api/global/
├── Controllers/
├── Middleware/
├── Services/
└── README.md
```

Controller dibuat setelah kontrak request/response disetujui.

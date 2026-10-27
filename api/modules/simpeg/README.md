# Modul SIMPEG

SIMPEG menjadi sumber data pegawai, jabatan, unit kerja, dan cabang.

Modul ini tidak boleh membuat salinan password atau mengambil alih autentikasi
SSO. Integrasi dapat menggunakan API SIMPEG atau adapter database yang
terisolasi, tergantung sumber data yang tersedia.

## Endpoint pencarian pegawai

Endpoint ini memakai Bearer token dari login SSO dan mengembalikan struktur
data yang sama seperti `/api/auth/whoami`.

```text
GET /api/simpeg/pegawai/search?id_peg=<ID_PEGAWAI>
Authorization: Bearer <JWT_LOGIN>
```

Parameter `id_peg` wajib diisi. Endpoint tidak mengubah URL atau response
endpoint lama.

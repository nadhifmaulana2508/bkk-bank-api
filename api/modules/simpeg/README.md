# Modul SIMPEG

SIMPEG menjadi sumber data pegawai, jabatan, unit kerja, dan cabang.

Struktur routing modul:

```text
api/modules/simpeg/
├── routes.php
├── controllers/
│   ├── EmployeeController.php
│   └── AoController.php
├── EmployeeService.php
└── AoService.php
```

Route pusat hanya memuat `modules/simpeg/routes.php`; endpoint SIMPEG tidak
ditulis ulang di `api/routes.php`.

Modul ini tidak boleh membuat salinan password atau mengambil alih autentikasi
SSO. Integrasi dapat menggunakan API SIMPEG atau adapter database yang
terisolasi, tergantung sumber data yang tersedia.

## Endpoint pencarian pegawai

Endpoint ini tidak memakai token login. Endpoint mengembalikan field ringkas
untuk pilihan pegawai.

```text
GET /api/simpeg/pegawai/search?id_peg=<ID_PEGAWAI>
GET /api/simpeg/pegawai/search?nama=<NAMA_PEGAWAI>
```

Isi salah satu parameter `id_peg` atau `nama`. Hasil selalu berupa array
dengan field `employee_id` dan `full_name`, maksimal 50 data. Endpoint tidak
mengubah URL atau response endpoint lama.

## Endpoint AO

AO termasuk data SIMPEG. URL lama tetap dipertahankan dan membutuhkan Bearer
token:

```text
GET /api/simpeg/ao/list?type_ao=dana&kode_kantor=000
Authorization: Bearer <JWT_LOGIN>
```

Jika `kode_kantor=000`, API mengambil seluruh AO dari kantor `001` sampai
`028`.

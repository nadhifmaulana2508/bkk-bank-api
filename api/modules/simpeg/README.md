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
dengan field `employee_id`, `full_name`, `kode`, dan `job_position`, maksimal
50 data. Nama field mengikuti response `whoami` agar client konsisten.

## Direktori organisasi untuk KPI

Endpoint direktori berikut bersifat read-only dan wajib memakai header `App`
serta `X-App-Token` dari aplikasi terdaftar. Semua kode dan ID dikembalikan
sebagai string. Pagination memakai `page` (default `1`) dan `per_page`
(default `100`, maksimum `200`). Endpoint pegawai, penempatan, dan unit kerja
menerima `updated_since` dalam ISO 8601; perubahan dengan timestamp sama dapat
terulang, jadi client sebaiknya melakukan upsert berdasarkan ID/kode.

```text
GET /api/simpeg/jabatan
GET /api/simpeg/pegawai
GET /api/simpeg/penempatan
GET /api/simpeg/unit-kerja
```

- `jabatan` menggabungkan `tb_master_jabatan` dengan baris `tb_jabatan` berstatus
  `Aktif` melalui `kode_jabatan`. Satu baris dikembalikan per pemegang aktif;
  posisi kosong tetap muncul dengan field penempatan bernilai `null`. Sumber
  `tb_master_jabatan` belum memiliki timestamp perubahan, jadi data perlu
  disinkronkan penuh dengan pagination dan parameter `updated_since` ditolak.
- `pegawai` memakai `tb_pegawai.status_kepeg` sebagai `status_pegawai`,
  menyertakan kode sumber `status_aktif`, dan timestamp `updated_at`.
- `penempatan` mencakup riwayat penempatan, tanggal efektif, status, kode unit,
  serta metadata jabatan dan timestamp perubahan.
- `unit-kerja` memetakan level sumber `KP`, `KW`, `KC`, dan `KK` ke jenis unit
  `PUSAT`, `KANWIL`, `KC`, dan `KK`. Tabel sumber `tb_kantor` belum memiliki
  kolom parent unit.

Response memakai envelope API biasa: array data di `data` dan informasi
`page`, `per_page`, `total`, `total_pages`, `has_more`, serta filter di `meta`.
Relasi jabatan mengikuti `tb_master_jabatan.parent_kode_jabatan`; pemegang aktif
berasal dari `tb_jabatan` dan menyertakan `kode_unit`. Karena satu kode jabatan
bisa punya beberapa pemegang aktif di unit berbeda dan `tb_kantor` belum punya
parent unit, response mengirim semua pemegang aktif agar KPI menerapkan aturan
penilai tanpa menebak pemegang lintas unit.

## Mengubah status login SSO

```text
POST /api/simpeg/pegawai/status
```

Kirim header `App` dan `X-App-Token` dari aplikasi aktif mana pun yang sudah
terdaftar di Registry, lalu body JSON berikut:

```json
{
  "id_peg": "102-119",
  "status": "non job"
}
```

Nilai `"0"` atau `"non job"` memblokir login dan request dengan JWT yang sudah
terbit. Nilai `"1"` mengaktifkan login kembali. Response menyertakan `nama`
pegawai dari `tb_pegawai` serta `can_login`.

## Endpoint AO

AO termasuk data SIMPEG. URL lama tetap dipertahankan dan membutuhkan Bearer
token:

```text
GET /api/simpeg/ao/list?type_ao=dana&kode_kantor=000
Authorization: Bearer <JWT_LOGIN>
```

Jika `kode_kantor=000`, API mengambil seluruh AO dari kantor `001` sampai
`028`.

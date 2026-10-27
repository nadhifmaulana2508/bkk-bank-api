# Konsep BKK Central API

Status: draft fondasi

## Tujuan

Menyediakan satu lapisan API untuk integrasi SIMPEG, TTD, Monbis, dan Notaris
tanpa langsung mencampur seluruh aturan bisnis ke dalam satu controller besar.

Branch ini hanya menyiapkan konsep dan struktur. Endpoint lama tetap berjalan
seperti sebelumnya sampai migrasi dilakukan secara bertahap.

## Batas domain awal

| Domain | Tanggung jawab | Sumber data utama |
| --- | --- | --- |
| Global | autentikasi, identitas aktif, cabang, kesehatan API, audit | SSO/API pusat |
| SIMPEG | pegawai, jabatan, unit kerja, cabang | SIMPEG |
| Global TTD | status TTD, signing, hash, verifikasi lintas aplikasi | database TTD |
| Monbis | permintaan dan status proses bisnis | Monbis |
| Notaris | permintaan dokumen dan pertukaran status dengan notaris | Notaris |

## Prinsip integrasi

1. `api/` adalah lapisan API pusat yang baru.
2. `api/global/` berisi endpoint yang dipakai lintas aplikasi, termasuk TTD.
3. Setiap domain bisnis memiliki controller, service, dan adapter sendiri.
4. SIMPEG menjadi sumber data pegawai. Data pegawai tidak disalin ke semua
   modul tanpa alasan yang jelas.
5. TTD menjadi global service karena dipakai Monbis, Notaris, dan aplikasi
   internal lain. Database TTD yang sudah ada tetap digunakan melalui adapter.
6. Endpoint lama dianggap legacy dan tidak dihapus selama migrasi.
7. Semua endpoint baru memakai prefix `/api/v1`.
8. Autentikasi pengguna dan autentikasi aplikasi dipisahkan.

## Kontrak kompatibilitas endpoint lama

Endpoint yang sudah dipakai sistem lain dianggap kontrak publik dan tidak boleh
diubah secara diam-diam.

Untuk endpoint lama, hal berikut wajib dipertahankan:

- path dan HTTP method;
- nama field request;
- bentuk response JSON;
- nama field response;
- HTTP status code;
- aturan autentikasi yang sedang dipakai;
- perilaku error yang sudah dikonsumsi oleh client.

Jika kode lama ingin dipindahkan ke service baru, gunakan adapter:

```text
Client lama
    -> route lama
    -> legacy adapter
    -> global service baru
    -> legacy response serializer
    -> response lama
```

Perubahan format hanya boleh dilakukan pada endpoint baru atau versi baru,
misalnya `/api/v1/...`. Endpoint lama tidak diarahkan ulang ke format baru
sebelum seluruh client menyatakan siap.

## Struktur target

```text
api/
├── routes.php
├── global/
│   ├── auth/
│   ├── ttd/
│   ├── master/
│   ├── middleware/
│   └── README.md
├── legacy/
│   ├── AuthController.php
│   ├── AoController.php
│   └── README.md
├── modules/
│   ├── simpeg/
│   ├── monbis/
│   └── notaris/
├── shared/
│   ├── Database/
│   ├── Http/
│   ├── Auth/
│   └── Support/
└── README.md
```

Folder di atas adalah batas tanggung jawab. Implementasi folder detail dibuat
saat domain tersebut mulai dikerjakan.

## Rute target

```text
/api/v1/health
/api/v1/auth/login
/api/v1/auth/me
/api/v1/simpeg/pegawai
/api/v1/simpeg/cabang
/api/v1/simpeg/jabatan
/api/v1/ttd/status
/api/v1/ttd/sign
/api/v1/ttd/verify
/api/v1/monbis/requests
/api/v1/notaris/requests
/api/v1/notaris/requests/{id}/status
```

Nama rute masih berupa kontrak awal. Payload final harus disepakati sebelum
controller dan query dibuat.

## Pemetaan endpoint lama

| Endpoint lama | Rencana tujuan | Perlakuan |
| --- | --- | --- |
| `POST /api/auth/login` | `POST /api/v1/auth/login` | route lama dan response lama wajib tetap |
| `GET/POST /api/auth/whoami` | `GET /api/v1/auth/me` | route lama dan response lama wajib tetap |
| `GET /api/ao/list` | `GET /api/v1/simpeg/ao` | route lama dan response lama wajib tetap |
| `POST /api/ttd/status` | `POST /api/v1/ttd/status` | route lama memakai adapter global TTD |
| `POST /api/ttd/sign` | `POST /api/v1/ttd/sign` | route lama memakai adapter global TTD |

## Posisi `ttd_in_bkk/`

`ttd_in_bkk/` diperlakukan sebagai aplikasi web internal untuk registrasi,
administrasi, dan verifikasi TTD. Folder tersebut bukan domain API terpisah.

Karena itu:

- tidak dibuat grup Swagger bernama `TTD IN BKK`;
- endpoint TTD ditampilkan di grup `Global API` atau tag `Global TTD`;
- UI internal tetap boleh memakai service TTD global;
- kode domain TTD baru diarahkan ke `api/global/ttd/` secara bertahap;
- URL UI lama tidak diputus selama masih dipakai.

## Model autentikasi

### Pengguna

Pegawai login melalui SSO dan menerima token dengan identitas pegawai serta
scope aplikasi yang diizinkan.

### Aplikasi

Monbis dan Notaris memakai kredensial aplikasi terpisah. Setiap client memiliki
scope minimum, misalnya:

```text
simpeg.read
ttd.status
ttd.sign
monbis.request.write
notaris.request.read
```

Kredensial aplikasi tidak boleh memakai password pegawai.

## Alur dokumen awal

```text
Monbis/Notaris mengirim request
        -> API validasi client dan payload
        -> API cek identitas pegawai melalui SIMPEG
        -> API cek status TTD
        -> proses signing melalui service TTD
        -> API menyimpan status, hash, dan audit
        -> client menerima status atau callback
```

## Tahapan implementasi

1. Menetapkan kontrak response, error, authentication, dan correlation ID.
2. Membuat router `/api/v1` tanpa mengubah router legacy.
3. Membuat endpoint global: health, auth/me, cabang, dan pegawai.
4. Membungkus integrasi TTD sebagai service yang dapat dipakai Monbis dan
   Notaris.
5. Menetapkan workflow dokumen Monbis.
6. Menetapkan workflow pertukaran dokumen dengan Notaris.
7. Menambahkan dokumentasi OpenAPI dan pengujian integrasi.

## Keputusan yang masih diperlukan

- Apakah Monbis dan Notaris sudah memiliki API atau hanya database?
- Siapa yang menjadi pemilik final dokumen: Monbis, Notaris, atau API pusat?
- Apakah hasil signing dikirim melalui callback atau diambil dengan polling?
- Scope apa yang boleh melakukan signing atas nama aplikasi?

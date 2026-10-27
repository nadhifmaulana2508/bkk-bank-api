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
| TTD | status TTD, signing, hash, verifikasi | database TTD |
| Monbis | permintaan dan status proses bisnis | Monbis |
| Notaris | permintaan dokumen dan pertukaran status dengan notaris | Notaris |

## Prinsip integrasi

1. `api/` adalah lapisan API pusat yang baru.
2. `api/global/` berisi endpoint yang dipakai lintas aplikasi.
3. Setiap domain memiliki controller, service, dan adapter sendiri.
4. SIMPEG menjadi sumber data pegawai. Data pegawai tidak disalin ke semua
   modul tanpa alasan yang jelas.
5. TTD tetap menggunakan database TTD yang sudah ada.
6. Endpoint lama dianggap legacy dan tidak dihapus selama migrasi.
7. Semua endpoint baru memakai prefix `/api/v1`.
8. Autentikasi pengguna dan autentikasi aplikasi dipisahkan.

## Struktur target

```text
api/
├── global/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Services/
│   └── README.md
├── modules/
│   ├── simpeg/
│   ├── ttd/
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
| `POST /api/auth/login` | `POST /api/v1/auth/login` | dipertahankan, lalu dibuat adapter |
| `GET/POST /api/auth/whoami` | `GET /api/v1/auth/me` | dipertahankan selama masa migrasi |
| `GET /api/ao/list` | `GET /api/v1/simpeg/ao` | dipindah setelah kontrak disepakati |
| `POST /api/ttd/status` | `POST /api/v1/ttd/status` | memakai service TTD yang sama |
| `POST /api/ttd/sign` | `POST /api/v1/ttd/sign` | memakai service TTD yang sama |

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

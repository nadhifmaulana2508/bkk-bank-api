# Panduan Testing API Setelah Migrasi

Dokumen ini untuk menguji migrasi struktur API pada branch
`feature/global-sso-migration`.

Migrasi hanya memindahkan implementasi internal. Endpoint yang sudah dipakai
client tetap menggunakan path dan response lama.

## 1. Posisi kode setelah migrasi

~~~text
Client
  -> index.php
  -> api/routes.php
  -> api/global/routes.php atau api/modules/*/routes.php
  -> controller module
  -> service domain
~~~

Contoh SSO:

~~~text
/api/auth/login
  -> api/global/routes.php
  -> api/legacy/AuthController.php
  -> api/global/auth/SsoService.php
~~~

## 2. Base URL dan prasyarat

Base URL lokal:

~~~text
http://localhost/rest_api_sso
~~~

Pastikan:

- Apache dan MySQL XAMPP aktif;
- `.env` root tersedia dan koneksi database dapat digunakan;
- memiliki `id_peg`, password, dan nama aplikasi yang memang diizinkan;

Dokumentasi interaktif:

~~~text
http://localhost/rest_api_sso/docs/
~~~

Production:

~~~text
https://apisso.bkkjateng.co.id
https://apisso.bkkjateng.co.id/docs/
~~~

Untuk testing branch ini, tetap gunakan endpoint publik `/api/...`. Migrasi
hanya memindahkan implementasi internal dan tidak mengubah URL endpoint.

## App token global

Setiap aplikasi pemanggil API wajib memiliki registry dan token aplikasi sendiri.
Dokumentasi setup ada di [APP_TOKEN_AUTH.md](APP_TOKEN_AUTH.md). Header yang
digunakan:

~~~http
App: simpeg
X-App-Token: app_<token-rahasia>
~~~

`Authorization: Bearer <JWT>` tetap khusus token login pegawai. Jangan memakai
`Authorization` untuk token aplikasi karena satu request dapat membutuhkan dua
kredensial berbeda.

Saat `APP_TOKEN_REQUIRED=false`, endpoint lama masih dapat diuji tanpa app
token. Pada URL development baru, set `APP_TOKEN_REQUIRED=true` setelah tabel
`api_applications` dan token aplikasi selesai disiapkan.

## 3. Rekomendasi alat dan proteksi

Gunakan halaman dokumentasi interaktif sebagai alat utama karena request, schema, response, dan
tombol `Authorize` sudah tersedia di satu halaman. Postman atau PowerShell
dapat dipakai sebagai alternatif untuk pengujian berulang atau otomatisasi.

Halaman dokumentasi memakai session yang sama dengan SSO Control Plane. User
harus memiliki permission docs yang sesuai; tidak ada login kode dokumentasi
kedua.

Checklist deployment keamanan lengkap tersedia di
`docs/SECURITY_DEPLOYMENT.md`.

Setelah masuk ke halaman dokumentasi:

1. Kirim header `App` dan `X-App-Token` bila `APP_TOKEN_REQUIRED=true`.
2. Jalankan `POST /api/auth/login` menggunakan `id_peg`, `password`, dan `app`.
3. Salin nilai `data.token` dari response login.
4. Klik `Authorize`, masukkan token JWT, lalu klik `Authorize` kembali.
5. Jalankan endpoint protected seperti `whoami`, profile, dan AO.

Endpoint login tidak memakai Bearer karena endpoint tersebut menghasilkan
token. Endpoint reset password memakai token OTP/reset sesuai alurnya.

## 4. Test login SSO

### Request

~~~http
POST /api/auth/login
Content-Type: application/json
~~~

~~~json
{
  "id_peg": "<ID_PEGAWAI>",
  "password": "<PASSWORD_PEGAWAI>",
  "app": "simpeg"
}
~~~

PowerShell:

~~~powershell
$baseUrl = "http://localhost/rest_api_sso"
$appHeaders = @{ App = "simpeg"; "X-App-Token" = "<APP_TOKEN>" }
$login = Invoke-RestMethod -Method Post -Uri "$baseUrl/api/auth/login" -Headers $appHeaders -ContentType "application/json" -Body (@{ id_peg = "<ID_PEGAWAI>"; password = "<PASSWORD_PEGAWAI>"; app = "simpeg" } | ConvertTo-Json)
$token = $login.data.token
$token
~~~

Response sukses yang harus tetap dipertahankan:

~~~json
{
  "status": 200,
  "message": "Login berhasil",
  "data": {
    "token": "<JWT>"
  }
}
~~~

### Negative test

| Skenario | Expected |
| --- | --- |
| `app` kosong | HTTP 400, `App wajib diisi (monbis, simpeg, dll)` |
| ID pegawai tidak ditemukan | HTTP 401, `User tidak ditemukan` |
| password salah | HTTP 401, `Password salah` |
| aplikasi tidak terdaftar | HTTP 400, `App tidak valid` |
| pegawai tidak punya akses aplikasi | HTTP 403 |

## 5. Test endpoint protected

### Whoami

~~~powershell
$headers = @{ App = "simpeg"; "X-App-Token" = "<APP_TOKEN>"; Authorization = "Bearer $token" }
Invoke-RestMethod -Method Get -Uri "$baseUrl/api/auth/whoami" -Headers $headers
~~~

Expected:

- HTTP 200;
- `message` bernilai `OK`;
- `data.employee_id`, `data.full_name`, `data.branch_name`, dan data jabatan
  tersedia sesuai pegawai.

### Test tanpa token

~~~powershell
Invoke-RestMethod -Method Get -Uri "$baseUrl/api/auth/whoami"
~~~

Expected:

~~~json
{
  "status": 401,
  "message": "Unauthorized",
  "data": null
}
~~~

### Test token tidak valid

Gunakan header:

~~~text
Authorization: Bearer token-tidak-valid
~~~

Expected: HTTP 401 dengan message `Invalid token`.

## 6. Test endpoint AO

~~~powershell
Invoke-RestMethod -Method Get -Uri "$baseUrl/api/simpeg/ao/list?type_ao=dana&kode_kantor=000" -Headers $headers
~~~

Expected:

- HTTP 200;
- `data` berupa array;
- `job_position` sesuai tipe AO;
- message mengikuti format lama, misalnya
  `Berhasil mengambil data AO DANA`.

Negative test:

~~~text
/api/simpeg/ao/list
/api/simpeg/ao/list?type_ao=lain&kode_kantor=000
/api/simpeg/ao/list?type_ao=dana&kode_kantor=000
/api/simpeg/ao/list?type_ao=dana
~~~

Nilai `kode_kantor=000` mengambil AO dari kantor `001` sampai `028`. Expected negative test:
HTTP 400 dengan response JSON.

## 7. Test search pegawai SIMPEG

Endpoint ini tidak membutuhkan token login pegawai, tetapi tetap membutuhkan
app token ketika `APP_TOKEN_REQUIRED=true`. Gunakan `id_peg` untuk pencarian
spesifik atau `nama` untuk pencarian sebagian nama. Setiap hasil berisi
`employee_id`, `full_name`, `kode`, dan `job_position` seperti response
`whoami`.

~~~powershell
$appHeaders = @{ App = "simpeg"; "X-App-Token" = "<APP_TOKEN>" }
$encodedIdPeg = [uri]::EscapeDataString("<ID_PEGAWAI>")
Invoke-RestMethod -Method Get -Uri "$baseUrl/api/simpeg/pegawai/search?id_peg=$encodedIdPeg" -Headers $appHeaders

$encodedName = [uri]::EscapeDataString("Nama Pegawai")
Invoke-RestMethod -Method Get -Uri "$baseUrl/api/simpeg/pegawai/search?nama=$encodedName" -Headers $appHeaders
~~~

Expected:

~~~json
{
  "status": 200,
  "message": "OK",
  "data": [
    {
      "employee_id": "<ID_PEGAWAI>",
      "full_name": "Nama Pegawai",
      "kode": "001",
      "job_position": "Staf"
    }
  ]
}
~~~

Negative test:

| Skenario | Expected |
| --- | --- |
| `id_peg` dan `nama` kosong | HTTP 400 |
| `id_peg` tidak ditemukan | HTTP 200 dengan `data: []` |
| `nama` tidak ditemukan | HTTP 200 dengan `data: []` |

## 8. Test endpoint profil dan password

Semua endpoint berikut memakai header Bearer dari login:

~~~text
POST /api/auth/change-password
POST /api/auth/update-profile
~~~

`change-password` dan `update-profile` mengubah data nyata. Jalankan hanya pada
akun testing dan simpan nilai lama untuk pemulihan.

Reset password terdiri dari tahap kirim OTP, verifikasi OTP, dan ubah password.
Resend bersifat opsional:

~~~text
POST /api/auth/forgot-password
POST /api/auth/resend-otp
POST /api/auth/verify-otp
POST /api/auth/reset-password
~~~

Panggil `/api/auth/resend-otp` dengan email yang sama jika OTP belum diterima
atau sudah kadaluarsa. Endpoint tersebut menghasilkan `otp_token` baru, jadi
gunakan token terbaru saat memanggil `/api/auth/verify-otp`.

Tahap ini mengirim email dan mengubah password. Sebaiknya dijalankan di
environment development/staging dengan mailbox testing.

## 9. Verifikasi khusus migrasi

Checklist:

- [ ] URL endpoint lama tetap dapat dipanggil.
- [ ] Response login legacy tetap memiliki `status`, `message`, dan `data.token`.
- [ ] Token yang dibuat dengan secret legacy tetap dapat dipakai.
- [ ] `/api/auth/whoami` tetap menerima Bearer token.
- [ ] `/api/simpeg/ao/list` mengembalikan envelope response AO.
- [ ] `/api/simpeg/pegawai/search` dapat dicari memakai `id_peg` atau `nama`
      dengan app token dan mengembalikan field yang konsisten dengan `whoami`.
- [ ] Client lama tetap memanggil endpoint `/api/...` tanpa perubahan URL.

## 10. Pemeriksaan lokal tanpa request bisnis

Dari root project:

~~~powershell
C:\xampp\php\php.exe -l index.php
C:\xampp\php\php.exe -l api/routes.php
C:\xampp\php\php.exe -l api/global/auth/SsoService.php
C:\xampp\php\php.exe -l api/modules/simpeg/EmployeeService.php
C:\xampp\php\php.exe -l api/modules/simpeg/EmployeeController.php
~~~

Validasi kontrak OpenAPI JSON:

~~~powershell
C:\xampp\php\php.exe -r "json_decode(file_get_contents('swagger.json')); exit(json_last_error() === JSON_ERROR_NONE ? 0 : 1);"
~~~

## Referensi

- [OpenAPI JSON](../swagger.json)
- [Konsep Central API](architecture/API_CENTRAL_CONCEPT.md)


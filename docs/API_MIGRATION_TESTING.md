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
  -> api/legacy/*
  -> api/global/*
~~~

Contoh SSO:

~~~text
/api/v1/auth/login
  -> api/legacy/AuthController.php
  -> api/global/auth/SsoService.php
~~~

Contoh TTD:

~~~text
/api/v1/ttd/sign
  -> api/global/ttd/TtdApiController.php
~~~

## 2. Base URL dan prasyarat

Base URL lokal:

~~~text
http://localhost/rest_api_sso
~~~

Pastikan:

- Apache dan MySQL XAMPP aktif;
- `.env` root tersedia dan koneksi database dapat digunakan;
- database TTD tersedia untuk testing endpoint TTD;
- memiliki `id_peg`, password, dan nama aplikasi yang memang diizinkan;
- password endpoint TTD disiapkan melalui `TTD_API_PASSWORD`.

Swagger:

~~~text
http://localhost/rest_api_sso/docs/
~~~

Endpoint canonical hasil migrasi menggunakan prefix `/api/v1/...`. Endpoint
legacy `/api/...` tetap tersedia untuk client lama.

## 3. Test login SSO

### Request

~~~http
POST /api/v1/auth/login
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
$login = Invoke-RestMethod -Method Post -Uri "$baseUrl/api/v1/auth/login" -ContentType "application/json" -Body (@{ id_peg = "<ID_PEGAWAI>"; password = "<PASSWORD_PEGAWAI>"; app = "simpeg" } | ConvertTo-Json)
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

## 4. Test endpoint protected

### Whoami

~~~powershell
$headers = @{ Authorization = "Bearer $token" }
Invoke-RestMethod -Method Get -Uri "$baseUrl/api/v1/auth/whoami" -Headers $headers
~~~

Expected:

- HTTP 200;
- `message` bernilai `OK`;
- `data.employee_id`, `data.full_name`, `data.branch_name`, dan data jabatan
  tersedia sesuai pegawai.

### Test tanpa token

~~~powershell
Invoke-RestMethod -Method Get -Uri "$baseUrl/api/v1/auth/whoami"
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

## 5. Test endpoint AO

~~~powershell
Invoke-RestMethod -Method Get -Uri "$baseUrl/api/v1/ao/list?tipe=dana&kode_cabang=000" -Headers $headers
~~~

Expected:

- HTTP 200;
- `data` berupa array;
- `job_position` sesuai tipe AO;
- message mengikuti format lama, misalnya
  `Berhasil mengambil data AO DANA`.

Negative test:

~~~text
/api/v1/ao/list
/api/v1/ao/list?tipe=lain&kode_cabang=000
/api/v1/ao/list?tipe=dana
~~~

Expected: HTTP 400 dengan response JSON.

## 6. Test Global TTD

Endpoint TTD saat ini tidak memakai Bearer token. Setiap request memakai
`pass_endpoint` pada JSON body.

### Cek status TTD

~~~powershell
$ttdBody = @{ id_peg = "<ID_PEGAWAI>"; nama_aplikasi = "simpeg"; pass_endpoint = "<TTD_API_PASSWORD>" } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "$baseUrl/api/v1/ttd/status" -ContentType "application/json" -Body $ttdBody
~~~

Expected jika aktif:

~~~text
HTTP 200
data.code = SIGNATURE_ACTIVE
data.active = true
data.can_sign = true
~~~

Expected jika belum terdaftar:

~~~text
HTTP 200
data.code = SIGNATURE_NOT_REGISTERED
data.active = false
~~~

### Tanda tangan dokumen

~~~powershell
$signBody = @{ id_peg = "<ID_PEGAWAI>"; input_pin = "<PIN_TTD_6_DIGIT>"; nama_aplikasi = "simpeg"; pass_endpoint = "<TTD_API_PASSWORD>"; document_id = "TEST-MIGRATION-001"; document_name = "Dokumen Test Migrasi API" } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "$baseUrl/api/v1/ttd/sign" -ContentType "application/json" -Body $signBody
~~~

Expected jika berhasil:

- HTTP 200;
- `data.code = SIGNATURE_SUCCESS`;
- `data.document_id = TEST-MIGRATION-001`;
- `data.document_hash` tersedia;
- `data.verification_url` tersedia;
- `data.signed_at` tersedia.

Negative test:

| Skenario | Expected |
| --- | --- |
| `pass_endpoint` salah | HTTP 401, `INVALID_ENDPOINT_PASSWORD` |
| PIN bukan 6 digit | HTTP 422, `INVALID_PIN_FORMAT` |
| TTD belum aktif | HTTP 409, `SIGNATURE_NOT_REGISTERED` |
| PIN salah | HTTP 401, `INVALID_SIGNATURE_PIN` |
| PIN salah lima kali | HTTP 423, `SIGNATURE_PIN_LOCKED` |
| hash bukan SHA-256 64 karakter | HTTP 422, `INVALID_DOCUMENT_HASH` |

Jangan memakai data tanda tangan pegawai sungguhan untuk test berulang. Gunakan
pegawai dan database TTD khusus testing bila tersedia.

## 7. Test endpoint profil dan password

Semua endpoint berikut memakai header Bearer dari login:

~~~text
POST /api/v1/auth/change-password
POST /api/v1/auth/update-profile
~~~

`change-password` dan `update-profile` mengubah data nyata. Jalankan hanya pada
akun testing dan simpan nilai lama untuk pemulihan.

Reset password terdiri dari tiga tahap:

~~~text
POST /api/v1/auth/forgot-password
POST /api/v1/auth/verify-otp
POST /api/v1/auth/reset-password
~~~

Tahap ini mengirim email dan mengubah password. Sebaiknya dijalankan di
environment development/staging dengan mailbox testing.

## 8. Verifikasi khusus migrasi

Checklist:

- [ ] URL endpoint lama tetap dapat dipanggil.
- [ ] Response login `/api/v1` tetap memiliki `status`, `message`, dan `data.token`.
- [ ] Token yang dibuat dengan secret legacy tetap dapat dipakai.
- [ ] `/api/v1/auth/whoami` tetap menerima Bearer token.
- [ ] `/api/v1/ao/list` tetap mengembalikan envelope response lama.
- [ ] `/api/v1/ttd/status` dan `/api/v1/ttd/sign` tetap memakai `pass_endpoint`.
- [ ] Endpoint lama `/api/...` tetap dapat dipanggil client lama.
- [ ] Swagger menampilkan grup `Global TTD`, bukan grup `TTD IN BKK`.
- [ ] `ttd_in_bkk/` tetap dapat dibuka sebagai UI internal.

## 9. Pemeriksaan lokal tanpa request bisnis

Dari root project:

~~~powershell
C:\xampp\php\php.exe -l index.php
C:\xampp\php\php.exe -l api/routes.php
C:\xampp\php\php.exe -l api/global/auth/SsoService.php
C:\xampp\php\php.exe -l api/global/ttd/TtdApiController.php
~~~

Validasi Swagger JSON:

~~~powershell
C:\xampp\php\php.exe -r "json_decode(file_get_contents('swagger.json')); exit(json_last_error() === JSON_ERROR_NONE ? 0 : 1);"
~~~

## Referensi

- [Swagger JSON](../swagger.json)
- [Dokumentasi TTD](TTD_API.md)
- [Konsep Central API](architecture/API_CENTRAL_CONCEPT.md)


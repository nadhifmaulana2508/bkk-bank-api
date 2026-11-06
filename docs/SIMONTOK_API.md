# Dokumentasi API Simontok

Dokumentasi ini menjelaskan endpoint untuk mencari rekening debitur, membaca detail kredit, dan mengambil seluruh agunan rekening.

## URL dokumentasi

```text
http://localhost/rest_api_sso/docs/simontok
https://devsso.bkkjateng.co.id/docs/simontok
```

Halaman dokumentasi memakai session Control Plane yang sama. User harus memiliki permission `docs.simontok`.

## Sumber data

- Data rekening dan agunan dibaca dari database `db_bank_api`.
- Master cabang dibaca dari database `dpk`, tabel `kode_kantor`.
- Nama tabel database tidak dikirim ke aplikasi pemanggil.

## Header request

Semua endpoint membutuhkan kredensial aplikasi jika `APP_TOKEN_REQUIRED=true`:

```http
App: simontok
X-App-Token: <TOKEN_APLIKASI_SIMONTOK>
```

Endpoint rekening juga membutuhkan JWT pegawai:

```http
Authorization: Bearer <JWT_HASIL_API_AUTH_LOGIN>
```

Kode aplikasi pada JWT harus sama dengan nilai header `App`.

## Endpoint

### Cari rekening

```http
GET /api/v1/simontok/credit-accounts?query={keyword}&branch_code={kode_cabang}&limit=20
```

`query` dapat berisi nomor rekening atau nama nasabah dan minimal 3 karakter. `limit` bernilai 20 secara default dan dibatasi maksimal 50.

```json
{
  "status": 200,
  "message": "OK",
  "data": [
    {
      "account_id": "identifier-dari-api",
      "account_number": "001234567890",
      "customer_id": "CORE-CUSTOMER-456",
      "customer_name": "Nama Nasabah",
      "branch": {
        "code": "001",
        "name": "Kantor Cabang Contoh",
        "is_active": true
      },
      "selectable": true
    }
  ],
  "meta": {
    "limit": 20,
    "count": 1
  }
}
```

Nomor rekening selalu dikirim sebagai string agar angka nol di depan tetap tersimpan.

### Detail rekening

```http
GET /api/v1/simontok/credit-accounts/{account_id}
```

`account_id` harus memakai nilai dari response pencarian. Detail berisi nasabah, cabang, plafon, tanggal realisasi, dan seluruh agunan.

```json
{
  "status": 200,
  "message": "OK",
  "data": {
    "account_id": "identifier-dari-api",
    "account_number": "001234567890",
    "customer": {
      "customer_id": "CORE-CUSTOMER-456",
      "name": "Nama Nasabah"
    },
    "branch": {
      "code": "001",
      "name": "Kantor Cabang Contoh",
      "is_active": true
    },
    "credit": {
      "loan_amount": "100000000.00",
      "realization_date": "2026-11-03"
    },
    "collaterals": []
  }
}
```

Nilai `credit.loan_amount` berasal dari `list_kredit.plafon` dan dikirim sebagai string untuk menjaga presisi angka keuangan.

### Daftar agunan

```http
GET /api/v1/simontok/credit-accounts/{account_id}/collaterals
```

Endpoint ini mengembalikan seluruh agunan rekening. Jika satu rekening memiliki lima agunan, array `data` berisi lima objek agunan.

```json
{
  "status": 200,
  "message": "OK",
  "data": [
    {
      "collateral_id": "AG-000123",
      "collateral_code": "AG-000123",
      "type_code": "TANAH_BANGUNAN",
      "type_name": "Tanah dan Bangunan",
      "description": "Deskripsi agunan",
      "legal_reference": "SHM-123",
      "value": "100000000.00",
      "account_id": "identifier-dari-api"
    }
  ],
  "meta": {
    "count": 1
  }
}
```

### Master cabang

```http
GET /api/v1/simontok/branches?active=true
```

Endpoint ini hanya membutuhkan token aplikasi. Nilai `code` berasal dari `dpk.kode_kantor`.

## Aturan akses cabang

- User cabang hanya dapat mencari rekening pada kode cabangnya sendiri.
- User pusat dengan kode cabang `000` dapat mencari lintas cabang jika parameter `branch_code` dikosongkan.
- User pusat dapat membatasi hasil dengan `branch_code` tertentu.
- API tetap memeriksa cabang dari JWT di backend. Filter dari frontend tidak dianggap sebagai otorisasi.

## Contoh testing PowerShell

```powershell
$baseUrl = "http://localhost/rest_api_sso"
$headers = @{
  App = "simontok"
  "X-App-Token" = "<TOKEN_APLIKASI_SIMONTOK>"
  Authorization = "Bearer <JWT_HASIL_LOGIN>"
}

Invoke-RestMethod `
  -Method Get `
  -Uri "$baseUrl/api/v1/simontok/credit-accounts?query=001&limit=20" `
  -Headers $headers
```

Gunakan `account_id` dari hasil pencarian untuk menguji detail dan daftar agunan. Token aplikasi dan JWT jangan ditulis ke source code atau repository.

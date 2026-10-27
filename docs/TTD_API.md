# REST API TTD IN BKK

Untuk urutan pengujian seluruh endpoint setelah migrasi, lihat
[Panduan Testing API Setelah Migrasi](API_MIGRATION_TESTING.md).

Endpoint integrasi ini tidak menggunakan Bearer token. Setiap request wajib mengirim `pass_endpoint` pada JSON body.

Base URL lokal:

```text
http://localhost/rest_api_sso
```

Dokumentasi Swagger tersedia pada:

```text
http://localhost/rest_api_sso/docs/
```

## Cek status TTD

Canonical migration endpoint:

`POST /api/v1/ttd/status`

Legacy endpoint `/api/ttd/status` tetap tersedia untuk client lama.

Request:

```json
{
  "id_peg": "102-119",
  "nama_aplikasi": "simpeg",
  "pass_endpoint": "bkkjateng123"
}
```

Jika pegawai belum memiliki TTD aktif, response tetap JSON dan memiliki `code` `SIGNATURE_NOT_REGISTERED`.

## Tanda tangani dokumen

Canonical migration endpoint:

`POST /api/v1/ttd/sign`

Legacy endpoint `/api/ttd/sign` tetap tersedia untuk client lama.

Field wajib:

```json
{
  "id_peg": "102-119",
  "input_pin": "123456",
  "nama_aplikasi": "simpeg",
  "pass_endpoint": "bkkjateng123"
}
```

Field dokumen opsional:

```json
{
  "document_id": "SURAT-2026-0001",
  "document_name": "Nota Persetujuan Internal",
  "document_hash": "sha256-64-karakter-jika-sudah-tersedia"
}
```

Contoh `curl`:

```bash
curl -X POST "http://localhost/rest_api_sso/api/v1/ttd/sign" \
  -H "Content-Type: application/json" \
  -d '{
    "id_peg": "102-119",
    "input_pin": "123456",
    "nama_aplikasi": "simpeg",
    "pass_endpoint": "bkkjateng123",
    "document_id": "SURAT-2026-0001",
    "document_name": "Nota Persetujuan Internal"
  }'
```

Response berhasil mengandung:

- `signature_url`: URL gambar TTD;
- `verification_url`: URL halaman verifikasi;
- `qr_content`: nilai yang dapat dibuat menjadi QR Code;
- `document_hash`: SHA-256 yang tercatat;
- `signed_at`: waktu signing.

Jika TTD belum terdaftar, endpoint mengembalikan HTTP `409` dengan `code` `SIGNATURE_NOT_REGISTERED` dan `register_url` menuju halaman registrasi TTD.

## Konfigurasi

Password endpoint dapat diubah melalui environment server `TTD_API_PASSWORD`. URL aplikasi TTD dapat diubah melalui `TTD_APP_URL`.

Untuk lokal, default-nya adalah:

```env
TTD_API_PASSWORD=bkkjateng123
TTD_APP_URL=http://localhost/rest_api_sso/ttd_in_bkk
```

Endpoint ini sengaja dibuat tanpa token sesuai kebutuhan integrasi awal. Untuk production, gunakan HTTPS, batasi IP aplikasi pemanggil, dan ganti password endpoint dari nilai default.

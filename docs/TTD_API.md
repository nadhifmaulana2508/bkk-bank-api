# REST API TTD IN BKK

> Status branch `develop`: endpoint TTD dinonaktifkan sementara. Dokumen ini
> hanya menjadi kontrak migrasi dan belum dapat dites sampai dependency database
> TTD tersedia sebagai service backend.

Untuk urutan pengujian seluruh endpoint setelah migrasi, lihat
[Panduan Testing API Setelah Migrasi](API_MIGRATION_TESTING.md).

Saat diaktifkan, endpoint integrasi ini menggunakan kontrak lama: request wajib
mengirim `pass_endpoint` pada JSON body. Jangan menaruh password endpoint asli
di dokumentasi atau repository.

Base URL lokal:

```text
http://localhost/rest_api_sso
```

Dokumentasi API tersedia pada:

```text
http://localhost/rest_api_sso/docs/
```

## Cek status TTD

`POST /api/ttd/status`

Request:

```json
{
  "id_peg": "102-119",
  "nama_aplikasi": "simpeg",
  "pass_endpoint": "<TTD_API_PASSWORD>"
}
```

Jika pegawai belum memiliki TTD aktif, response tetap JSON dan memiliki `code` `SIGNATURE_NOT_REGISTERED`.

## Tanda tangani dokumen

`POST /api/ttd/sign`

Field wajib:

```json
{
  "id_peg": "102-119",
  "input_pin": "123456",
  "nama_aplikasi": "simpeg",
  "pass_endpoint": "<TTD_API_PASSWORD>"
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
curl -X POST "http://localhost/rest_api_sso/api/ttd/sign" \
  -H "Content-Type: application/json" \
  -d '{
    "id_peg": "102-119",
    "input_pin": "123456",
    "nama_aplikasi": "simpeg",
    "pass_endpoint": "<TTD_API_PASSWORD>",
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

## Konfigurasi saat diaktifkan kembali

Simpan `TTD_API_PASSWORD` dan konfigurasi service TTD hanya pada environment
server di luar document root. Untuk production, gunakan HTTPS, batasi IP
aplikasi pemanggil, dan jangan gunakan password default.

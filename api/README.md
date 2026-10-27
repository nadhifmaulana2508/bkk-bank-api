# BKK Central API

Folder ini adalah fondasi API pusat untuk integrasi SIMPEG, TTD, Monbis, dan
Notaris.

## Aturan folder

- `global/` berisi endpoint bersama lintas aplikasi, termasuk layanan TTD.
- `modules/` berisi endpoint dan aturan bisnis berdasarkan domain.
- `shared/` berisi komponen teknis yang dipakai lintas domain.

Endpoint lama di root project belum dipindahkan ke sini. Pemindahan dilakukan
bertahap setelah kontrak endpoint baru disepakati dan sudah memiliki pengujian.

## Aturan kompatibilitas

Endpoint yang sudah digunakan aplikasi lain tidak boleh diganti path, method,
atau format response. Jika implementasi internal dipindahkan ke service baru,
route lama harus memakai adapter dan serializer response lama.

Endpoint `/api/v1` bersifat additive. Ia tidak menggantikan endpoint lama
sampai semua client selesai bermigrasi.

## Target versi

Endpoint baru menggunakan prefix:

```text
/api/v1
```

Aturan signing dan verifikasi TTD berada di `global/ttd/` karena TTD adalah
layanan bersama. Aturan bisnis Monbis dan Notaris tetap berada di modulnya
masing-masing.

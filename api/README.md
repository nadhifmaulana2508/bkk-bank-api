# BKK Central API

Folder ini adalah fondasi API pusat untuk integrasi SIMPEG, TTD, Monbis, dan
Notaris.

## Aturan folder

- `global/` berisi endpoint bersama lintas aplikasi.
- `modules/` berisi endpoint dan aturan bisnis berdasarkan domain.
- `shared/` berisi komponen teknis yang dipakai lintas domain.

Endpoint lama di root project belum dipindahkan ke sini. Pemindahan dilakukan
bertahap setelah kontrak endpoint baru disepakati dan sudah memiliki pengujian.

## Target versi

Endpoint baru menggunakan prefix:

```text
/api/v1
```

Jangan menambahkan aturan bisnis Monbis, Notaris, atau TTD langsung ke folder
`global/` hanya karena endpoint tersebut dipanggil oleh banyak aplikasi.

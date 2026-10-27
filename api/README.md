# BKK Central API

Folder ini adalah fondasi API pusat untuk integrasi SIMPEG, TTD, Monbis, dan
Notaris.

## Aturan folder

- `global/` berisi endpoint bersama lintas aplikasi, termasuk layanan TTD.
- `legacy/` berisi adapter untuk endpoint lama yang masih dipakai client.
- `modules/` berisi endpoint dan aturan bisnis berdasarkan domain.
- `shared/` berisi komponen teknis yang dipakai lintas domain.

`index.php` di root hanya menjadi bootstrap/router. Implementasi endpoint lama
berada di `legacy/` dan memanggil service baru melalui adapter.

Routing API berada di `api/routes.php` agar bootstrap root tidak bercampur
dengan aturan endpoint.

## Aturan kompatibilitas

Endpoint yang sudah digunakan aplikasi lain tidak boleh diganti path, method,
atau format response. Jika implementasi internal dipindahkan ke service baru,
route lama harus memakai adapter dan serializer response lama.

Migrasi ini tidak mengubah URL publik. Endpoint `/api/...` tetap menjadi
kontrak utama agar client yang sudah terintegrasi tidak perlu diubah.

Folder `api/global/` adalah struktur internal, bukan prefix URL baru. Jika
versioning endpoint diperlukan di masa depan, hal tersebut harus diputuskan
sebagai perubahan kontrak terpisah.

Aturan signing dan verifikasi TTD berada di `global/ttd/` karena TTD adalah
layanan bersama. Aturan bisnis Monbis dan Notaris tetap berada di modulnya
masing-masing.

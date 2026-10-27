# BKK Central API

Folder ini adalah fondasi API pusat untuk integrasi SIMPEG, TTD, Monbis, dan
Notaris. Branch `develop` difokuskan untuk backend API; aplikasi UI TTD tidak
ikut di dalamnya.

## Arsitektur aktif

`api/routes.php` adalah composition root. Ia hanya membuat router dan memuat
registry route dari global serta setiap modul. Detail endpoint tidak dikumpulkan
di satu file.

Router core mendukung middleware pipeline sehingga autentikasi dapat dipasang
di route tanpa menyalin pengecekan token ke setiap controller.

## Aturan folder

- `global/` berisi endpoint bersama lintas aplikasi, termasuk layanan TTD.
- `legacy/` berisi adapter untuk endpoint lama yang masih dipakai client.
- `modules/` berisi endpoint dan aturan bisnis berdasarkan domain.
- `Core/` berisi komponen teknis routing dan HTTP yang dipakai lintas domain.

`index.php` di root hanya menjadi bootstrap/router. Implementasi endpoint lama
berada di `legacy/` dan memanggil service baru melalui adapter.

Routing API berada di `api/routes.php` agar bootstrap root tidak bercampur
dengan aturan endpoint.

Panduan pengembangan lengkap tersedia di
`docs/architecture/API_MODERN_ARCHITECTURE.md`.

## Aturan kompatibilitas

Endpoint yang sudah digunakan aplikasi lain tidak boleh diganti path, method,
atau format response. Jika implementasi internal dipindahkan ke service baru,
route lama harus memakai adapter dan serializer response lama.

Migrasi ini tidak mengubah URL publik. Endpoint `/api/...` tetap menjadi
kontrak utama agar client yang sudah terintegrasi tidak perlu diubah.

Folder `api/global/` adalah struktur internal, bukan prefix URL baru. Jika
versioning endpoint diperlukan di masa depan, hal tersebut harus diputuskan
sebagai perubahan kontrak terpisah.

Aturan signing dan verifikasi TTD disiapkan di `global/ttd/` karena TTD adalah
layanan bersama. Route TTD baru diaktifkan setelah dependency database/service
tersedia di backend. Aturan bisnis Monbis dan Notaris tetap berada di modulnya
masing-masing.

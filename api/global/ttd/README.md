# Global TTD

TTD adalah service global yang dipakai lintas aplikasi. Controller API dan
adapter-nya disimpan di sini agar tidak bercampur dengan modul SIMPEG,
Monbis, atau Notaris.

Pada branch `develop`, route `/api/ttd/status` dan `/api/ttd/sign` belum
didaftarkan karena database/service TTD masih lokal. Kontrak response lama
tetap dicatat dan dapat diaktifkan kembali setelah dependency TTD dipindahkan
ke service backend yang tersedia di server.

# Global TTD

TTD adalah service global yang dipakai lintas aplikasi. Controller API berada
di sini, sedangkan `apps/ttd_in_bkk/` tetap menjadi UI internal untuk registrasi dan
administrasi TTD.

Route legacy `/api/ttd/status` dan `/api/ttd/sign` tetap menggunakan response
lama. Perapian internal dilakukan di controller global tanpa mengubah kontrak
client.

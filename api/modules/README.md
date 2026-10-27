# Modul API

Setiap domain baru dibuat sebagai modul mandiri. Contoh struktur:

```text
api/modules/<domain>/
├── routes.php
├── controllers/
├── services/
├── repositories/
├── validators/
└── README.md
```

Folder `services/`, `repositories/`, atau `validators/` ditambahkan saat
memang dibutuhkan. Jangan membuat satu controller besar untuk semua domain.

## Alur request

```text
request
  -> api/routes.php
  -> module/routes.php
  -> middleware
  -> controller
  -> service
  -> repository/database atau API eksternal
  -> response serializer
```

## Cara menambah modul

1. Buat folder `api/modules/<domain>/`.
2. Buat `routes.php` yang mengembalikan closure pendaftaran route.
3. Tambahkan file route tersebut ke daftar `$routeFiles` di `api/routes.php`.
4. Letakkan autentikasi pada middleware route, bukan duplikasi di setiap
   controller.
5. Pertahankan kontrak endpoint lama bila modul menggantikan implementasi
   legacy.

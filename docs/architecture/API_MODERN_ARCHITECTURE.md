# Arsitektur Modern BKK API

Dokumen ini menjadi panduan pengembangan branch `develop`. Arsitektur tetap
PHP native, tetapi memakai batas modul, composition root, service layer, dan
middleware pipeline agar mudah dikembangkan tanpa membuat satu file router
atau controller menjadi terlalu besar.

## Struktur

```text
rest_api_sso/
├── index.php                 # entry point web dan security headers
├── api/
│   ├── routes.php            # composition root route
│   ├── Core/                 # router dan HTTP primitive
│   ├── global/               # auth dan service lintas domain
│   ├── legacy/               # adapter kontrak lama
│   └── modules/
│       ├── simpeg/
│       ├── monbis/
│       └── notaris/
├── config/                   # environment dan koneksi
├── helpers/                  # compatibility helper teknis
├── middlewares/              # compatibility middleware lama
└── docs/
```

## Tanggung jawab lapisan

| Lapisan | Tanggung jawab | Tidak boleh dilakukan |
| --- | --- | --- |
| `index.php` | bootstrap keamanan, CORS, exception boundary | query database |
| `api/routes.php` | memuat route registry | aturan bisnis |
| `api/Core` | routing, request, middleware pipeline | query domain |
| `routes.php` modul | mapping method/path ke controller | query database |
| controller | validasi input tipis dan serialisasi response | query panjang/aturan lintas domain |
| service | use case dan aturan bisnis | membaca `$_SERVER` langsung bila bisa dihindari |
| repository | query database/API eksternal | mengubah response HTTP |
| `legacy` | menjaga URL dan response client lama | menambah aturan bisnis baru |

## Alur request

```text
HTTP request
    -> index.php
    -> api/routes.php
    -> route registry global/module
    -> middleware (app token, auth, scope, rate limit)
    -> controller
    -> service
    -> repository/adapter
    -> response lama atau response baru
```

Route per modul mengembalikan closure registry. Dengan pola ini, `api/routes.php`
hanya mengetahui daftar modul, bukan detail endpoint setiap modul.

## Aturan pengembangan

1. URL dan response endpoint existing dianggap kontrak publik.
2. Domain baru tidak boleh menaruh route di `api/routes.php` langsung.
3. App token, auth, scope, dan rate limit dipasang sebagai middleware pipeline.
   `RequireAppMiddleware` menjadi gerbang global untuk aplikasi pemanggil;
   `RequireUserMiddleware` tetap dipakai hanya saat identitas pegawai diperlukan.
4. Semua query memakai prepared statement dan repository/service yang sesuai.
5. Dependency harus di-load pada bootstrap/composition root agar scope global
   seperti `$pdo` tidak bergantung pada closure route.
6. Jangan membaca secret dari file yang dapat diakses web server.
7. TTD tetap area global, tetapi route-nya diaktifkan hanya setelah service dan
   database backend tersedia.

## Contoh route modul

```php
require_once __DIR__ . '/controllers/ExampleController.php';
require_once dirname(__DIR__, 2) . '/global/auth/RequireUserMiddleware.php';

return static function (ApiRouter $router): void {
    $router->get(
        '/api/example/items',
        [ExampleController::class, 'index'],
        [[RequireUserMiddleware::class, 'handle']]
    );
};
```

## Rencana evolusi

- Tahap 1: route registry dan middleware pipeline sudah tersedia.
- Tahap 2: pindahkan query dari service ke repository per modul.
- Tahap 3: tambahkan validator, error code, correlation ID, dan audit log.
- Tahap 4: tambahkan scope aplikasi untuk Monbis, Notaris, dan TTD.
- Tahap 5: pecah menjadi microservice hanya jika ownership data, beban, dan
  deployment memang sudah membutuhkan pemisahan fisik.

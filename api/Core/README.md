# API Core

Core berisi komponen teknis yang dipakai semua modul. Core tidak boleh berisi
aturan bisnis SIMPEG, Monbis, Notaris, atau TTD.

```text
api/Core/
├── Http/
│   └── ApiRequest.php       # method dan path request
└── Routing/
    └── ApiRouter.php        # route, middleware, dan dispatch
```

Aturan utama:

- controller menerima request dan mengembalikan response;
- service berisi aturan bisnis dan query/use case;
- core hanya mengatur alur teknis;
- dependency dimuat pada composition root, bukan secara tersembunyi di
  dalam closure route;
- endpoint lama tetap didaftarkan dengan path dan method yang sama.

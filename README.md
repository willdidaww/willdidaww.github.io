# Aplikasi Manajemen Biaya Bus AKAP

Aplikasi manajemen biaya operasional bus Antar Kota Antar Provinsi (AKAP):
rit, uang jalan, pengeluaran, pendapatan, perawatan, dokumen kendaraan, kas/kasbon,
persetujuan, laporan, dan dashboard.

## Stack

Dibangun **tanpa framework eksternal** dan **tanpa dependensi yang perlu diunduh**,
karena lingkungan build tidak punya akses internet (registry npm/Composer tidak dapat dijangkau,
tidak ada server PostgreSQL/MySQL). Pilihan ini membuat aplikasi langsung jalan.

- **Bahasa:** PHP 8.4 (murni, tanpa Composer package)
- **Database:** SQLite 3 (via PDO) — file tunggal, tanpa server
- **Frontend:** HTML + CSS + JavaScript vanilla (chart digambar sendiri via Canvas)
- **Arsitektur:** front controller + router + controller/model sederhana (PSR-4-ish autoloader buatan)

> Catatan: schema disusun agar mudah dipetakan ke PostgreSQL. Layer DB memakai PDO,
> sehingga migrasi ke Postgres/MySQL nanti relatif mudah (ganti DSN + sedikit tipe kolom).

## Menjalankan

```bash
# 1. Migrasi + seed database (buat tabel & data awal)
php bin/console migrate
php bin/console seed

# 2. Jalankan server
php -S 127.0.0.1:8000 -t public public/index.php
# buka http://127.0.0.1:8000
```

Akun awal (dari seeder):

| Peran      | Email               | Password   |
|------------|---------------------|------------|
| owner      | owner@akap.test     | password   |
| manajer    | manajer@akap.test   | password   |
| keuangan   | keuangan@akap.test  | password   |
| admin_pool | admin@akap.test     | password   |
| kru        | kru@akap.test       | password   |

## Struktur

```
bin/console          # CLI: migrate, seed, fresh
config/              # konfigurasi app & env
database/
  migrations.php     # skema (dijalankan oleh console)
  seed.php           # data awal
public/index.php     # front controller
  assets/            # css & js
src/
  Core/              # Router, Request, Response, DB, Auth, View, dll
  Controllers/       # controller per modul
  Models/            # model per entitas
  Support/           # helper (validasi, sanitasi, ekspor, dsb)
resources/views/     # template PHP
storage/
  app.sqlite         # database
  uploads/           # foto nota & dokumen
  logs/              # log aplikasi
```

## Status checklist

Lihat `CHECKLIST-STATUS.md` untuk pemetaan per item checklist ke implementasi,
termasuk bagian yang sudah jalan penuh, sebagian, dan yang belum (dengan alasan).

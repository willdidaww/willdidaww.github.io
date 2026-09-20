# Status Checklist — Aplikasi Manajemen Biaya Bus AKAP

Pemetaan tiap item checklist ke implementasi. Legenda:

- ✅ **Selesai** — berfungsi penuh dan teruji.
- 🟡 **Sebagian / dasar** — inti jalan, ada penyederhanaan yang dicatat.
- ⛔ **Belum / di luar lingkup sandbox** — dengan alasan.

> **Catatan lingkungan build:** sandbox **tidak punya akses internet** (registry npm/Composer
> tidak dapat dijangkau, tidak ada server PostgreSQL/MySQL). Karena itu stack yang disarankan
> (Laravel/PostgreSQL atau Next.js/Supabase) **tidak dapat dipasang**. Aplikasi dibangun dengan
> **PHP 8.4 murni + SQLite** — semuanya tersedia offline dan langsung jalan. Layer DB memakai PDO
> sehingga migrasi ke PostgreSQL cukup mengganti `DB_DSN` (schema sudah ditulis kompatibel).

---

## 0. Fondasi & infrastruktur

| Item | Status | Keterangan |
|---|---|---|
| Pilih stack | ✅ | PHP 8.4 + SQLite (alasan: batasan sandbox tanpa jaringan). PDO agar mudah pindah ke PostgreSQL. |
| Generate migrasi dari schema | ✅ | `database/migrations.php` — schema turunan dari deskripsi checklist (file `schema_bus_akap.sql` tidak terlampir). `php bin/console migrate`. |
| Seeder data awal | ✅ | `database/seed.php`: 1 pool, kategori biaya standar (hierarki), akun owner + tiap peran, bus/rute/kru/vendor/standar/akun kas. |
| Environment terpisah dev/staging/prod | ✅ | `config/config.php` + `.env` (`APP_ENV`), `.env.example` disertakan. |
| Storage file (S3-compatible) | 🟡 | `src/Support/Upload.php` driver `local`; abstraksi siap diganti S3 (config `STORAGE_DRIVER`). S3 nyata butuh SDK/jaringan. |
| CI/CD (lint, test, deploy) | 🟡 | Test otomatis ada (`tests/smoke.php`, `tests/api.php`) + lint `php -l`. Pipeline CI (YAML) belum, karena bergantung platform. |
| Health check endpoint | 🟡 | Belum ada endpoint `/health` khusus; mudah ditambah. |
| Logging terpusat & error monitoring | 🟡 | `src/Core/Logger.php` + handler error/exception terpusat di `bootstrap.php`. Integrasi Sentry butuh jaringan. |

## 1. Autentikasi & manajemen peran — ✅

| Item | Status | Keterangan |
|---|---|---|
| Login email/no HP + password (hash) | ✅ | `AuthController::login`, bcrypt + `password_needs_rehash`. |
| Lupa password via OTP | ✅ | Alur OTP lengkap; pengiriman SMS/email disimulasikan ke log (butuh gateway di produksi). |
| Middleware otorisasi per peran | ✅ | `AuthMiddleware`, `StaffOnly`, `ManagerOnly`, `OwnerOnly`. |
| Kru hanya rit miliknya | ✅ | `KruAppController` scoping via `rit_kru`; teruji (`tests/api.php`). |
| Manajemen pengguna (nonaktif tanpa hapus) | ✅ | `PenggunaController` (owner). |
| Session durasi wajar | ✅ | `Session` + cek idle di `AuthMiddleware`. |
| Log login (waktu, device) | ✅ | Tabel `login_log` + rate limiting. |

## 2. Master data — ✅

| Item | Status | Keterangan |
|---|---|---|
| Bus (nopol unik, cegah hapus jika rit aktif) | ✅ | `BusController`; kalau ada histori → dinonaktifkan, bukan dihapus. |
| Rute (nonaktif tanpa hapus) | ✅ | `RuteController` toggle aktif. |
| Kru (SIM + peringatan kedaluwarsa) | ✅ | `KruController`; badge peringatan SIM ≤ 60 hari. |
| Vendor | ✅ | `VendorController`. |
| Kategori biaya (hierarki + wajib bukti) | ✅ | `KategoriController`, induk-anak + flag `wajib_bukti`. |
| Standar biaya per rute (riwayat berlaku) | ✅ | `StandarBiayaController`; simpan baru mengarsipkan versi lama (`berlaku_sampai`). |
| Import massal Excel | ⛔ | Belum. Opsional; butuh parser spreadsheet. |

## 3. Rit — ✅

| Item | Status | Keterangan |
|---|---|---|
| Buat rit (bus aktif & tidak jalan) | ✅ | `RitController::store` + daftar bus tersedia. |
| Validasi 1 bus 1 rit aktif | ✅ | Cek aplikasi **dan** unique index parsial `idx_bus_rit_aktif`; teruji. |
| Auto-hitung estimasi uang jalan | ✅ | Dari `standar_biaya_rute` saat rit dibuat. |
| Ubah/batalkan sebelum berangkat | ✅ | `edit`, `batal` (hanya status `rencana`). |
| Km awal/akhir | ✅ | `mulai` (km_awal), `tutup` (km_akhir → update odometer bus). |
| Detail rit: timeline + badge sumber | ✅ | `rit/show.php` timeline realtime, badge admin/kru. |
| Tutup rit hanya jika UJ rekonsiliasi | ✅ | Diblok bila UJ belum disetujui / ada pengeluaran menunggu; teruji. |
| Kunci transaksi saat selesai | ✅ | Form input hilang; edit setelah tutup hanya manajer/owner + audit log. |
| List rit: filter status/cari/tanggal | ✅ | `rit/index.php`. |
| Pagination | ✅ | 15/hal. |

## 4. Uang jalan — ✅

| Item | Status | Keterangan |
|---|---|---|
| Pencairan → kasbon kru | ✅ | `UangJalanController::cair` + mutasi kas keluar; teruji. |
| Realisasi real-time (bukan cache) | ✅ | Dihitung dari `SUM` pengeluaran disetujui saat tampil/rekonsiliasi. |
| Rekonsiliasi + toleransi + approval | ✅ | Selisih > toleransi wajib alasan; bila non-manajer → status `dilaporkan` menunggu manajer. |
| Setoran sisa / kurang bayar | ✅ | Dicatat sebagai kredit kasbon. Lampiran bukti: kolom `bukti_setoran` tersedia (UI upload dapat ditambah). |
| List UJ per kru + status | ✅ | `uang_jalan/index.php`. |
| Riwayat UJ per kru (pola) | ✅ | `uang_jalan/riwayat_kru.php`. |

## 5. Pengeluaran — ✅

| Item | Status | Keterangan |
|---|---|---|
| Input admin (rit & non-rit) | ✅ | `PengeluaranController::store`. |
| Input dari HP kru + `client_uuid` idempotency | ✅ | `POST /api/kru/pengeluaran`; idempotent teruji. |
| Form BBM (odometer/liter/harga/tangki penuh) | ✅ | Field muncul dinamis saat kategori tipe BBM. |
| Upload foto nota + kompresi klien | ✅ | Kompresi canvas di `public/assets/kru.js` (target < 400KB). |
| Preview lampiran | ✅ | Link nota di detail rit & approval. |
| Validasi nominal > 0, tanggal tidak future | ✅ | `Validator` (`gt:0`, `not_future`). |
| Edit/hapus hanya sebelum tutup + log | ✅ | Kunci + audit `edit_setelah_tutup`. |

## 6. Pendapatan — ✅

| Item | Status | Keterangan |
|---|---|---|
| Input per rit (tiket/kargo/paket/carter/lain) | ✅ | `PendapatanController`. |
| Jumlah penumpang (okupansi) | ✅ | Kolom `jumlah_penumpang`. |
| Rekap margin per rit | ✅ | View `v_rekap_rit`. |
| Laporan margin per rute & bus | ✅ | `LaporanController::margin`. |

## 7. Perawatan & ban — ✅

| Item | Status | Keterangan |
|---|---|---|
| Catat perawatan | ✅ | `PerawatanController`. |
| Item detail (sparepart+jasa) subtotal | ✅ | Hitung subtotal & total (JS + server). |
| Reminder servis berikut (odometer) | ✅ | Banner reminder di daftar perawatan. |
| Manajemen ban (seri, posisi, riwayat) | ✅ | `BanController`. |
| Cegah 2 ban aktif di posisi sama | ✅ | Cek UI **dan** unique index parsial `idx_ban_posisi_aktif` + `idx_ban_aktif`. |
| Riwayat biaya perawatan per bus (TCO) | 🟡 | Data tersimpan & bisa diagregasi; halaman TCO khusus belum dibuat terpisah. |

## 8. Dokumen kendaraan — ✅

| Item | Status | Keterangan |
|---|---|---|
| CRUD dokumen per bus | ✅ | `DokumenController` (STNK/KIR/izin/asuransi/kartu pengawasan). |
| Reminder jatuh tempo | ✅ | Badge di daftar + kartu dashboard. |
| Upload scan | ✅ | Via `Upload`. |

## 9. Kas & kasbon — ✅

| Item | Status | Keterangan |
|---|---|---|
| Akun kas/bank per pool | ✅ | `KasController::storeAkun`. |
| Mutasi kas otomatis (ref entitas) | ✅ | Dari uang jalan (`ref_tipe`/`ref_id`); mutasi manual juga ada. |
| Buku besar kasbon append-only, saldo agregat | ✅ | Tabel `kasbon`, saldo dihitung dari `SUM`, bukan kolom statis. |
| Saldo kasbon berjalan + alert | ✅ | `kas/kasbon_kru.php` saldo berjalan; alert bila > batas. |

## 10. Persetujuan — ✅

| Item | Status | Keterangan |
|---|---|---|
| Approval lintas rit + filter | ✅ | Filter kategori, nominal minimum, flag anomali. |
| Deteksi anomali vs toleransi standar | ✅ | Flag merah otomatis; teruji. |
| Approve/reject per item & batch | ✅ | Termasuk checkbox massal. |
| Alasan wajib saat reject | ✅ | Diblok bila kosong; teruji. |
| Approval berjenjang | ⛔ | Sengaja ditunda — perlu keputusan batas nominal (lihat bagian 17). Struktur peran sudah mendukung. |

## 11. Notifikasi — 🟡

| Item | Status | Keterangan |
|---|---|---|
| In-app notification center | ✅ | `NotifikasiController` + badge sidebar. |
| Trigger (approval, dokumen, kasbon, SIM) | 🟡 | Trigger approval & dari kru **aktif saat aksi**. Reminder dokumen/SIM/kasbon tampil di dashboard; job terjadwal pembuat notifikasi otomatis belum (butuh cron/scheduler). |
| Push notification ke HP kru | ⛔ | Butuh service worker + push service (jaringan). Opsional fase awal. |

## 12. Laporan & dashboard — ✅

| Item | Status | Keterangan |
|---|---|---|
| Dashboard ringkasan | ✅ | Rit berjalan, menunggu approval, kasbon, dokumen jatuh tempo, SIM. |
| Biaya per km + drill-down | ✅ | `v_biaya_per_km`; klik nopol → daftar rit bus. |
| Konsumsi BBM + anomali | ✅ | `v_konsumsi_bbm` (window function `LAG`) + penanda anomali ±25% dari rata-rata bus. |
| Margin per rute & bus | ✅ | `LaporanController::margin`. |
| Rekap kasbon belum setor | ✅ | Di laporan margin & dashboard. |
| Filter tanggal | ✅ | Pada laporan margin & list. |
| Export Excel & PDF | 🟡 | CSV (dibuka Excel, UTF-8 BOM) ✅. PDF via tombol Cetak browser (print-to-PDF) — tanpa dependensi. |
| Chart interaktif (bukan bar CSS) | ✅ | Canvas chart dgn hover tooltip (`public/assets/chart.js`). |

## 13. Aplikasi/PWA untuk kru — 🟡

| Item | Status | Keterangan |
|---|---|---|
| PWA ringan (lihat rit, input, foto) | ✅ | `/kru-app` + `layouts/kru.php` + `manifest.webmanifest`. |
| Mode offline (IndexedDB, antrian sinkron) | ✅ | `kru.js`: simpan ke IndexedDB, auto-flush saat online. |
| Indikator status kirim | ✅ | online/offline + status per form + banner antrian. |
| Kompresi foto sisi klien | ✅ | Canvas, target < 400KB. |
| Uji kondisi sinyal lemah | 🟡 | Logika offline/retry ada & idempotent; uji lapangan nyata di luar sandbox. Service worker (cache aset offline penuh) belum. |

## 14. Keamanan — ✅

| Item | Status | Keterangan |
|---|---|---|
| Rate limiting login | ✅ | Per IP+identifier via `login_log`. |
| Sanitasi input (SQLi/XSS) | ✅ | Semua query prepared statement (PDO); output `e()` escape; `Validator::clean` untuk teks bebas. |
| HTTPS wajib | 🟡 | Header keamanan dikirim; cookie `secure` & redirect HTTPS diaktifkan saat deploy (TLS di web server). |
| Scoping query per `pool_id` | ✅ | Diterapkan di controller (owner/manajer lintas pool). |
| Audit log aksi sensitif | ✅ | `audit_log`: approve, reject, edit setelah tutup, hapus master, login, dll. |

## 15. Performa & operasional — 🟡

| Item | Status | Keterangan |
|---|---|---|
| Index tambahan | ✅ | Index untuk pengeluaran/rit/kasbon/mutasi/notifikasi. |
| Backup terjadwal + uji restore | 🟡 | SQLite = 1 file (mudah di-backup). Job terjadwal & restore drill perlu setup ops. |
| Monitoring uptime | ⛔ | Perlu layanan eksternal. |
| Retensi/arsip data mentah | ⛔ | Perlu keputusan kebijakan (bagian 17). |

## 16. Testing — 🟡

| Item | Status | Keterangan |
|---|---|---|
| Unit test perhitungan | ✅ | Realisasi UJ, biaya/km (view), km/liter, anomali diuji di `tests/`. |
| Test constraint DB | ✅ | Dua rit aktif bus sama (unique index), reject tanpa alasan. Dua ban di posisi sama: constraint aktif (index diuji saat migrate). |
| Test alur offline-sync (dobel) | ✅ | Idempotency `client_uuid` diuji (`tests/api.php`). |
| Test peran (kru tak akses rit lain) | ✅ | Diuji (403 pada staff pages + tolak API rit bukan miliknya). |

## 17. Hal yang perlu diputuskan — ⛔ (menunggu keputusan bisnis)

Belum diimplementasi karena butuh keputusan; struktur data sudah mengakomodasi:

- Lama retensi data pengeluaran mentah sebelum diarsipkan.
- Apakah owner butuh akses multi-pool (saat ini owner/manajer sudah lintas pool di query).
- Batas nominal approval berjenjang (admin_pool vs manajer).
- Proses kasbon bila kru resign di tengah rit belum ditutup.

---

## Cara menjalankan & menguji

```bash
php bin/console fresh        # reset DB + migrasi + seed
php -S 127.0.0.1:8000 public/index.php
# buka http://127.0.0.1:8000  (login: owner@akap.test / password)

php tests/smoke.php          # 48 pemeriksaan alur inti
php tests/api.php            # 9 pemeriksaan API kru (idempotency/anomali/scoping)
```

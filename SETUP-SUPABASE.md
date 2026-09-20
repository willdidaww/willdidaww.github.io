# Setup — Versi Supabase (HTML/JS) — SLICE 1

Panduan langkah demi langkah untuk menjalankan versi web (front-end statis + Supabase).
Hasil akhir: aplikasi yang **berfungsi nyata** (login, data tersimpan bersama, peran ditegakkan
oleh database) dan bisa **dipreview lewat URL publik** (GitHub Pages).

Perkiraan waktu: ~15–20 menit. Semua gratis (Supabase free tier + GitHub Pages).

---

## Ringkasan arsitektur

- **Front-end:** folder `web/` — HTML + JavaScript statis. Tidak butuh server PHP.
- **Backend:** Supabase = database PostgreSQL + Auth + Storage + Row Level Security (RLS).
- **Keamanan:** RLS di database menegakkan peran & scoping pool (bukan hanya di UI),
  jadi tetap aman walau front-end statis.

> Versi PHP lama tetap ada di root repo dan tidak diubah. Versi ini terpisah di `web/` + `supabase/`.

---

## Langkah 1 — Buat proyek Supabase

1. Daftar/masuk di https://supabase.com → **New project**.
2. Beri nama (mis. `akap`), pilih region terdekat (mis. Singapore), set database password.
3. Tunggu proyek selesai dibuat (~2 menit).

## Langkah 2 — Jalankan SQL (schema → policies → seed)

Buka **SQL Editor** di dashboard, lalu jalankan **berurutan** (New query → tempel → Run):

1. Isi file `supabase/schema.sql`
2. Isi file `supabase/policies.sql`
3. Isi file `supabase/seed.sql`

Setelah ini database punya tabel, RLS aktif, dan data master contoh (pool, kategori, bus, rute, kru, standar biaya).

## Langkah 3 — Buat bucket Storage untuk foto nota

1. Menu **Storage** → **New bucket** → nama persis: **`nota`** → centang **Public bucket** → Create.
2. (Opsional, agar upload/lihat rapi) tambahkan policy sederhana di Storage:
   - Untuk demo cepat, bucket public sudah cukup untuk menampilkan foto.
   - Untuk lebih aman, batasi INSERT ke user terautentikasi (lihat catatan di bawah).

## Langkah 4 — Buat akun & tetapkan peran

Akun login **tidak** dibuat lewat SQL (password di-hash oleh Supabase Auth).

1. Menu **Authentication** → **Users** → **Add user** (email + password). Buat sesuai kebutuhan, contoh:
   - `owner@akap.test`
   - `manajer@akap.test`
   - `keuangan@akap.test`
   - `admin@akap.test`
   - `kru@akap.test`
   > Saat membuat user, matikan "Auto confirm" tidak masalah untuk demo — atau centang **Auto Confirm User** supaya bisa langsung login tanpa verifikasi email.
2. Kembali ke **SQL Editor**, buka `supabase/seed.sql`, dan jalankan **blok "PROMOSI PERAN"**
   (baris `update profiles ...` — hapus tanda komentar `--`, sesuaikan email). Ini menetapkan
   role + pool + tautan kru untuk tiap akun. Trigger sudah otomatis membuat baris `profiles`
   saat user dibuat; blok ini hanya mengisi peran yang benar.

   Contoh menetapkan owner:
   ```sql
   update profiles p
     set role='owner', nama='Owner Utama',
         pool_id=(select id from pool order by id limit 1)
   where p.id = (select id from auth.users where email='owner@akap.test');
   ```

## Langkah 5 — Isi konfigurasi front-end

1. Di **Project Settings → API** salin **Project URL** dan **anon public** key.
2. Di folder `web/`, salin `config.example.js` → **`config.js`** dan isi kedua nilai itu:
   ```js
   window.AKAP_CONFIG = {
     SUPABASE_URL: 'https://xxxx.supabase.co',
     SUPABASE_ANON_KEY: 'eyJ...anon-key...',
   };
   ```
   > `config.js` sengaja tidak di-commit (ada di `.gitignore`) agar key tidak ikut ke repo.
   > anon key memang untuk dipakai di browser — RLS yang mengamankan data. Jangan pakai `service_role`.

## Langkah 6 — Izinkan domain (CORS/redirect)

Di **Authentication → URL Configuration**, tambahkan URL tempat Anda membuka app ke
**Site URL / Redirect URLs**, mis:
- `http://localhost:5173` (saat coba lokal), dan
- `https://USERNAME.github.io` (saat deploy GitHub Pages).

## Langkah 7 — Jalankan

**Lokal (paling cepat untuk uji):** dari folder `web/`, jalankan server statis apa saja, mis:
```bash
cd web
python3 -m http.server 5173
# buka http://localhost:5173
```
(atau ekstensi "Live Server" di VS Code)

Login pakai salah satu akun (mis. `owner@akap.test`). Coba: buat bus/rute/kru → buat rit →
input pengeluaran → approve. Login sebagai `kru@akap.test` untuk melihat hanya rit miliknya.

---

## Deploy ke GitHub Pages (URL publik gratis)

Karena front-end statis, GitHub Pages bisa menyajikannya. Tapi **isi `config.js` dulu**
(Pages tidak punya server untuk menyembunyikan key; anon key aman untuk publik).

Cara termudah — sajikan folder `web/`:

1. Karena Pages menyajikan dari root atau `/docs`, salin isi `web/` ke folder `docs/` di branch `main`
   (atau atur workflow). Contoh cepat:
   ```bash
   mkdir -p docs && cp -r web/* docs/
   # buat docs/config.js dari template dan isi key Anda
   git add docs && git commit -m "Deploy web ke GitHub Pages" && git push
   ```
2. Di GitHub: **Settings → Pages → Build and deployment → Source: Deploy from a branch**,
   pilih branch `main` dan folder `/docs`. Simpan.
3. Tunggu ~1 menit; URL muncul: `https://USERNAME.github.io/Project_AKAP/`.
4. Tambahkan URL itu ke Supabase **Redirect URLs** (Langkah 6).

> Karena app pakai routing berbasis hash (`#/…`), tidak perlu konfigurasi rewrite khusus di Pages.

---

## Cakupan Slice 1 (yang sudah ada di versi web)

✅ Login email+password (Supabase Auth) · peran + scoping pool via RLS
✅ Dashboard ringkasan · Master **Bus / Rute / Kru**
✅ **Rit**: buat (validasi 1 bus 1 rit aktif + estimasi uang jalan), mulai, tutup, batal, detail
✅ **Pengeluaran**: input (rit & non-rit), form BBM, upload foto nota, deteksi anomali vs standar
✅ **Persetujuan**: approve/reject per item & batch, alasan wajib saat menolak
✅ **Area Kru**: hanya rit miliknya, input pengeluaran + foto (idempotency `client_uuid`)

**Belum di Slice 1** (rencana Slice 2+): uang jalan & rekonsiliasi penuh, kas & kasbon,
pendapatan, perawatan & ban, dokumen kendaraan, laporan & chart, notifikasi, PWA offline.
Versi PHP di root repo sudah mencakup semua modul itu sebagai referensi.

---

## Catatan keamanan Storage (opsional, produksi)

Untuk membatasi upload nota hanya oleh user login (selain bucket public untuk baca),
tambahkan policy di **Storage → Policies** pada bucket `nota`:

```sql
-- INSERT: hanya user terautentikasi
create policy "nota_insert_auth" on storage.objects for insert
  to authenticated with check (bucket_id = 'nota');
-- SELECT: publik boleh baca (agar <img> tampil)
create policy "nota_read_public" on storage.objects for select
  using (bucket_id = 'nota');
```

## Troubleshooting

- **"Konfigurasi belum lengkap"** → `config.js` belum dibuat/diisi (Langkah 5).
- **Bisa login tapi data kosong / gagal simpan** → role/pool belum di-set (Langkah 4, blok PROMOSI PERAN),
  atau SQL policies belum dijalankan.
- **Upload nota gagal** → bucket `nota` belum dibuat atau belum public (Langkah 3).
- **Login gagal "Email not confirmed"** → aktifkan Auto Confirm saat membuat user, atau matikan
  "Confirm email" di Authentication → Providers → Email (untuk demo).

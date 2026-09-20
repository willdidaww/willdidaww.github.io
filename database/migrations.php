<?php
declare(strict_types=1);

/**
 * Skema database aplikasi manajemen biaya bus AKAP.
 *
 * Ditulis untuk SQLite (default) namun dipetakan mudah ke PostgreSQL:
 *  - INTEGER PRIMARY KEY AUTOINCREMENT  -> SERIAL/BIGSERIAL
 *  - TEXT untuk tanggal/waktu ISO-8601  -> TIMESTAMP/DATE
 *  - CHECK constraint & FOREIGN KEY dipertahankan
 *
 * Mengembalikan array pernyataan DDL yang dijalankan berurutan oleh bin/console.
 */

return [

    // ---------------------------------------------------------------------
    // 0/1. Pool (cabang) & pengguna
    // ---------------------------------------------------------------------
    'pool' => "
        CREATE TABLE IF NOT EXISTS pool (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            nama         TEXT NOT NULL,
            kota         TEXT,
            alamat       TEXT,
            aktif        INTEGER NOT NULL DEFAULT 1,
            dibuat_pada  TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    'pengguna' => "
        CREATE TABLE IF NOT EXISTS pengguna (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id       INTEGER REFERENCES pool(id),
            nama          TEXT NOT NULL,
            email         TEXT UNIQUE,
            no_hp         TEXT UNIQUE,
            password_hash TEXT NOT NULL,
            role          TEXT NOT NULL CHECK (role IN ('owner','manajer','keuangan','admin_pool','kru')),
            kru_id        INTEGER,           -- tautan ke tabel kru bila role=kru
            aktif         INTEGER NOT NULL DEFAULT 1,
            dibuat_pada   TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    'login_log' => "
        CREATE TABLE IF NOT EXISTS login_log (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            pengguna_id INTEGER REFERENCES pengguna(id),
            email       TEXT,
            ip          TEXT,
            user_agent  TEXT,
            berhasil    INTEGER NOT NULL DEFAULT 0,
            waktu       TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    // ---------------------------------------------------------------------
    // 2. Master data
    // ---------------------------------------------------------------------
    'bus' => "
        CREATE TABLE IF NOT EXISTS bus (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id     INTEGER NOT NULL REFERENCES pool(id),
            nopol       TEXT NOT NULL UNIQUE,
            kelas       TEXT,                        -- ekonomi/bisnis/eksekutif/sleeper
            karoseri    TEXT,
            tahun       INTEGER,
            odometer    INTEGER NOT NULL DEFAULT 0,
            status      TEXT NOT NULL DEFAULT 'aktif' CHECK (status IN ('aktif','nonaktif','perawatan')),
            dibuat_pada TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    'rute' => "
        CREATE TABLE IF NOT EXISTS rute (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id       INTEGER NOT NULL REFERENCES pool(id),
            kode          TEXT,
            asal          TEXT NOT NULL,
            tujuan        TEXT NOT NULL,
            jarak_km      REAL,
            estimasi_jam  REAL,
            aktif         INTEGER NOT NULL DEFAULT 1,
            dibuat_pada   TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    'kru' => "
        CREATE TABLE IF NOT EXISTS kru (
            id                INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id           INTEGER NOT NULL REFERENCES pool(id),
            nama              TEXT NOT NULL,
            no_hp             TEXT,
            posisi            TEXT NOT NULL DEFAULT 'sopir' CHECK (posisi IN ('sopir','sopir_2','kernet','kondektur')),
            no_sim            TEXT,
            sim_berlaku_sampai TEXT,
            aktif             INTEGER NOT NULL DEFAULT 1,
            dibuat_pada       TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    'vendor' => "
        CREATE TABLE IF NOT EXISTS vendor (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id     INTEGER NOT NULL REFERENCES pool(id),
            nama        TEXT NOT NULL,
            jenis       TEXT NOT NULL DEFAULT 'lain' CHECK (jenis IN ('spbu','bengkel','sparepart','agen','lain')),
            telepon     TEXT,
            alamat      TEXT,
            aktif       INTEGER NOT NULL DEFAULT 1,
            dibuat_pada TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    'kategori_biaya' => "
        CREATE TABLE IF NOT EXISTS kategori_biaya (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            induk_id    INTEGER REFERENCES kategori_biaya(id),   -- hierarki induk-anak
            nama        TEXT NOT NULL,
            tipe        TEXT NOT NULL DEFAULT 'variabel' CHECK (tipe IN ('variabel','tetap','bbm')),
            wajib_bukti INTEGER NOT NULL DEFAULT 0,               -- wajib foto nota?
            aktif       INTEGER NOT NULL DEFAULT 1,
            dibuat_pada TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    // Standar biaya per rute+kelas+kategori, dengan riwayat berlaku
    'standar_biaya_rute' => "
        CREATE TABLE IF NOT EXISTS standar_biaya_rute (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            rute_id        INTEGER NOT NULL REFERENCES rute(id),
            kategori_id    INTEGER NOT NULL REFERENCES kategori_biaya(id),
            kelas_bus      TEXT,                    -- null = berlaku semua kelas
            nominal_standar REAL NOT NULL,
            toleransi_pct  REAL NOT NULL DEFAULT 10,
            berlaku_dari   TEXT NOT NULL DEFAULT (date('now')),
            berlaku_sampai TEXT,                    -- null = masih berlaku
            dibuat_pada    TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    // ---------------------------------------------------------------------
    // 3. Rit (inti operasional)
    // ---------------------------------------------------------------------
    'rit' => "
        CREATE TABLE IF NOT EXISTS rit (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id         INTEGER NOT NULL REFERENCES pool(id),
            kode            TEXT NOT NULL UNIQUE,
            bus_id          INTEGER NOT NULL REFERENCES bus(id),
            rute_id         INTEGER NOT NULL REFERENCES rute(id),
            tanggal         TEXT NOT NULL,
            status          TEXT NOT NULL DEFAULT 'rencana'
                              CHECK (status IN ('rencana','berjalan','selesai','batal')),
            km_awal         INTEGER,
            km_akhir        INTEGER,
            estimasi_uang_jalan REAL NOT NULL DEFAULT 0,
            dibuat_oleh     INTEGER REFERENCES pengguna(id),
            ditutup_oleh    INTEGER REFERENCES pengguna(id),
            ditutup_pada    TEXT,
            catatan         TEXT,
            dibuat_pada     TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    // Penugasan kru ke rit (banyak kru per rit)
    'rit_kru' => "
        CREATE TABLE IF NOT EXISTS rit_kru (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            rit_id  INTEGER NOT NULL REFERENCES rit(id) ON DELETE CASCADE,
            kru_id  INTEGER NOT NULL REFERENCES kru(id),
            peran   TEXT NOT NULL DEFAULT 'sopir',
            UNIQUE (rit_id, kru_id)
        )",

    // Index unik parsial: satu bus hanya boleh punya SATU rit aktif (rencana/berjalan)
    'idx_bus_rit_aktif' => "
        CREATE UNIQUE INDEX IF NOT EXISTS idx_bus_rit_aktif
            ON rit (bus_id) WHERE status IN ('rencana','berjalan')",

    // ---------------------------------------------------------------------
    // 5. Pengeluaran
    // ---------------------------------------------------------------------
    'pengeluaran' => "
        CREATE TABLE IF NOT EXISTS pengeluaran (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id       INTEGER NOT NULL REFERENCES pool(id),
            rit_id        INTEGER REFERENCES rit(id),       -- null = biaya non-rit / tetap
            kategori_id   INTEGER NOT NULL REFERENCES kategori_biaya(id),
            vendor_id     INTEGER REFERENCES vendor(id),
            nominal       REAL NOT NULL CHECK (nominal > 0),
            tanggal       TEXT NOT NULL,
            keterangan    TEXT,
            -- data khusus BBM
            odometer      INTEGER,
            liter         REAL,
            harga_liter   REAL,
            tangki_penuh  INTEGER,
            -- lampiran & sumber
            lampiran      TEXT,                             -- path foto nota
            sumber        TEXT NOT NULL DEFAULT 'admin' CHECK (sumber IN ('admin','kru')),
            client_uuid   TEXT UNIQUE,                      -- idempotency key dari HP kru
            status        TEXT NOT NULL DEFAULT 'menunggu'
                            CHECK (status IN ('menunggu','disetujui','ditolak')),
            flag_anomali  INTEGER NOT NULL DEFAULT 0,
            alasan_reject TEXT,
            disetujui_oleh INTEGER REFERENCES pengguna(id),
            disetujui_pada TEXT,
            dibuat_oleh    INTEGER REFERENCES pengguna(id),
            dibuat_pada    TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    // ---------------------------------------------------------------------
    // 4. Uang jalan (kasbon kru per rit)
    // ---------------------------------------------------------------------
    'uang_jalan' => "
        CREATE TABLE IF NOT EXISTS uang_jalan (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id        INTEGER NOT NULL REFERENCES pool(id),
            rit_id         INTEGER NOT NULL REFERENCES rit(id),
            kru_id         INTEGER NOT NULL REFERENCES kru(id),
            nominal_diberikan REAL NOT NULL DEFAULT 0,
            status         TEXT NOT NULL DEFAULT 'belum_cair'
                             CHECK (status IN ('belum_cair','dicairkan','dilaporkan','disetujui','lunas')),
            selisih        REAL,                    -- diberikan - realisasi (dihitung saat rekonsiliasi)
            alasan_selisih TEXT,
            rekonsiliasi_oleh INTEGER REFERENCES pengguna(id),
            rekonsiliasi_pada TEXT,
            dicairkan_oleh INTEGER REFERENCES pengguna(id),
            dicairkan_pada TEXT,
            bukti_setoran  TEXT,                     -- lampiran transfer/tanda terima
            dibuat_pada    TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    // ---------------------------------------------------------------------
    // 6. Pendapatan
    // ---------------------------------------------------------------------
    'pendapatan' => "
        CREATE TABLE IF NOT EXISTS pendapatan (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id     INTEGER NOT NULL REFERENCES pool(id),
            rit_id      INTEGER NOT NULL REFERENCES rit(id),
            jenis       TEXT NOT NULL DEFAULT 'tiket' CHECK (jenis IN ('tiket','kargo','paket','carter','lain')),
            nominal     REAL NOT NULL CHECK (nominal >= 0),
            jumlah_penumpang INTEGER,
            keterangan  TEXT,
            dibuat_oleh INTEGER REFERENCES pengguna(id),
            dibuat_pada TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    // ---------------------------------------------------------------------
    // 7. Perawatan & ban
    // ---------------------------------------------------------------------
    'perawatan' => "
        CREATE TABLE IF NOT EXISTS perawatan (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id        INTEGER NOT NULL REFERENCES pool(id),
            bus_id         INTEGER NOT NULL REFERENCES bus(id),
            vendor_id      INTEGER REFERENCES vendor(id),
            jenis          TEXT NOT NULL DEFAULT 'servis' CHECK (jenis IN ('servis','perbaikan','ganti_ban','lain')),
            tanggal_masuk  TEXT NOT NULL,
            tanggal_keluar TEXT,
            odometer       INTEGER,
            keluhan        TEXT,
            tindakan       TEXT,
            odometer_servis_berikutnya INTEGER,
            total          REAL NOT NULL DEFAULT 0,
            dibuat_pada    TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    'perawatan_item' => "
        CREATE TABLE IF NOT EXISTS perawatan_item (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            perawatan_id  INTEGER NOT NULL REFERENCES perawatan(id) ON DELETE CASCADE,
            jenis         TEXT NOT NULL DEFAULT 'sparepart' CHECK (jenis IN ('sparepart','jasa')),
            nama          TEXT NOT NULL,
            qty           REAL NOT NULL DEFAULT 1,
            harga         REAL NOT NULL DEFAULT 0,
            subtotal      REAL NOT NULL DEFAULT 0
        )",

    'ban' => "
        CREATE TABLE IF NOT EXISTS ban (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id      INTEGER NOT NULL REFERENCES pool(id),
            kode_seri    TEXT NOT NULL UNIQUE,
            merk         TEXT,
            dibuat_pada  TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    'ban_pasang' => "
        CREATE TABLE IF NOT EXISTS ban_pasang (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            ban_id        INTEGER NOT NULL REFERENCES ban(id),
            bus_id        INTEGER NOT NULL REFERENCES bus(id),
            posisi        TEXT NOT NULL,           -- mis. FL, FR, RL1, RR1, ...
            odometer_pasang INTEGER,
            tanggal_pasang TEXT NOT NULL DEFAULT (date('now')),
            odometer_lepas INTEGER,
            tanggal_lepas  TEXT,                   -- null = masih terpasang (aktif)
            dibuat_pada    TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    // Cegah dua ban aktif di posisi sama pada satu bus
    'idx_ban_posisi_aktif' => "
        CREATE UNIQUE INDEX IF NOT EXISTS idx_ban_posisi_aktif
            ON ban_pasang (bus_id, posisi) WHERE tanggal_lepas IS NULL",
    // Cegah satu ban terpasang di dua tempat sekaligus
    'idx_ban_aktif' => "
        CREATE UNIQUE INDEX IF NOT EXISTS idx_ban_aktif
            ON ban_pasang (ban_id) WHERE tanggal_lepas IS NULL",

    // ---------------------------------------------------------------------
    // 8. Dokumen kendaraan
    // ---------------------------------------------------------------------
    'dokumen_bus' => "
        CREATE TABLE IF NOT EXISTS dokumen_bus (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id      INTEGER NOT NULL REFERENCES pool(id),
            bus_id       INTEGER NOT NULL REFERENCES bus(id),
            jenis        TEXT NOT NULL CHECK (jenis IN ('stnk','kir','izin_trayek','asuransi','kartu_pengawasan','lain')),
            nomor        TEXT,
            berlaku_dari TEXT,
            jatuh_tempo  TEXT,
            lampiran     TEXT,
            dibuat_pada  TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    // ---------------------------------------------------------------------
    // 9. Kas & kasbon
    // ---------------------------------------------------------------------
    'akun_kas' => "
        CREATE TABLE IF NOT EXISTS akun_kas (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id     INTEGER NOT NULL REFERENCES pool(id),
            nama        TEXT NOT NULL,
            jenis       TEXT NOT NULL DEFAULT 'kas' CHECK (jenis IN ('kas','bank')),
            no_rekening TEXT,
            saldo_awal  REAL NOT NULL DEFAULT 0,
            aktif       INTEGER NOT NULL DEFAULT 1,
            dibuat_pada TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    // Buku besar kas (append-only). Saldo dihitung dari agregat, bukan kolom statis.
    'mutasi_kas' => "
        CREATE TABLE IF NOT EXISTS mutasi_kas (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id     INTEGER NOT NULL REFERENCES pool(id),
            akun_id     INTEGER NOT NULL REFERENCES akun_kas(id),
            tanggal     TEXT NOT NULL DEFAULT (date('now')),
            arah        TEXT NOT NULL CHECK (arah IN ('masuk','keluar')),
            nominal     REAL NOT NULL CHECK (nominal > 0),
            keterangan  TEXT,
            ref_tipe    TEXT,   -- 'uang_jalan' | 'pengeluaran' | 'pendapatan' | 'manual'
            ref_id      INTEGER,
            dibuat_oleh INTEGER REFERENCES pengguna(id),
            dibuat_pada TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    // Buku besar kasbon per kru (append-only)
    'kasbon' => "
        CREATE TABLE IF NOT EXISTS kasbon (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id     INTEGER NOT NULL REFERENCES pool(id),
            kru_id      INTEGER NOT NULL REFERENCES kru(id),
            tanggal     TEXT NOT NULL DEFAULT (date('now')),
            arah        TEXT NOT NULL CHECK (arah IN ('debit','kredit')), -- debit=nambah utang kru, kredit=setor
            nominal     REAL NOT NULL CHECK (nominal > 0),
            keterangan  TEXT,
            ref_tipe    TEXT,
            ref_id      INTEGER,
            dibuat_pada TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    // ---------------------------------------------------------------------
    // 11. Notifikasi
    // ---------------------------------------------------------------------
    'notifikasi' => "
        CREATE TABLE IF NOT EXISTS notifikasi (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            pool_id     INTEGER REFERENCES pool(id),
            pengguna_id INTEGER REFERENCES pengguna(id),  -- null = broadcast per pool/role
            role        TEXT,                              -- target role (opsional)
            tipe        TEXT NOT NULL,                     -- approval|dokumen|kasbon|sim
            judul       TEXT NOT NULL,
            pesan       TEXT,
            link        TEXT,
            dibaca      INTEGER NOT NULL DEFAULT 0,
            dibuat_pada TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    // ---------------------------------------------------------------------
    // 14. Audit log
    // ---------------------------------------------------------------------
    'audit_log' => "
        CREATE TABLE IF NOT EXISTS audit_log (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            pengguna_id INTEGER REFERENCES pengguna(id),
            aksi        TEXT NOT NULL,        -- approve|reject|edit_setelah_tutup|hapus_master|login|...
            entitas     TEXT,
            entitas_id  INTEGER,
            detail      TEXT,
            ip          TEXT,
            waktu       TEXT NOT NULL DEFAULT (datetime('now'))
        )",

    // ---------------------------------------------------------------------
    // Index performa (bagian 15)
    // ---------------------------------------------------------------------
    'idx_pengeluaran_rit'    => "CREATE INDEX IF NOT EXISTS idx_pengeluaran_rit ON pengeluaran(rit_id)",
    'idx_pengeluaran_status' => "CREATE INDEX IF NOT EXISTS idx_pengeluaran_status ON pengeluaran(status)",
    'idx_pengeluaran_tgl'    => "CREATE INDEX IF NOT EXISTS idx_pengeluaran_tgl ON pengeluaran(tanggal)",
    'idx_rit_status'         => "CREATE INDEX IF NOT EXISTS idx_rit_status ON rit(status)",
    'idx_rit_bus'            => "CREATE INDEX IF NOT EXISTS idx_rit_bus ON rit(bus_id)",
    'idx_kasbon_kru'         => "CREATE INDEX IF NOT EXISTS idx_kasbon_kru ON kasbon(kru_id)",
    'idx_mutasi_akun'        => "CREATE INDEX IF NOT EXISTS idx_mutasi_akun ON mutasi_kas(akun_id)",
    'idx_notif_user'         => "CREATE INDEX IF NOT EXISTS idx_notif_user ON notifikasi(pengguna_id, dibaca)",
];

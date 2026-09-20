<?php
declare(strict_types=1);

use App\Core\App;

/**
 * Seeder data awal: 1 pool, kategori biaya standar, akun owner + peran lain,
 * ditambah beberapa master data & contoh rit agar aplikasi langsung bisa dieksplor.
 */

return static function (): void {
    $db = App::db();

    // Idempotent: kalau sudah ada pool, lewati
    if ((int) $db->scalar('SELECT COUNT(*) FROM pool') > 0) {
        echo "  (seed dilewati — data sudah ada)\n";
        return;
    }

    $db->transaction(function ($db) {
        // --- Pool ---
        $poolId = $db->insert('pool', [
            'nama' => 'Pool Pusat Jakarta', 'kota' => 'Jakarta',
            'alamat' => 'Jl. Terminal Pulogebang No. 1', 'aktif' => 1,
        ]);

        // --- Pengguna (owner + tiap peran) ---
        $hash = password_hash('password', PASSWORD_BCRYPT);
        $users = [
            ['nama' => 'Owner Utama',  'email' => 'owner@akap.test',    'role' => 'owner'],
            ['nama' => 'Manajer Ops',  'email' => 'manajer@akap.test',  'role' => 'manajer'],
            ['nama' => 'Staff Keuangan','email' => 'keuangan@akap.test', 'role' => 'keuangan'],
            ['nama' => 'Admin Pool',   'email' => 'admin@akap.test',    'role' => 'admin_pool'],
        ];
        foreach ($users as $u) {
            $db->insert('pengguna', [
                'pool_id' => $poolId, 'nama' => $u['nama'], 'email' => $u['email'],
                'password_hash' => $hash, 'role' => $u['role'], 'aktif' => 1,
            ]);
        }

        // --- Kategori biaya standar (dengan hierarki & flag bukti) ---
        $catOps = $db->insert('kategori_biaya', ['nama' => 'Biaya Operasional Rit', 'tipe' => 'variabel', 'wajib_bukti' => 0]);
        $bbm = $db->insert('kategori_biaya', ['induk_id' => $catOps, 'nama' => 'BBM (Solar)', 'tipe' => 'bbm', 'wajib_bukti' => 1]);
        $db->insert('kategori_biaya', ['induk_id' => $catOps, 'nama' => 'Tol', 'tipe' => 'variabel', 'wajib_bukti' => 1]);
        $db->insert('kategori_biaya', ['induk_id' => $catOps, 'nama' => 'Retribusi Terminal', 'tipe' => 'variabel', 'wajib_bukti' => 0]);
        $db->insert('kategori_biaya', ['induk_id' => $catOps, 'nama' => 'Uang Makan Kru', 'tipe' => 'variabel', 'wajib_bukti' => 0]);
        $db->insert('kategori_biaya', ['induk_id' => $catOps, 'nama' => 'Parkir', 'tipe' => 'variabel', 'wajib_bukti' => 0]);
        $catTetap = $db->insert('kategori_biaya', ['nama' => 'Biaya Tetap', 'tipe' => 'tetap', 'wajib_bukti' => 0]);
        $db->insert('kategori_biaya', ['induk_id' => $catTetap, 'nama' => 'Gaji Kru', 'tipe' => 'tetap', 'wajib_bukti' => 0]);
        $catRawat = $db->insert('kategori_biaya', ['nama' => 'Perawatan', 'tipe' => 'variabel', 'wajib_bukti' => 1]);
        $db->insert('kategori_biaya', ['induk_id' => $catRawat, 'nama' => 'Servis Berkala', 'tipe' => 'variabel', 'wajib_bukti' => 1]);
        $db->insert('kategori_biaya', ['induk_id' => $catRawat, 'nama' => 'Ganti Ban', 'tipe' => 'variabel', 'wajib_bukti' => 1]);

        // --- Bus ---
        $bus1 = $db->insert('bus', ['pool_id' => $poolId, 'nopol' => 'B 7001 AKAP', 'kelas' => 'eksekutif', 'karoseri' => 'Adiputro', 'tahun' => 2021, 'odometer' => 340000, 'status' => 'aktif']);
        $bus2 = $db->insert('bus', ['pool_id' => $poolId, 'nopol' => 'B 7002 AKAP', 'kelas' => 'bisnis', 'karoseri' => 'Laksana', 'tahun' => 2019, 'odometer' => 510000, 'status' => 'aktif']);
        $db->insert('bus', ['pool_id' => $poolId, 'nopol' => 'B 7003 AKAP', 'kelas' => 'ekonomi', 'karoseri' => 'Tentrem', 'tahun' => 2018, 'odometer' => 620000, 'status' => 'perawatan']);

        // --- Rute ---
        $rute1 = $db->insert('rute', ['pool_id' => $poolId, 'kode' => 'JKT-SBY', 'asal' => 'Jakarta', 'tujuan' => 'Surabaya', 'jarak_km' => 780, 'estimasi_jam' => 12, 'aktif' => 1]);
        $rute2 = $db->insert('rute', ['pool_id' => $poolId, 'kode' => 'JKT-JOG', 'asal' => 'Jakarta', 'tujuan' => 'Yogyakarta', 'jarak_km' => 560, 'estimasi_jam' => 9, 'aktif' => 1]);

        // --- Kru ---
        $kru1 = $db->insert('kru', ['pool_id' => $poolId, 'nama' => 'Sukirman', 'no_hp' => '081200000001', 'posisi' => 'sopir', 'no_sim' => 'SIM-B2-001', 'sim_berlaku_sampai' => date('Y-m-d', strtotime('+40 days')), 'aktif' => 1]);
        $kru2 = $db->insert('kru', ['pool_id' => $poolId, 'nama' => 'Bambang', 'no_hp' => '081200000002', 'posisi' => 'sopir_2', 'no_sim' => 'SIM-B2-002', 'sim_berlaku_sampai' => date('Y-m-d', strtotime('+400 days')), 'aktif' => 1]);
        $db->insert('kru', ['pool_id' => $poolId, 'nama' => 'Joko', 'no_hp' => '081200000003', 'posisi' => 'kernet', 'aktif' => 1]);

        // Akun pengguna untuk kru pertama
        $db->insert('pengguna', [
            'pool_id' => $poolId, 'nama' => 'Sukirman (Kru)', 'email' => 'kru@akap.test',
            'password_hash' => $hash, 'role' => 'kru', 'kru_id' => $kru1, 'aktif' => 1,
        ]);

        // --- Vendor ---
        $spbu = $db->insert('vendor', ['pool_id' => $poolId, 'nama' => 'SPBU Cikampek', 'jenis' => 'spbu', 'aktif' => 1]);
        $db->insert('vendor', ['pool_id' => $poolId, 'nama' => 'Bengkel Jaya Motor', 'jenis' => 'bengkel', 'aktif' => 1]);

        // --- Standar biaya per rute ---
        $db->insert('standar_biaya_rute', ['rute_id' => $rute1, 'kategori_id' => $bbm, 'kelas_bus' => null, 'nominal_standar' => 3200000, 'toleransi_pct' => 10]);
        $db->insert('standar_biaya_rute', ['rute_id' => $rute2, 'kategori_id' => $bbm, 'kelas_bus' => null, 'nominal_standar' => 2300000, 'toleransi_pct' => 10]);

        // --- Akun kas ---
        $db->insert('akun_kas', ['pool_id' => $poolId, 'nama' => 'Kas Operasional Pool', 'jenis' => 'kas', 'saldo_awal' => 50000000, 'aktif' => 1]);
        $db->insert('akun_kas', ['pool_id' => $poolId, 'nama' => 'Bank BCA Pool', 'jenis' => 'bank', 'no_rekening' => '1234567890', 'saldo_awal' => 100000000, 'aktif' => 1]);

        echo "  Pool, 5 akun pengguna, kategori biaya, 3 bus, 2 rute, 3 kru, vendor, standar biaya & akun kas dibuat.\n";
    });
};

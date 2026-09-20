<?php
declare(strict_types=1);

/**
 * View SQL untuk laporan (analog v_rekap_rit & v_konsumsi_bbm yang disebut di checklist).
 * SQLite mendukung CREATE VIEW; sintaks kompatibel PostgreSQL.
 */

return [
    'drop_v_rekap_rit'   => "DROP VIEW IF EXISTS v_rekap_rit",
    'drop_v_konsumsi'    => "DROP VIEW IF EXISTS v_konsumsi_bbm",
    'drop_v_biaya_km'    => "DROP VIEW IF EXISTS v_biaya_per_km",

    // Rekap per rit: total pendapatan, total biaya disetujui, margin
    'v_rekap_rit' => "
        CREATE VIEW v_rekap_rit AS
        SELECT
            r.id                AS rit_id,
            r.kode,
            r.pool_id,
            r.tanggal,
            r.status,
            r.bus_id,
            r.rute_id,
            COALESCE((SELECT SUM(p.nominal) FROM pendapatan p WHERE p.rit_id = r.id), 0)                    AS total_pendapatan,
            COALESCE((SELECT SUM(e.nominal) FROM pengeluaran e WHERE e.rit_id = r.id AND e.status='disetujui'), 0) AS total_biaya,
            COALESCE((SELECT SUM(p.nominal) FROM pendapatan p WHERE p.rit_id = r.id), 0)
              - COALESCE((SELECT SUM(e.nominal) FROM pengeluaran e WHERE e.rit_id = r.id AND e.status='disetujui'), 0) AS margin,
            (CASE WHEN r.km_awal IS NOT NULL AND r.km_akhir IS NOT NULL AND r.km_akhir > r.km_awal
                  THEN r.km_akhir - r.km_awal ELSE NULL END) AS jarak_tempuh_km
        FROM rit r",

    // Konsumsi BBM per pengisian (km/liter). Anomali ditandai di layer aplikasi.
    'v_konsumsi_bbm' => "
        CREATE VIEW v_konsumsi_bbm AS
        SELECT
            e.id            AS pengeluaran_id,
            e.pool_id,
            r.bus_id,
            e.rit_id,
            e.tanggal,
            e.odometer,
            e.liter,
            e.harga_liter,
            e.nominal,
            LAG(e.odometer) OVER (PARTITION BY r.bus_id ORDER BY e.odometer) AS odometer_sebelumnya,
            (e.odometer - LAG(e.odometer) OVER (PARTITION BY r.bus_id ORDER BY e.odometer)) AS jarak,
            CASE WHEN e.liter > 0 AND e.odometer IS NOT NULL
                 THEN (e.odometer - LAG(e.odometer) OVER (PARTITION BY r.bus_id ORDER BY e.odometer)) / e.liter
                 ELSE NULL END AS km_per_liter
        FROM pengeluaran e
        JOIN kategori_biaya k ON k.id = e.kategori_id AND k.tipe = 'bbm'
        JOIN rit r            ON r.id = e.rit_id
        WHERE e.status = 'disetujui' AND e.odometer IS NOT NULL AND e.liter > 0",

    // Biaya per km per bus (agregat), untuk laporan & drill-down
    'v_biaya_per_km' => "
        CREATE VIEW v_biaya_per_km AS
        SELECT
            b.id   AS bus_id,
            b.nopol,
            b.pool_id,
            COALESCE(SUM(vr.total_biaya), 0)      AS total_biaya,
            COALESCE(SUM(vr.jarak_tempuh_km), 0)  AS total_km,
            CASE WHEN COALESCE(SUM(vr.jarak_tempuh_km),0) > 0
                 THEN SUM(vr.total_biaya) * 1.0 / SUM(vr.jarak_tempuh_km)
                 ELSE NULL END AS biaya_per_km
        FROM bus b
        LEFT JOIN v_rekap_rit vr ON vr.bus_id = b.id
        GROUP BY b.id, b.nopol, b.pool_id",
];

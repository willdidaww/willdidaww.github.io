<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Support\Exporter;

/**
 * Laporan & dashboard (bagian 12): biaya per km, konsumsi BBM (anomali),
 * margin per rute/bus, rekap kasbon, filter tanggal, export Excel/CSV.
 */
final class LaporanController extends Controller
{
    private function poolScope(string $col = 'pool_id'): string
    {
        return $this->poolId() ? " AND $col = " . (int) $this->poolId() : '';
    }

    public function index(Request $req): Response
    {
        return $this->view('laporan/index', ['title' => 'Laporan']);
    }

    public function biayaPerKm(Request $req): Response
    {
        $rows = $this->db()->all("
            SELECT * FROM v_biaya_per_km WHERE 1=1 " . $this->poolScope() . " ORDER BY biaya_per_km DESC");
        return $this->view('laporan/biaya_per_km', ['title' => 'Biaya per KM', 'rows' => $rows]);
    }

    public function konsumsiBbm(Request $req): Response
    {
        $rows = $this->db()->all("
            SELECT vk.*, b.nopol FROM v_konsumsi_bbm vk JOIN bus b ON b.id=vk.bus_id
            WHERE 1=1 " . $this->poolScope('vk.pool_id') . " ORDER BY vk.tanggal DESC");
        // Hitung rata-rata km/liter per bus untuk deteksi anomali (±25%)
        $avg = [];
        foreach ($rows as $r) {
            if ($r['km_per_liter']) { $avg[$r['bus_id']][] = (float) $r['km_per_liter']; }
        }
        $mean = [];
        foreach ($avg as $bus => $vals) { $mean[$bus] = array_sum($vals) / count($vals); }
        foreach ($rows as &$r) {
            $r['anomali'] = false;
            if ($r['km_per_liter'] && isset($mean[$r['bus_id']]) && $mean[$r['bus_id']] > 0) {
                $dev = abs((float) $r['km_per_liter'] - $mean[$r['bus_id']]) / $mean[$r['bus_id']];
                $r['anomali'] = $dev > 0.25;
            }
        }
        unset($r);
        return $this->view('laporan/konsumsi_bbm', ['title' => 'Konsumsi BBM', 'rows' => $rows, 'mean' => $mean]);
    }

    public function margin(Request $req): Response
    {
        $dari = $req->input('dari', '');
        $sampai = $req->input('sampai', '');
        $cond = ''; $params = [];
        if ($dari) { $cond .= ' AND vr.tanggal >= ?'; $params[] = $dari; }
        if ($sampai) { $cond .= ' AND vr.tanggal <= ?'; $params[] = $sampai; }

        $perRute = $this->db()->all("
            SELECT ru.asal, ru.tujuan,
              COALESCE(SUM(vr.total_pendapatan),0) AS pendapatan,
              COALESCE(SUM(vr.total_biaya),0) AS biaya,
              COALESCE(SUM(vr.margin),0) AS margin
            FROM v_rekap_rit vr JOIN rute ru ON ru.id=vr.rute_id
            WHERE 1=1 " . $this->poolScope('vr.pool_id') . " $cond
            GROUP BY vr.rute_id ORDER BY margin DESC", $params);

        $perBus = $this->db()->all("
            SELECT b.nopol,
              COALESCE(SUM(vr.total_pendapatan),0) AS pendapatan,
              COALESCE(SUM(vr.total_biaya),0) AS biaya,
              COALESCE(SUM(vr.margin),0) AS margin
            FROM v_rekap_rit vr JOIN bus b ON b.id=vr.bus_id
            WHERE 1=1 " . $this->poolScope('vr.pool_id') . " $cond
            GROUP BY vr.bus_id ORDER BY margin DESC", $params);

        $kasbon = $this->db()->all("
            SELECT k.nama, COALESCE(SUM(CASE WHEN kb.arah='debit' THEN kb.nominal ELSE -kb.nominal END),0) AS saldo
            FROM kru k LEFT JOIN kasbon kb ON kb.kru_id=k.id
            WHERE k.aktif=1 " . $this->poolScope('k.pool_id') . "
            GROUP BY k.id HAVING saldo > 0 ORDER BY saldo DESC");

        return $this->view('laporan/margin', [
            'title' => 'Laporan Margin', 'perRute' => $perRute, 'perBus' => $perBus,
            'kasbon' => $kasbon, 'dari' => $dari, 'sampai' => $sampai,
        ]);
    }

    /** Export CSV/Excel-compatible (bagian 12). */
    public function export(Request $req): Response
    {
        $jenis = $req->input('jenis', 'margin_rute');
        [$header, $rows, $fname] = match ($jenis) {
            'biaya_per_km' => [
                ['Nopol', 'Total Biaya', 'Total KM', 'Biaya/KM'],
                array_map(fn($r) => [$r['nopol'], $r['total_biaya'], $r['total_km'], round((float)$r['biaya_per_km'], 2)],
                    $this->db()->all("SELECT * FROM v_biaya_per_km WHERE 1=1" . $this->poolScope())),
                'biaya_per_km',
            ],
            'konsumsi_bbm' => [
                ['Tanggal', 'Bus', 'Odometer', 'Liter', 'Jarak', 'KM/Liter'],
                array_map(fn($r) => [$r['tanggal'], $r['nopol'], $r['odometer'], $r['liter'], $r['jarak'], round((float)($r['km_per_liter'] ?? 0), 2)],
                    $this->db()->all("SELECT vk.*, b.nopol FROM v_konsumsi_bbm vk JOIN bus b ON b.id=vk.bus_id WHERE 1=1" . $this->poolScope('vk.pool_id'))),
                'konsumsi_bbm',
            ],
            default => [
                ['Asal', 'Tujuan', 'Pendapatan', 'Biaya', 'Margin'],
                array_map(fn($r) => [$r['asal'], $r['tujuan'], $r['pendapatan'], $r['biaya'], $r['margin']],
                    $this->db()->all("SELECT ru.asal, ru.tujuan, COALESCE(SUM(vr.total_pendapatan),0) pendapatan,
                        COALESCE(SUM(vr.total_biaya),0) biaya, COALESCE(SUM(vr.margin),0) margin
                        FROM v_rekap_rit vr JOIN rute ru ON ru.id=vr.rute_id WHERE 1=1" . $this->poolScope('vr.pool_id') . " GROUP BY vr.rute_id")),
                'margin_rute',
            ],
        };
        $csv = Exporter::csv($header, $rows);
        return Response::download($csv, $fname . '_' . date('Ymd') . '.csv', 'text/csv; charset=utf-8');
    }
}

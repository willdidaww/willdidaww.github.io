<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

/**
 * Dashboard ringkasan (bagian 12): rit berjalan, menunggu approval,
 * kasbon belum setor, dokumen jatuh tempo, plus data chart biaya vs pendapatan.
 */
final class DashboardController extends Controller
{
    public function index(Request $req): Response
    {
        $db = $this->db();
        $pool = $this->poolId();
        $scope = $pool ? 'AND pool_id = ' . (int) $pool : '';

        $ritBerjalan = (int) $db->scalar("SELECT COUNT(*) FROM rit WHERE status = 'berjalan' $scope");
        $ritRencana  = (int) $db->scalar("SELECT COUNT(*) FROM rit WHERE status = 'rencana' $scope");
        $menungguApproval = (int) $db->scalar("SELECT COUNT(*) FROM pengeluaran WHERE status = 'menunggu' $scope");
        $anomali = (int) $db->scalar("SELECT COUNT(*) FROM pengeluaran WHERE status = 'menunggu' AND flag_anomali = 1 $scope");

        // Kasbon belum setor: saldo kasbon per kru > 0 dari uang jalan yg statusnya belum lunas
        $kasbonBelumSetor = (float) $db->scalar("
            SELECT COALESCE(SUM(CASE WHEN arah='debit' THEN nominal ELSE -nominal END),0)
            FROM kasbon " . ($pool ? "WHERE pool_id = " . (int)$pool : ''));

        // Dokumen jatuh tempo 30 hari ke depan
        $dokJatuhTempo = $db->all("
            SELECT d.*, b.nopol FROM dokumen_bus d JOIN bus b ON b.id = d.bus_id
            WHERE d.jatuh_tempo IS NOT NULL AND d.jatuh_tempo <= date('now','+30 day')
            " . ($pool ? "AND d.pool_id = " . (int)$pool : '') . "
            ORDER BY d.jatuh_tempo ASC LIMIT 8");

        // SIM kru mendekati kedaluwarsa
        $simExpiring = $db->all("
            SELECT nama, no_sim, sim_berlaku_sampai FROM kru
            WHERE aktif = 1 AND sim_berlaku_sampai IS NOT NULL AND sim_berlaku_sampai <= date('now','+60 day')
            " . ($pool ? "AND pool_id = " . (int)$pool : '') . "
            ORDER BY sim_berlaku_sampai ASC LIMIT 8");

        // Total pendapatan & biaya bulan berjalan
        $bulan = date('Y-m');
        $pendapatanBulan = (float) $db->scalar("SELECT COALESCE(SUM(nominal),0) FROM pendapatan WHERE strftime('%Y-%m', dibuat_pada) = ? $scope", [$bulan]);
        $biayaBulan = (float) $db->scalar("SELECT COALESCE(SUM(nominal),0) FROM pengeluaran WHERE status='disetujui' AND strftime('%Y-%m', tanggal) = ? $scope", [$bulan]);

        // Data chart: 6 bulan terakhir pendapatan vs biaya disetujui
        $chart = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = date('Y-m', strtotime("first day of -$i month"));
            $chart[] = [
                'bulan' => date('M', strtotime($m . '-01')),
                'pendapatan' => (float) $db->scalar("SELECT COALESCE(SUM(nominal),0) FROM pendapatan WHERE strftime('%Y-%m', dibuat_pada)=? $scope", [$m]),
                'biaya' => (float) $db->scalar("SELECT COALESCE(SUM(nominal),0) FROM pengeluaran WHERE status='disetujui' AND strftime('%Y-%m', tanggal)=? $scope", [$m]),
            ];
        }

        // Rit terbaru
        $ritTerbaru = $db->all("
            SELECT r.*, b.nopol, ru.asal, ru.tujuan,
                   (SELECT COALESCE(SUM(nominal),0) FROM pengeluaran WHERE rit_id=r.id AND status='disetujui') AS biaya
            FROM rit r JOIN bus b ON b.id=r.bus_id JOIN rute ru ON ru.id=r.rute_id
            WHERE 1=1 " . ($pool ? "AND r.pool_id=".(int)$pool : '') . "
            ORDER BY r.dibuat_pada DESC LIMIT 6");

        return $this->view('dashboard', [
            'title' => 'Dashboard',
            'ritBerjalan' => $ritBerjalan, 'ritRencana' => $ritRencana,
            'menungguApproval' => $menungguApproval, 'anomali' => $anomali,
            'kasbonBelumSetor' => $kasbonBelumSetor,
            'dokJatuhTempo' => $dokJatuhTempo, 'simExpiring' => $simExpiring,
            'pendapatanBulan' => $pendapatanBulan, 'biayaBulan' => $biayaBulan,
            'chart' => $chart, 'ritTerbaru' => $ritTerbaru,
        ]);
    }
}

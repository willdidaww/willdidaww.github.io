<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Upload;
use App\Support\Validator;

/**
 * Dokumen kendaraan (bagian 8): STNK/KIR/izin trayek/asuransi/kartu pengawasan,
 * reminder jatuh tempo, upload scan.
 */
final class DokumenController extends Controller
{
    public function index(Request $req): Response
    {
        $scope = $this->poolId() ? ' AND d.pool_id = ' . (int) $this->poolId() : '';
        $rows = $this->db()->all("
            SELECT d.*, b.nopol FROM dokumen_bus d JOIN bus b ON b.id=d.bus_id
            WHERE 1=1 $scope ORDER BY d.jatuh_tempo IS NULL, d.jatuh_tempo ASC");
        foreach ($rows as &$r) {
            $r['warning'] = $r['jatuh_tempo'] && strtotime($r['jatuh_tempo']) <= strtotime('+30 day');
            $r['expired'] = $r['jatuh_tempo'] && strtotime($r['jatuh_tempo']) < time();
        }
        unset($r);
        return $this->view('dokumen/index', [
            'title' => 'Dokumen Kendaraan', 'rows' => $rows,
            'buses' => $this->db()->all('SELECT id, nopol FROM bus ORDER BY nopol'),
        ]);
    }

    public function store(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate([
            'bus_id' => 'required|int',
            'jenis' => 'required|in:stnk,kir,izin_trayek,asuransi,kartu_pengawasan,lain',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $bus = $this->db()->first('SELECT pool_id FROM bus WHERE id = ?', [$d['bus_id']]);
        $lampiran = null;
        if ($file = $req->file('lampiran')) {
            try { $lampiran = Upload::store($file, 'dokumen'); }
            catch (\RuntimeException $e) { return $this->backWithErrors($req, [$e->getMessage()]); }
        }
        $this->db()->insert('dokumen_bus', [
            'pool_id' => $bus['pool_id'], 'bus_id' => $d['bus_id'], 'jenis' => $d['jenis'],
            'nomor' => Validator::clean($req->input('nomor')),
            'berlaku_dari' => $req->input('berlaku_dari') ?: null,
            'jatuh_tempo' => $req->input('jatuh_tempo') ?: null, 'lampiran' => $lampiran,
        ]);
        Session::flash('success', 'Dokumen disimpan.');
        return $this->redirect('/dokumen');
    }

    public function destroy(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $this->db()->delete('dokumen_bus', ['id' => (int) $req->param('id')]);
        Session::flash('success', 'Dokumen dihapus.');
        return $this->redirect('/dokumen');
    }
}

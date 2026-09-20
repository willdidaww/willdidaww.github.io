<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Validator;

/**
 * Kas & kasbon (bagian 9): akun kas/bank per pool, mutasi kas (buku besar),
 * saldo dihitung dari agregat, buku besar kasbon per kru (append-only).
 */
final class KasController extends Controller
{
    private const BATAS_KASBON = 5000000; // alert bila saldo kasbon kru melebihi

    public function index(Request $req): Response
    {
        $poolScope = $this->poolId() ? ' AND pool_id = ' . (int) $this->poolId() : '';

        // Saldo akun = saldo_awal + masuk - keluar (dihitung, bukan disimpan)
        $akun = $this->db()->all("SELECT * FROM akun_kas WHERE 1=1 $poolScope ORDER BY nama");
        foreach ($akun as &$a) {
            $masuk = (float) $this->db()->scalar("SELECT COALESCE(SUM(nominal),0) FROM mutasi_kas WHERE akun_id=? AND arah='masuk'", [$a['id']]);
            $keluar = (float) $this->db()->scalar("SELECT COALESCE(SUM(nominal),0) FROM mutasi_kas WHERE akun_id=? AND arah='keluar'", [$a['id']]);
            $a['saldo'] = (float) $a['saldo_awal'] + $masuk - $keluar;
        }
        unset($a);

        $mutasi = $this->db()->all("
            SELECT m.*, a.nama AS akun FROM mutasi_kas m JOIN akun_kas a ON a.id=m.akun_id
            WHERE 1=1 " . ($this->poolId() ? 'AND m.pool_id='.(int)$this->poolId() : '') . "
            ORDER BY m.dibuat_pada DESC LIMIT 50");

        // Saldo kasbon per kru (agregat)
        $kasbon = $this->db()->all("
            SELECT k.id, k.nama,
              COALESCE(SUM(CASE WHEN kb.arah='debit' THEN kb.nominal ELSE -kb.nominal END),0) AS saldo
            FROM kru k LEFT JOIN kasbon kb ON kb.kru_id=k.id
            WHERE k.aktif=1 " . ($this->poolId() ? 'AND k.pool_id='.(int)$this->poolId() : '') . "
            GROUP BY k.id, k.nama HAVING saldo <> 0 ORDER BY saldo DESC");
        foreach ($kasbon as &$k) {
            $k['alert'] = (float) $k['saldo'] > self::BATAS_KASBON;
        }
        unset($k);

        return $this->view('kas/index', [
            'title' => 'Kas & Kasbon', 'akun' => $akun, 'mutasi' => $mutasi, 'kasbon' => $kasbon,
            'batasKasbon' => self::BATAS_KASBON,
        ]);
    }

    public function storeAkun(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate(['nama' => 'required|string|max:60', 'jenis' => 'in:kas,bank', 'saldo_awal' => 'numeric|min:0'])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $this->db()->insert('akun_kas', [
            'pool_id' => $this->poolId(), 'nama' => $d['nama'], 'jenis' => $d['jenis'] ?? 'kas',
            'no_rekening' => Validator::clean($req->input('no_rekening')), 'saldo_awal' => $d['saldo_awal'] ?? 0, 'aktif' => 1,
        ]);
        Session::flash('success', 'Akun kas ditambahkan.');
        return $this->redirect('/kas');
    }

    public function storeMutasi(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate(['akun_id' => 'required|int', 'arah' => 'required|in:masuk,keluar', 'nominal' => 'required|numeric|gt:0'])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $this->db()->insert('mutasi_kas', [
            'pool_id' => $this->poolId(), 'akun_id' => $d['akun_id'], 'arah' => $d['arah'],
            'nominal' => $d['nominal'], 'keterangan' => Validator::clean($req->input('keterangan')),
            'ref_tipe' => 'manual', 'dibuat_oleh' => $this->userId(),
            'tanggal' => $req->input('tanggal') ?: date('Y-m-d'),
        ]);
        Session::flash('success', 'Mutasi kas dicatat.');
        return $this->redirect('/kas');
    }

    public function kasbonKru(Request $req): Response
    {
        $kruId = (int) $req->param('kruId');
        $kru = $this->db()->first('SELECT * FROM kru WHERE id = ?', [$kruId]);
        if (!$kru) { return $this->redirect('/kas'); }
        $rows = $this->db()->all('SELECT * FROM kasbon WHERE kru_id = ? ORDER BY dibuat_pada ASC', [$kruId]);
        // Hitung saldo berjalan
        $saldo = 0;
        foreach ($rows as &$r) {
            $saldo += $r['arah'] === 'debit' ? (float) $r['nominal'] : -(float) $r['nominal'];
            $r['saldo_berjalan'] = $saldo;
        }
        unset($r);
        return $this->view('kas/kasbon_kru', ['title' => 'Kasbon — ' . $kru['nama'], 'kru' => $kru, 'rows' => $rows, 'saldo' => $saldo]);
    }

    public function setorKasbon(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate(['kru_id' => 'required|int', 'nominal' => 'required|numeric|gt:0'])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $kru = $this->db()->first('SELECT pool_id FROM kru WHERE id = ?', [$d['kru_id']]);
        $this->db()->insert('kasbon', [
            'pool_id' => $kru['pool_id'], 'kru_id' => $d['kru_id'], 'arah' => 'kredit',
            'nominal' => $d['nominal'], 'keterangan' => Validator::clean($req->input('keterangan', 'Setoran kasbon')),
            'ref_tipe' => 'manual',
        ]);
        Session::flash('success', 'Setoran kasbon dicatat.');
        return $this->back($req);
    }
}

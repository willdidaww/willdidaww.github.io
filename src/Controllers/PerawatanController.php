<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Validator;

/**
 * Perawatan (bagian 7): catat servis + item (sparepart/jasa) subtotal otomatis,
 * reminder servis berikutnya (odometer), riwayat biaya per bus (TCO).
 */
final class PerawatanController extends Controller
{
    public function index(Request $req): Response
    {
        $scope = $this->poolId() ? ' AND p.pool_id = ' . (int) $this->poolId() : '';
        $rows = $this->db()->all("
            SELECT p.*, b.nopol, v.nama AS vendor
            FROM perawatan p JOIN bus b ON b.id=p.bus_id LEFT JOIN vendor v ON v.id=p.vendor_id
            WHERE 1=1 $scope ORDER BY p.tanggal_masuk DESC LIMIT 100");
        // Reminder servis: bus yang odometer terkini >= odometer_servis_berikutnya
        $reminder = $this->db()->all("
            SELECT b.nopol, b.odometer, p.odometer_servis_berikutnya
            FROM perawatan p JOIN bus b ON b.id=p.bus_id
            WHERE p.odometer_servis_berikutnya IS NOT NULL
              AND b.odometer >= p.odometer_servis_berikutnya - 5000
            GROUP BY b.id ORDER BY (b.odometer - p.odometer_servis_berikutnya) DESC");
        return $this->view('perawatan/index', ['title' => 'Perawatan', 'rows' => $rows, 'reminder' => $reminder]);
    }

    public function create(Request $req): Response
    {
        return $this->view('perawatan/form', [
            'title' => 'Catat Perawatan',
            'buses' => $this->db()->all('SELECT id, nopol FROM bus ORDER BY nopol'),
            'vendor' => $this->db()->all("SELECT id, nama FROM vendor WHERE jenis IN ('bengkel','sparepart') AND aktif=1 ORDER BY nama"),
        ]);
    }

    public function store(Request $req): Response
    {
        if ($c = $this->requireCsrf($req)) { return $c; }
        $v = Validator::make($req->body);
        if (!$v->validate([
            'bus_id' => 'required|int', 'jenis' => 'in:servis,perbaikan,ganti_ban,lain',
            'tanggal_masuk' => 'required|date',
        ])) {
            return $this->backWithErrors($req, $v->errors());
        }
        $d = $v->validated();
        $bus = $this->db()->first('SELECT pool_id FROM bus WHERE id = ?', [$d['bus_id']]);

        // Item: array paralel nama[], jenis_item[], qty[], harga[]
        $names = (array) $req->input('item_nama', []);
        $jenis = (array) $req->input('item_jenis', []);
        $qtys = (array) $req->input('item_qty', []);
        $hargas = (array) $req->input('item_harga', []);

        $this->db()->transaction(function ($db) use ($d, $bus, $req, $names, $jenis, $qtys, $hargas) {
            $total = 0;
            $items = [];
            foreach ($names as $i => $nama) {
                $nama = trim((string) $nama);
                if ($nama === '') { continue; }
                $q = (float) ($qtys[$i] ?? 1);
                $h = (float) ($hargas[$i] ?? 0);
                $sub = $q * $h;
                $total += $sub;
                $items[] = ['jenis' => in_array($jenis[$i] ?? 'sparepart', ['sparepart','jasa'], true) ? $jenis[$i] : 'sparepart',
                    'nama' => $nama, 'qty' => $q, 'harga' => $h, 'subtotal' => $sub];
            }
            $pid = $db->insert('perawatan', [
                'pool_id' => $bus['pool_id'], 'bus_id' => $d['bus_id'],
                'vendor_id' => $req->input('vendor_id') ? (int) $req->input('vendor_id') : null,
                'jenis' => $d['jenis'] ?? 'servis', 'tanggal_masuk' => $d['tanggal_masuk'],
                'tanggal_keluar' => $req->input('tanggal_keluar') ?: null,
                'odometer' => $req->input('odometer') ? (int) $req->input('odometer') : null,
                'keluhan' => Validator::clean($req->input('keluhan')),
                'tindakan' => Validator::clean($req->input('tindakan')),
                'odometer_servis_berikutnya' => $req->input('odometer_servis_berikutnya') ? (int) $req->input('odometer_servis_berikutnya') : null,
                'total' => $total,
            ]);
            foreach ($items as $it) {
                $it['perawatan_id'] = $pid;
                $db->insert('perawatan_item', $it);
            }
        });
        Session::flash('success', 'Perawatan dicatat.');
        return $this->redirect('/perawatan');
    }

    public function show(Request $req): Response
    {
        $id = (int) $req->param('id');
        $p = $this->db()->first('SELECT p.*, b.nopol, v.nama AS vendor FROM perawatan p JOIN bus b ON b.id=p.bus_id LEFT JOIN vendor v ON v.id=p.vendor_id WHERE p.id = ?', [$id]);
        if (!$p) { return $this->redirect('/perawatan'); }
        $items = $this->db()->all('SELECT * FROM perawatan_item WHERE perawatan_id = ?', [$id]);
        return $this->view('perawatan/show', ['title' => 'Detail Perawatan', 'p' => $p, 'items' => $items]);
    }
}

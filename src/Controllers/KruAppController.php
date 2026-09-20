<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Support\Notify;
use App\Support\Upload;

/**
 * PWA Kru (bagian 13): lihat rit miliknya, input pengeluaran + foto nota.
 * Idempotency via client_uuid. Kru hanya bisa akses rit miliknya (scoping ketat).
 */
final class KruAppController extends Controller
{
    private function kruId(): ?int
    {
        return $this->user()['kru_id'] ?? null;
    }

    public function home(Request $req): Response
    {
        if ($this->role() !== 'kru') {
            return $this->redirect('/');
        }
        $rits = $this->ritSayaData();
        return Response::html(\App\Core\View::page('kru/home', [
            '_flash' => \App\Core\Session::takeFlash(), '_csrf' => \App\Core\Session::csrfToken(),
            '_user' => $this->user(), 'title' => 'Rit Saya', 'rits' => $rits,
            'kategori' => $this->db()->all('SELECT id, nama, wajib_bukti, tipe FROM kategori_biaya WHERE aktif=1 ORDER BY nama'),
        ], 'layouts/kru'));
    }

    private function ritSayaData(): array
    {
        $kruId = $this->kruId();
        if (!$kruId) { return []; }
        return $this->db()->all("
            SELECT r.*, b.nopol, ru.asal, ru.tujuan,
              (SELECT COALESCE(SUM(nominal),0) FROM pengeluaran WHERE rit_id=r.id) AS total,
              (SELECT COALESCE(SUM(nominal_diberikan),0) FROM uang_jalan WHERE rit_id=r.id AND kru_id=?) AS uang_jalan
            FROM rit r
            JOIN rit_kru rk ON rk.rit_id=r.id AND rk.kru_id=?
            JOIN bus b ON b.id=r.bus_id JOIN rute ru ON ru.id=r.rute_id
            WHERE r.status IN ('rencana','berjalan','selesai')
            ORDER BY r.status='selesai', r.tanggal DESC", [$kruId, $kruId]);
    }

    /** API: rit milik kru (untuk sinkron PWA). */
    public function ritSaya(Request $req): Response
    {
        if ($this->role() !== 'kru') { return Response::json(['error' => 'Akses ditolak'], 403); }
        return Response::json(['data' => $this->ritSayaData()]);
    }

    /**
     * API: simpan pengeluaran dari HP kru. Idempotent via client_uuid.
     * Menerima JSON {client_uuid, rit_id, kategori_id, nominal, tanggal, keterangan, odometer, liter, harga_liter, tangki_penuh, foto(base64)}
     */
    public function simpanPengeluaran(Request $req): Response
    {
        if ($this->role() !== 'kru') { return Response::json(['error' => 'Akses ditolak'], 403); }
        if ($c = $this->requireCsrf($req)) { return $c; }

        $kruId = $this->kruId();
        $clientUuid = trim((string) $req->input('client_uuid'));
        if ($clientUuid === '') {
            return Response::json(['error' => 'client_uuid wajib (idempotency key)'], 422);
        }

        // Idempotency: bila sudah ada, kembalikan sukses (tanpa duplikasi)
        $existing = $this->db()->first('SELECT id, status FROM pengeluaran WHERE client_uuid = ?', [$clientUuid]);
        if ($existing) {
            return Response::json(['ok' => true, 'id' => (int) $existing['id'], 'status' => $existing['status'], 'duplikat' => true]);
        }

        $ritId = (int) $req->input('rit_id');
        // Scoping ketat: rit harus milik kru ini & masih aktif
        $rit = $this->db()->first("
            SELECT r.* FROM rit r JOIN rit_kru rk ON rk.rit_id=r.id AND rk.kru_id=?
            WHERE r.id = ? AND r.status IN ('rencana','berjalan')", [$kruId, $ritId]);
        if (!$rit) {
            return Response::json(['error' => 'Rit bukan milik Anda atau sudah ditutup'], 403);
        }

        $nominal = (float) $req->input('nominal');
        $kategoriId = (int) $req->input('kategori_id');
        if ($nominal <= 0 || !$kategoriId) {
            return Response::json(['error' => 'Nominal & kategori wajib, nominal > 0'], 422);
        }
        $tanggal = $req->input('tanggal') ?: date('Y-m-d');
        if (strtotime($tanggal) > time()) {
            return Response::json(['error' => 'Tanggal tidak boleh di masa depan'], 422);
        }

        // Foto (base64 hasil kompresi sisi klien)
        $lampiran = null;
        if ($foto = $req->input('foto')) {
            $lampiran = Upload::storeBase64((string) $foto, 'nota');
        }
        $kategori = $this->db()->first('SELECT wajib_bukti, nama FROM kategori_biaya WHERE id = ?', [$kategoriId]);
        if ($kategori && (int) $kategori['wajib_bukti'] === 1 && !$lampiran) {
            return Response::json(['error' => 'Kategori ini wajib foto nota'], 422);
        }

        $id = $this->db()->insert('pengeluaran', [
            'pool_id' => $rit['pool_id'], 'rit_id' => $ritId, 'kategori_id' => $kategoriId,
            'nominal' => $nominal, 'tanggal' => $tanggal,
            'keterangan' => \App\Support\Validator::clean($req->input('keterangan')),
            'odometer' => $req->input('odometer') ? (int) $req->input('odometer') : null,
            'liter' => $req->input('liter') ? (float) $req->input('liter') : null,
            'harga_liter' => $req->input('harga_liter') ? (float) $req->input('harga_liter') : null,
            'tangki_penuh' => $req->input('tangki_penuh') ? 1 : null,
            'lampiran' => $lampiran, 'sumber' => 'kru', 'client_uuid' => $clientUuid,
            'status' => 'menunggu', 'dibuat_oleh' => $this->userId(),
        ]);

        Notify::toRole($rit['pool_id'], 'manajer', 'approval', 'Pengeluaran dari kru',
            rupiah($nominal) . ' — ' . ($kategori['nama'] ?? ''), '/approval');

        return Response::json(['ok' => true, 'id' => $id, 'status' => 'menunggu']);
    }
}

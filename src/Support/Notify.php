<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\App;

/**
 * Pembuat notifikasi in-app (bagian 11).
 */
final class Notify
{
    /** Notifikasi ke pengguna tertentu. */
    public static function toUser(int $penggunaId, string $tipe, string $judul, string $pesan = '', ?string $link = null): void
    {
        App::db()->insert('notifikasi', [
            'pengguna_id' => $penggunaId, 'tipe' => $tipe,
            'judul' => $judul, 'pesan' => $pesan, 'link' => $link,
        ]);
    }

    /** Notifikasi ke seluruh pemegang role dalam satu pool. */
    public static function toRole(?int $poolId, string $role, string $tipe, string $judul, string $pesan = '', ?string $link = null): void
    {
        $sql = "SELECT id FROM pengguna WHERE role = ? AND aktif = 1";
        $params = [$role];
        if ($poolId !== null) {
            $sql .= " AND (pool_id = ? OR role IN ('owner','manajer'))";
            $params[] = $poolId;
        }
        foreach (App::db()->all($sql, $params) as $u) {
            self::toUser((int) $u['id'], $tipe, $judul, $pesan, $link);
        }
    }

    public static function unreadCount(int $penggunaId): int
    {
        return (int) App::db()->scalar(
            'SELECT COUNT(*) FROM notifikasi WHERE pengguna_id = ? AND dibaca = 0',
            [$penggunaId]
        );
    }
}

<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\App;

/**
 * Penyimpanan file upload (foto nota / dokumen). Driver lokal;
 * mudah diganti ke S3-compatible dengan mengganti implementasi store().
 */
final class Upload
{
    /**
     * Simpan file dari $_FILES. Kembalikan path publik (mis. /uploads/nota/xxx.jpg) atau null.
     * @param array $file elemen dari Request::file()
     */
    public static function store(array $file, string $folder = 'nota'): ?string
    {
        $maxBytes = (int) App::config('security.upload_max_bytes', 5 * 1024 * 1024);
        $allowed  = (array) App::config('security.upload_allowed', []);

        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return null;
        }
        if ($file['size'] > $maxBytes) {
            throw new \RuntimeException('Ukuran file melebihi batas.');
        }
        $mime = mime_content_type($file['tmp_name']) ?: '';
        if ($allowed && !in_array($mime, $allowed, true)) {
            throw new \RuntimeException('Tipe file tidak diizinkan.');
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png',
            'image/webp' => 'webp', 'application/pdf' => 'pdf',
            default => 'bin',
        };
        $dir = App::config('storage.uploads') . '/' . $folder;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = $dir . '/' . $name;

        // move_uploaded_file untuk request nyata; rename untuk data dari API (php://input sudah ditulis)
        if (is_uploaded_file($file['tmp_name'])) {
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                throw new \RuntimeException('Gagal menyimpan file.');
            }
        } else {
            if (!rename($file['tmp_name'], $dest)) {
                throw new \RuntimeException('Gagal menyimpan file.');
            }
        }
        return '/uploads/' . $folder . '/' . $name;
    }

    /** Simpan data base64 (dari kompresi sisi klien PWA kru). */
    public static function storeBase64(string $dataUri, string $folder = 'nota'): ?string
    {
        if (!preg_match('#^data:(image/\w+);base64,(.+)$#s', $dataUri, $m)) {
            return null;
        }
        $mime = $m[1];
        $bin = base64_decode($m[2], true);
        if ($bin === false) {
            return null;
        }
        $ext = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => 'bin',
        };
        $dir = App::config('storage.uploads') . '/' . $folder;
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        file_put_contents($dir . '/' . $name, $bin);
        return '/uploads/' . $folder . '/' . $name;
    }
}

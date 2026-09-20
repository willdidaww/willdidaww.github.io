<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Export data ke CSV (dibuka Excel). Untuk PDF: gunакан print-to-PDF dari view laporan
 * (tombol cetak disediakan di UI) agar tidak butuh dependensi eksternal.
 */
final class Exporter
{
    public static function csv(array $header, array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        // BOM agar Excel mengenali UTF-8
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $header, ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($fh, $row, ',', '"', '\\');
        }
        rewind($fh);
        $out = stream_get_contents($fh);
        fclose($fh);
        return $out;
    }
}

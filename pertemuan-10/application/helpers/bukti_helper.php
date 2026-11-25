<?php
// application/helpers/bukti_helper.php
// Mengirim file bukti pembayaran ke browser secara aman.

if (!function_exists('kirim_bukti_pembayaran')) {
    function kirim_bukti_pembayaran(string $filename): void
    {
        // Hanya terima nama file sesuai pola yang dibuat uploadBukti().
        if (!preg_match('/\Aft_byr_\d{14}_[a-f0-9]{16}\.(jpg|png|webp)\z/', $filename)) {
            http_response_code(404);
            exit('Bukti pembayaran tidak ditemukan.');
        }

        $path = dirname(APPPATH) . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'bayar' . DIRECTORY_SEPARATOR . $filename;

        if (!is_file($path) || !is_readable($path)) {
            http_response_code(404);
            exit('File bukti pembayaran tidak ditemukan.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            http_response_code(404);
            exit('Format bukti pembayaran tidak valid.');
        }

        // Bersihkan output buffer agar biner gambar tidak rusak.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');

        readfile($path);
        exit;
    }
}
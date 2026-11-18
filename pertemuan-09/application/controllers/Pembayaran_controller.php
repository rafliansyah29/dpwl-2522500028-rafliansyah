<?php
// P9 BARU - application/controllers/Pembayaran_controller.php
// Sisi penyewa: melihat riwayat transaksi dan mengunggah bukti pembayaran DP.
require_once APPPATH . 'models/Pembayaran_model.php';
require_once APPPATH . 'models/Transaksi_model.php';
require_once APPPATH . 'helpers/csrf_helper.php';

class Pembayaran_controller extends Controller
{
    private Pembayaran_model $pembayaranModel;
    private Transaksi_model $transaksiModel;

    public function __construct()
    {
        $this->pembayaranModel = new Pembayaran_model();
        $this->transaksiModel = new Transaksi_model();
    }

    // GET pembayaran : daftar transaksi penyewa dan status DP.
    public function riwayat(): void
    {
        $userPlg = $this->wajibLoginPenyewa();
        $this->wajibMetode('GET');

        try {
            $daftar = $this->pembayaranModel->getRiwayatDpPenyewa($userPlg);
            $jumlahKeranjang = $this->transaksiModel->getJumlahMobilKeranjang($userPlg);
        } catch (Throwable $e) {
            error_log('Riwayat pembayaran gagal: ' . $e->getMessage());
            http_response_code(500);
            exit('Riwayat transaksi belum dapat ditampilkan. Silakan coba kembali.');
        }

        $this->view('pembayaran/riwayat', [
            'daftar' => $daftar,
            'jumlahKeranjang' => $jumlahKeranjang
        ]);
    }

    // GET pembayaran/dp?kode_sewa=... : form unggah bukti.
    // POST pembayaran/dp               : memproses unggahan (PRG).
    public function bayarDp(): void
    {
        $userPlg = $this->wajibLoginPenyewa();
        $metode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($metode === 'GET') {
            $this->tampilkanForm($userPlg);
            return;
        }

        $this->wajibMetode('POST');
        $this->prosesUnggah($userPlg);
    }

    private function tampilkanForm(string $userPlg): void
    {
        $kodeSewa = trim((string) ($_GET['kode_sewa'] ?? ''));
        if ($kodeSewa === '') {
            $this->redirectDenganPesan(
                'pembayaran',
                'error',
                'Pilih transaksi yang akan dibayar terlebih dahulu.'
            );
        }

        try {
            $dp = $this->pembayaranModel->getDpPenyewa($kodeSewa, $userPlg);
            if ($dp === null) {
                http_response_code(404);
                exit('Transaksi tidak ditemukan atau bukan milik akun ini.');
            }
            $jumlahKeranjang = $this->transaksiModel->getJumlahMobilKeranjang($userPlg);
        } catch (Throwable $e) {
            error_log('Tampilan form pembayaran gagal: ' . $e->getMessage());
            http_response_code(500);
            exit('Halaman pembayaran belum dapat ditampilkan. Silakan coba kembali.');
        }

        $this->view('pembayaran/form_dp', [
            'dp' => $dp,
            'csrf' => csrf_token('pembayaran'),
            'jumlahKeranjang' => $jumlahKeranjang
        ]);
    }

    private function prosesUnggah(string $userPlg): void
    {
        $kodeSewa = trim((string) ($_POST['kode_sewa'] ?? ''));

        // Jika ukuran unggahan melebihi post_max_size, PHP mengosongkan $_POST.
        if (!csrf_valid('pembayaran', $_POST['csrf_token'] ?? null)) {
            $this->redirectDenganPesan(
                'pembayaran',
                'error',
                'Formulir tidak valid atau ukuran berkas melebihi batas. Muat ulang halaman lalu coba kembali.'
            );
        }

        if ($kodeSewa === '') {
            http_response_code(400);
            exit('Kode transaksi wajib diisi.');
        }

        $uriForm = 'pembayaran/dp?kode_sewa=' . rawurlencode($kodeSewa);

        $upload = $this->uploadBukti('ft_byr');
        if (!$upload['success']) {
            $this->redirectDenganPesan($uriForm, 'error', (string) $upload['error']);
        }
        $namaBaru = (string) $upload['filename'];

        try {
            $namaLama = $this->pembayaranModel->simpanBuktiDp(
                $kodeSewa,
                $userPlg,
                $namaBaru
            );
        } catch (InvalidArgumentException $e) {
            $this->hapusBukti($namaBaru);
            $this->redirectDenganPesan($uriForm, 'error', $e->getMessage());
        } catch (Throwable $e) {
            $this->hapusBukti($namaBaru);
            error_log('Simpan bukti pembayaran DP gagal: ' . $e->getMessage());
            $this->redirectDenganPesan(
                $uriForm,
                'error',
                'Bukti pembayaran belum berhasil disimpan. Silakan coba kembali.'
            );
        }

        // File lama dihapus hanya setelah database berhasil diperbarui.
        if ($namaLama !== null) {
            $this->hapusBukti($namaLama);
        }

        $this->redirectDenganPesan(
            'pembayaran',
            'success',
            'Bukti pembayaran DP berhasil diunggah. Pembayaran menunggu verifikasi admin.'
        );
    }

    private function wajibLoginPenyewa(): string
    {
        $userPlg = trim((string) ($_SESSION['penyewa_user_plg'] ?? ''));
        if ($userPlg === '') {
            $_SESSION['flash_penyewa_nav'] =
                'Silakan login terlebih dahulu untuk mengakses pembayaran.';
            header('Location: ' . site_url('auth/login-penyewa'));
            exit;
        }
        return $userPlg;
    }

    private function wajibMetode(string $metodeDiizinkan): void
    {
        $metodeAktif = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($metodeAktif !== $metodeDiizinkan) {
            http_response_code(405);
            header('Allow: ' . $metodeDiizinkan);
            exit('Method Not Allowed');
        }
    }

    // Flash memakai kunci flash_transaksi yang sudah ditampilkan oleh katalog/navbar.php.
    private function redirectDenganPesan(
        string $uri,
        string $jenis,
        string $pesan
    ): never {
        $_SESSION['flash_transaksi'] = [
            'type' => $jenis,
            'message' => $pesan
        ];
        header('Location: ' . site_url($uri));
        exit;
    }

    private function direktoriBukti(): string
    {
        return dirname(APPPATH) . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'bayar' . DIRECTORY_SEPARATOR;
    }

    // Pola unggah sama dengan Penyewa_controller/Mobil_controller, tetapi file WAJIB ada.
    private function uploadBukti(string $field): array
    {
        $gagal = static fn(string $pesan): array => [
            'success' => false,
            'filename' => null,
            'error' => $pesan
        ];

        if (
            !isset($_FILES[$field]) ||
            ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            return $gagal('Bukti pembayaran wajib diunggah.');
        }

        $file = $_FILES[$field];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $gagal('Unggah bukti gagal. Periksa ukuran file dan konfigurasi PHP.');
        }

        if (
            !isset($file['tmp_name'], $file['size']) ||
            !is_uploaded_file($file['tmp_name']) ||
            (int) $file['size'] < 1 ||
            (int) $file['size'] > 5 * 1024 * 1024
        ) {
            return $gagal('Bukti harus berukuran lebih dari 0 byte dan maksimal 5 MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $diizinkan = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];
        if (
            !is_string($mime) ||
            !isset($diizinkan[$mime]) ||
            @getimagesize($file['tmp_name']) === false
        ) {
            return $gagal('Format bukti harus JPEG, PNG, atau WEBP yang valid.');
        }

        $dir = $this->direktoriBukti();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return $gagal('Folder penyimpanan bukti pembayaran tidak dapat dibuat.');
        }
        if (!is_writable($dir)) {
            return $gagal('Folder penyimpanan bukti pembayaran tidak dapat ditulis.');
        }

        try {
            $filename = $field . '_' . date('YmdHis') . '_'
                . bin2hex(random_bytes(8)) . '.' . $diizinkan[$mime];
        } catch (Throwable $e) {
            return $gagal('Nama file bukti gagal dibuat.');
        }

        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            return $gagal('Bukti gagal dipindahkan ke folder penyimpanan.');
        }

        return [
            'success' => true,
            'filename' => $filename,
            'error' => null
        ];
    }

    // Hanya menghapus nama file yang cocok dengan pola uploadBukti().
    private function hapusBukti(?string $filename): void
    {
        if (
            $filename === null ||
            !preg_match('/\Aft_byr_\d{14}_[a-f0-9]{16}\.(jpg|png|webp)\z/', $filename)
        ) {
            return;
        }
        $path = $this->direktoriBukti() . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

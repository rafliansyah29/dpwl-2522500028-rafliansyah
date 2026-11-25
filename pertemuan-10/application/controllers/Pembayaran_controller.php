<?php
// P9 BARU - application/controllers/Pembayaran_controller.php
// Pembayaran DP oleh penyewa: daftar, formulir, unggah bukti, penyajian bukti.
require_once APPPATH . 'models/Pembayaran_model.php';
require_once APPPATH . 'models/Transaksi_model.php';
require_once APPPATH . 'helpers/auth_helper.php';
require_once APPPATH . 'helpers/csrf_helper.php';

class Pembayaran_controller extends Controller
{
    private const MAKS_UKURAN = 5 * 1024 * 1024; // 5 MB, sama dengan unggah foto P6
    private const POLA_BERKAS = '/^ft_byr_\d{10,14}_[a-f0-9]{12,16}\.(jpg|jpeg|png|webp)$/';

    private Pembayaran_model $model;
    private Transaksi_model $transaksiModel;

    public function __construct()
    {
        $this->model = new Pembayaran_model();
        $this->transaksiModel = new Transaksi_model();
    }

    // GET pembayaran
    public function index(): void
    {
        $userPlg = $this->wajibLoginPenyewa();
        $this->wajibMetode('GET');
        try {
            $this->view('pembayaran/daftar', [
                'daftar' => $this->model->getDaftarDpPenyewa($userPlg),
                'jumlahKeranjang' => $this->transaksiModel->getJumlahMobilKeranjang($userPlg)
            ]);
        } catch (Throwable $e) {
            error_log('Daftar pembayaran gagal: ' . $e->getMessage());
            http_response_code(500);
            exit('Daftar pembayaran belum dapat ditampilkan. Silakan coba kembali.');
        }
    }

    // GET pembayaran/bayar?kode_sewa=...
    public function bayar(): void
    {
        $userPlg = $this->wajibLoginPenyewa();
        $this->wajibMetode('GET');
        $kodeSewa = trim((string) ($_GET['kode_sewa'] ?? ''));
        if ($kodeSewa === '') {
            $this->redirectDenganPesan(
                'pembayaran',
                'error',
                'Kode transaksi tidak ditemukan.'
            );
        }
        try {
            $pembayaran = $this->model->getDpPenyewa($kodeSewa, $userPlg);
            if ($pembayaran === null) {
                http_response_code(404);
                exit('Pembayaran tidak ditemukan atau bukan milik akun ini.');
            }
            $this->view('pembayaran/bayar', [
                'pembayaran' => $pembayaran,
                'csrf' => csrf_token(),
                'jumlahKeranjang' => $this->transaksiModel->getJumlahMobilKeranjang($userPlg)
            ]);
        } catch (Throwable $e) {
            error_log('Halaman pembayaran gagal: ' . $e->getMessage());
            http_response_code(500);
            exit('Halaman pembayaran belum dapat ditampilkan. Silakan coba kembali.');
        }
    }

    // POST pembayaran/simpan
    public function simpan(): void
    {
        $userPlg = $this->wajibLoginPenyewa();
        $this->wajibMetode('POST');
        $kodeSewa = trim((string) ($_POST['kode_sewa'] ?? ''));
        $kodeBayar = trim((string) ($_POST['kode_bayar'] ?? ''));
        $uriBalik = 'pembayaran/bayar?kode_sewa=' . rawurlencode($kodeSewa);

        if (!csrf_valid($_POST['csrf'] ?? null)) {
            http_response_code(403);
            exit('Permintaan tidak valid. Muat ulang halaman lalu coba lagi.');
        }
        if ($kodeSewa === '' || $kodeBayar === '') {
            http_response_code(400);
            exit('Kode transaksi dan kode pembayaran wajib diisi.');
        }

        // Pastikan kode_bayar milik transaksi dan penyewa ini (bukan dari input mentah).
        try {
            $dp = $this->model->getDpPenyewa($kodeSewa, $userPlg);
        } catch (Throwable $e) {
            error_log('Pemeriksaan pembayaran gagal: ' . $e->getMessage());
            $this->redirectDenganPesan($uriBalik, 'error', 'Pembayaran belum dapat diproses. Silakan coba kembali.');
        }
        if ($dp === null || $dp['kode_bayar'] !== $kodeBayar) {
            http_response_code(404);
            exit('Pembayaran tidak ditemukan atau bukan milik akun ini.');
        }

        $upload = $this->unggahBukti('ft_byr');
        if (!$upload['success']) {
            $this->redirectDenganPesan($uriBalik, 'error', (string) $upload['error']);
        }
        if ($upload['filename'] === null) {
            $this->redirectDenganPesan($uriBalik, 'error', 'Bukti pembayaran wajib dipilih.');
        }

        try {
            $lama = $this->model->simpanBukti($kodeBayar, $userPlg, $upload['filename']);
        } catch (InvalidArgumentException $e) {
            $this->hapusBerkas($upload['filename']);
            $this->redirectDenganPesan($uriBalik, 'error', $e->getMessage());
        } catch (Throwable $e) {
            $this->hapusBerkas($upload['filename']);
            error_log('Simpan bukti pembayaran gagal: ' . $e->getMessage());
            $this->redirectDenganPesan(
                $uriBalik,
                'error',
                'Bukti pembayaran belum berhasil disimpan. Silakan coba kembali.'
            );
        }
        // Berkas lama dihapus hanya setelah database berhasil diperbarui.
        if ($lama !== null) {
            $this->hapusBerkas($lama);
        }
        $this->redirectDenganPesan(
            $uriBalik,
            'success',
            'Bukti pembayaran berhasil diunggah dan menunggu verifikasi admin.'
        );
    }

    // GET pembayaran/bukti?kode_bayar=... (admin atau pemilik transaksi)
    public function bukti(): void
    {
        $this->wajibMetode('GET');
        $adminLogin = is_admin_logged_in();
        $userPlg = trim((string) ($_SESSION['penyewa_user_plg'] ?? ''));
        if (!$adminLogin && $userPlg === '') {
            http_response_code(403);
            exit('Akses ditolak.');
        }
        $kodeBayar = trim((string) ($_GET['kode_bayar'] ?? ''));
        try {
            $info = $kodeBayar === '' ? null : $this->model->getBukti($kodeBayar);
        } catch (Throwable $e) {
            error_log('Pengambilan bukti gagal: ' . $e->getMessage());
            http_response_code(500);
            exit('Bukti belum dapat ditampilkan.');
        }
        $nama = (string) ($info['ft_byr'] ?? '');
        if (
            $info === null ||
            $nama === '' ||
            (!$adminLogin && $info['user_plg'] !== $userPlg) ||
            preg_match(self::POLA_BERKAS, $nama) !== 1
        ) {
            http_response_code(404);
            exit('Bukti pembayaran tidak ditemukan.');
        }
        $path = $this->folderBukti() . $nama;
        if (!is_file($path)) {
            http_response_code(404);
            exit('Berkas bukti tidak ditemukan.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            http_response_code(404);
            exit('Berkas bukti tidak valid.');
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0');
        readfile($path);
        exit;
    }

    // ---------- helper privat ----------

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
        $aktif = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($aktif !== $metodeDiizinkan) {
            http_response_code(405);
            header('Allow: ' . $metodeDiizinkan);
            exit('Method Not Allowed');
        }
    }

    private function redirectDenganPesan(string $uri, string $jenis, string $pesan): never
    {
        $_SESSION['flash_transaksi'] = ['type' => $jenis, 'message' => $pesan];
        header('Location: ' . site_url($uri));
        exit;
    }

    private function folderBukti(): string
    {
        return dirname(APPPATH) . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'bayar' . DIRECTORY_SEPARATOR;
    }

    private function hapusBerkas(string $nama): void
    {
        if (preg_match(self::POLA_BERKAS, $nama) !== 1) {
            return;
        }
        $path = $this->folderBukti() . $nama;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    // Pola sama dengan Penyewa_controller::uploadFotoPenyewa() (P6).
    private function unggahBukti(string $field): array
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
            return ['success' => true, 'filename' => null, 'error' => null];
        }
        $file = $_FILES[$field];
        if (is_array($file['error'])) {
            return $gagal('Unggah bukti tidak valid.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $gagal('Unggah bukti gagal. Periksa ukuran file dan konfigurasi PHP.');
        }
        if (
            !isset($file['tmp_name'], $file['size']) ||
            !is_uploaded_file($file['tmp_name']) ||
            (int) $file['size'] < 1 ||
            (int) $file['size'] > self::MAKS_UKURAN
        ) {
            return $gagal('Bukti harus berukuran lebih dari 0 byte dan maksimal 5 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $diizinkan = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($diizinkan[$mime]) || @getimagesize($file['tmp_name']) === false) {
            return $gagal('Format bukti harus JPEG, PNG, atau WEBP yang valid.');
        }
        $dir = $this->folderBukti();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return $gagal('Folder penyimpanan bukti tidak dapat dibuat.');
        }
        if (!is_writable($dir)) {
            return $gagal('Folder penyimpanan bukti tidak dapat ditulis.');
        }
        try {
            $nama = $field . '_' . date('YmdHis') . '_'
                . bin2hex(random_bytes(8)) . '.' . $diizinkan[$mime];
        } catch (Throwable $e) {
            return $gagal('Nama file bukti gagal dibuat.');
        }
        if (!move_uploaded_file($file['tmp_name'], $dir . $nama)) {
            return $gagal('Bukti gagal dipindahkan ke folder penyimpanan.');
        }
        return ['success' => true, 'filename' => $nama, 'error' => null];
    }

    /**
     * P10: Menampilkan daftar transaksi yang masih memiliki kewajiban pelunasan.
     */
    public function pelunasanIndex(): void
    {
        require_admin_login();
        $this->wajibMetode('GET');
        try {
            $daftarPelunasan = $this->model
                ->getDaftarPelunasan();
            $flash = $_SESSION['flash_transaksi'] ?? null;
            unset($_SESSION['flash_transaksi']);
            $this->view('admin/pelunasan', [
                'username' => $_SESSION['admin_username'],
                'daftarPelunasan' => $daftarPelunasan,
                'flash' => $flash
            ]);
        } catch (Throwable $e) {
            error_log(
                'Daftar pelunasan gagal ditampilkan: ' . $e->getMessage()
            );
            http_response_code(500);
            exit('Daftar transaksi pelunasan belum dapat ditampilkan. '
                . 'Silakan coba kembali.');
        }
    }

    /**
     * P10: Menampilkan detail transaksi, riwayat pembayaran, dan sisa kewajiban.
     */
    public function pelunasanDetail(string $kodeSewa = ''): void
    {
        require_admin_login();
        $this->wajibMetode('GET');
        $kodeSewa = trim(str_replace('~', '/', $kodeSewa));
        if ($kodeSewa === '') {
            http_response_code(400);
            exit('Kode transaksi sewa wajib tersedia.');
        }
        try {
            $detailPelunasan = $this->model
                ->getDetailPelunasan($kodeSewa);
            if ($detailPelunasan === null) {
                http_response_code(404);
                exit('Transaksi sewa tidak ditemukan.');
            }
            $flash = $_SESSION['flash_transaksi'] ?? null;
            unset($_SESSION['flash_transaksi']);
            $this->view('admin/pelunasan_detail', [
                'username' => $_SESSION['admin_username'],
                'detailPelunasan' => $detailPelunasan,
                'flash' => $flash
            ]);
        } catch (Throwable $e) {
            error_log(
                'Detail pelunasan gagal ditampilkan: ' . $e->getMessage()
            );
            http_response_code(500);
            exit('Detail transaksi pelunasan belum dapat ditampilkan. '
                . 'Silakan coba kembali.');
        }
    }

    /**
     * P10: Mencatat pelunasan yang diterima langsung oleh admin.
     *
     * Tunai: tidak memerlukan unggahan bukti.
     * Transfer: admin mengunggah bukti yang diterima dari penyewa.
     * Model menghitung nominal sisa kewajiban dan menyimpan pembayaran
     * dengan status valid dalam satu transaksi database.
     */
    public function prosesPelunasan(string $kodeSewa = ''): void
    {
        require_admin_login();
        $this->wajibMetode('POST');
        $kodeSewa = trim(str_replace('~', '/', $kodeSewa));
        $metodeBayar = trim((string) ($_POST['mtode_byr'] ?? ''));
        if ($kodeSewa === '') {
            http_response_code(400);
            exit('Kode transaksi sewa wajib tersedia.');
        }
        if (!in_array($metodeBayar, ['tunai', 'transfer'], true)) {
            $this->redirectDenganPesan(
                'admin/pembayaran/pelunasan/' . str_replace('/', '~', $kodeSewa),
                'error',
                'Metode pembayaran harus tunai atau transfer.'
            );
        }
        $namaFileBukti = null;
        if ($metodeBayar === 'transfer') {
            $hasilUpload = $this->unggahBukti('ft_byr');
            if (!$hasilUpload['success']) {
                $this->redirectDenganPesan(
                    'admin/pembayaran/pelunasan/' . str_replace('/', '~', $kodeSewa),
                    'error',
                    $hasilUpload['error']
                );
            }
            $namaFileBukti = (string) $hasilUpload['filename'];
        }
        try {
            $kodeBayar = $this->model->simpanPelunasan(
                $kodeSewa,
                $metodeBayar,
                $namaFileBukti
            );
            $this->redirectDenganPesan(
                'admin/pembayaran/pelunasan/' . str_replace('/', '~', $kodeSewa),
                'success',
                'Pelunasan berhasil dicatat dengan kode pembayaran ' . $kodeBayar
                    . '. Status pembayaran: valid; status transaksi: lunas.'
            );
        } catch (InvalidArgumentException $e) {
            if ($namaFileBukti !== null) {
                $this->hapusBerkas($namaFileBukti);
            }
            $this->redirectDenganPesan(
                'admin/pembayaran/pelunasan/' . str_replace('/', '~', $kodeSewa),
                'error',
                $e->getMessage()
            );
        } catch (Throwable $e) {
            error_log(
                'Proses pelunasan gagal: ' . $e->getMessage()
            );
            if ($namaFileBukti !== null) {
                $this->hapusBerkas($namaFileBukti);
            }
            $this->redirectDenganPesan(
                'admin/pembayaran/pelunasan/' . str_replace('/', '~', $kodeSewa),
                'error',
                'Pelunasan belum berhasil dicatat. Pastikan transaksi masih memiliki '
                    . 'sisa kewajiban dan belum pernah dilunasi.'
            );
        }
    }
    /**
* P10: Menampilkan dokumen transaksi yang siap dicetak.
* Data disusun Model dari t_sewa, t_penyewa, t_pilih, t_mobil,
* dan t_bayar. Method ini hanya menyajikan data.
*/
public function dokumenTransaksi(string $kodeSewa = ''): void
{
require_admin_login();
$this->wajibMetode('GET');
$kodeSewa = trim(str_replace('~', '/', $kodeSewa));
if ($kodeSewa === '') {
http_response_code(400);
exit('Kode transaksi sewa wajib tersedia.');
}
try {
$detailPelunasan = $this->model
->getDetailPelunasan($kodeSewa);
if ($detailPelunasan === null) {
http_response_code(404);
exit('Data transaksi tidak ditemukan.');
}
$this->view('admin/dokumen_transaksi', [
'detailPelunasan' => $detailPelunasan
]);
} catch (Throwable $e) {
error_log(
'Dokumen transaksi gagal ditampilkan: ' . $e->getMessage()
);
http_response_code(500);
exit('Dokumen transaksi belum dapat ditampilkan. '
. 'Silakan coba kembali.');
}
}
}

<?php
// P9 BARU - application/controllers/Verifikasi_controller.php
// Sisi admin: melihat pembayaran DP dan memverifikasinya (valid / invalid).
class Verifikasi_controller extends Controller
{
    private Pembayaran_model $model;

    public function __construct()
    {
        require_once APPPATH . 'models/Pembayaran_model.php';
        require_once APPPATH . 'helpers/auth_helper.php';
        require_once APPPATH . 'helpers/flash_helper.php';
        require_once APPPATH . 'helpers/csrf_helper.php';
        $this->model = new Pembayaran_model();
    }

    // GET admin/verifikasi[?status=pending|valid|invalid]
    public function index(): void
    {
        require_admin_login();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            header('Allow: GET');
            exit('Method Not Allowed');
        }

        $filter = (string) ($_GET['status'] ?? '');
        $filter = in_array($filter, ['pending', 'valid', 'invalid'], true) ? $filter : null;

        try {
            $daftar = $this->model->getDaftarDpAdmin($filter);
        } catch (Throwable $e) {
            error_log('Daftar verifikasi pembayaran gagal: ' . $e->getMessage());
            http_response_code(500);
            exit('Daftar pembayaran belum dapat ditampilkan. Silakan coba kembali.');
        }

        $this->view('admin/verifikasi_view', [
            'username' => $_SESSION['admin_username'],
            'daftar' => $daftar,
            'filter' => $filter,
            'flash' => get_flash_message(),
            'csrf' => csrf_token('verifikasi')
        ]);
    }

    // POST admin/verifikasi/proses (PRG)
    public function proses(): void
    {
        require_admin_login();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Method Not Allowed');
        }

        if (!csrf_valid('verifikasi', $_POST['csrf_token'] ?? null)) {
            set_flash_message('danger', 'Formulir tidak valid. Muat ulang halaman lalu coba kembali.');
            $this->kembali();
        }

        $kodeBayar = trim((string) ($_POST['kode_bayar'] ?? ''));
        $keputusan = (string) ($_POST['keputusan'] ?? '');

        if ($kodeBayar === '' || strlen($kodeBayar) > 15) {
            set_flash_message('danger', 'Kode pembayaran tidak valid.');
            $this->kembali();
        }
        if (!in_array($keputusan, ['valid', 'invalid'], true)) {
            set_flash_message('danger', 'Keputusan verifikasi tidak valid.');
            $this->kembali();
        }

        try {
            $this->model->verifikasiDp($kodeBayar, $keputusan);
            set_flash_message(
                'success',
                $keputusan === 'valid'
                    ? 'Pembayaran ' . $kodeBayar . ' dinyatakan valid dan transaksi disetujui.'
                    : 'Pembayaran ' . $kodeBayar . ' dinyatakan invalid. Penyewa dapat mengunggah ulang bukti.'
            );
        } catch (InvalidArgumentException $e) {
            set_flash_message('danger', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Verifikasi pembayaran gagal: ' . $e->getMessage());
            set_flash_message('danger', 'Verifikasi belum berhasil diproses. Silakan coba kembali.');
        }

        $this->kembali();
    }

    private function kembali(): never
    {
        header('Location: ' . site_url('admin/verifikasi'));
        exit;
    }
}

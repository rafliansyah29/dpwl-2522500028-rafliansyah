<?php
// P8: Manajemen Penyewa (CRUD oleh admin) - dengan foto KTP & SIM A
class Npenyewa_controller extends Controller
{
  private Penyewa_model $model;

  public function __construct()
  {
    require_once APPPATH . 'models/Penyewa_model.php';
    require_once APPPATH . 'helpers/auth_helper.php';
    require_once APPPATH . 'helpers/flash_helper.php';
    $this->model = new Penyewa_model();
  }

  // ---------- Read ----------
  public function index(): void
  {
    require_admin_login();
    $this->render(null, null, null, get_flash_message());
  }

  // ---------- Create ----------
  public function tambah(): void
  {
    require_admin_login();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      $this->render('tambah', []);
      return;
    }
    $this->wajibPost();

    $in = $this->readInput();
    $password = $_POST['sandi'] ?? '';

    $error = $this->validateInput($in, $password, false);
    if ($error === null && $this->model->findByUserPlg($in['user_plg']) !== null) {
      $error = 'Username sudah digunakan.';
    }
    if ($error === null && $this->model->nikDipakai($in['nik'])) {
      $error = 'NIK sudah terdaftar pada penyewa lain.';
    }
    if ($error !== null) {
      $this->render('tambah', $in, $error);
      return;
    }

    // Unggah foto (opsional). Bila salah satu gagal, batalkan semuanya.
    $ktp = $this->uploadFoto('ft_ktp');
    $sim = $ktp['success'] ? $this->uploadFoto('ft_sim_a') : ['success' => true, 'filename' => null, 'error' => null];
    if (!$ktp['success'] || !$sim['success']) {
      $this->hapusFoto([$ktp['filename'], $sim['filename']]);
      $this->render('tambah', $in, $ktp['error'] ?? $sim['error']);
      return;
    }

    try {
      $ok = $this->model->createByAdmin([
        'user_plg'   => $in['user_plg'],
        'sandi'      => password_hash($password, PASSWORD_DEFAULT),
        'nik'        => $in['nik'],
        'nm_lngkp'   => $in['nm_lngkp'],
        'no_hp'      => $in['no_hp'],
        'almt_lngkp' => $in['almt_lngkp'],
      ], $ktp['filename'], $sim['filename']);
    } catch (Throwable $e) {
      error_log('Tambah penyewa gagal: ' . $e->getMessage());
      $ok = false;
    }
    if (!$ok) {
      $this->hapusFoto([$ktp['filename'], $sim['filename']]);
      $this->render('tambah', $in, 'Data penyewa gagal disimpan.');
      return;
    }

    $this->selesai('success', 'Data penyewa berhasil ditambahkan.');
  }

  // ---------- Update ----------
  public function ubah(string $userPlg): void
  {
    require_admin_login();

    $penyewa = $this->model->getProfil($userPlg);
    if ($penyewa === null) {
      $this->selesai('danger', 'Data penyewa tidak ditemukan.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      $this->render('ubah', $penyewa);
      return;
    }
    $this->wajibPost();

    $in = $this->readInput();
    $in['user_plg'] = $userPlg; // kunci dari route, bukan dari form
    $in['ft_ktp'] = $penyewa['ft_ktp'];
    $in['ft_sim_a'] = $penyewa['ft_sim_a'];
    $password = $_POST['sandi'] ?? '';

    $error = $this->validateInput($in, $password, true);
    if ($error === null && $this->model->nikDipakai($in['nik'], $userPlg)) {
      $error = 'NIK sudah terdaftar pada penyewa lain.';
    }
    if ($error !== null) {
      $this->render('ubah', $in, $error);
      return;
    }

    $ktp = $this->uploadFoto('ft_ktp');
    $sim = $ktp['success'] ? $this->uploadFoto('ft_sim_a') : ['success' => true, 'filename' => null, 'error' => null];
    if (!$ktp['success'] || !$sim['success']) {
      $this->hapusFoto([$ktp['filename'], $sim['filename']]);
      $this->render('ubah', $in, $ktp['error'] ?? $sim['error']);
      return;
    }

    // Foto baru menggantikan foto lama; bila tidak ada unggahan, foto lama dipertahankan.
    $ktpAkhir = $ktp['filename'] ?? $penyewa['ft_ktp'];
    $simAkhir = $sim['filename'] ?? $penyewa['ft_sim_a'];
    $hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

    try {
      $ok = $this->model->updateByAdmin(
        $userPlg, $in['nik'], $in['nm_lngkp'], $in['no_hp'], $in['almt_lngkp'],
        $ktpAkhir, $simAkhir, $hash
      );
    } catch (Throwable $e) {
      error_log('Ubah penyewa gagal: ' . $e->getMessage());
      $ok = false;
    }
    if (!$ok) {
      $this->hapusFoto([$ktp['filename'], $sim['filename']]);
      $this->render('ubah', $in, 'Data penyewa gagal diperbarui.');
      return;
    }

    // Hapus file lama hanya setelah database berhasil diperbarui.
    if ($ktp['filename'] !== null) $this->hapusFoto([$penyewa['ft_ktp']]);
    if ($sim['filename'] !== null) $this->hapusFoto([$penyewa['ft_sim_a']]);

    $this->selesai('success', 'Data penyewa berhasil diperbarui.');
  }

  // ---------- Delete ----------
  public function hapus(string $userPlg): void
  {
    require_admin_login();
    $this->wajibPost();

    $penyewa = $this->model->getProfil($userPlg);
    if ($penyewa === null) {
      $this->selesai('danger', 'Data penyewa tidak ditemukan.');
    }

    try {
      $ok = $this->model->delete($userPlg);
    } catch (Throwable $e) {
      // Biasanya ditolak foreign key: penyewa masih punya data di t_sewa.
      error_log('Hapus penyewa gagal: ' . $e->getMessage());
      $ok = false;
    }
    if (!$ok) {
      $this->selesai('danger', 'Penyewa tidak dapat dihapus. Kemungkinan masih memiliki riwayat transaksi sewa.');
    }

    $this->hapusFoto([$penyewa['ft_ktp'] ?? null, $penyewa['ft_sim_a'] ?? null]);
    $this->selesai('success', 'Data penyewa berhasil dihapus.');
  }

  // ---------- helper privat ----------

  private function wajibPost(): void
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      http_response_code(405);
      exit('Metode request tidak diizinkan.');
    }
  }

  private function selesai(string $type, string $message): void
  {
    set_flash_message($type, $message);
    header('Location: ' . site_url('admin/npenyewa'));
    exit;
  }

  private function readInput(): array
  {
    return [
      'user_plg'   => trim($_POST['user_plg'] ?? ''),
      'nik'        => trim($_POST['nik'] ?? ''),
      'nm_lngkp'   => trim($_POST['nm_lngkp'] ?? ''),
      'no_hp'      => trim($_POST['no_hp'] ?? ''),
      'almt_lngkp' => trim($_POST['almt_lngkp'] ?? ''),
    ];
  }

  private function render(?string $mode, ?array $dipilih = null, ?string $error = null, ?array $flash = null): void
  {
    $this->view('admin/npenyewa_view', [
      'username'        => $_SESSION['admin_username'],
      'penyewa'         => $this->model->getAll(),
      'flash'           => $flash,
      'mode'            => $mode,
      'penyewa_dipilih' => $dipilih,
      'error'           => $error,
    ]);
  }

  // Aturan sama dengan Auth::register agar data konsisten.
  private function validateInput(array $in, string $password, bool $isEdit): ?string
  {
    if (!$isEdit && !preg_match('/\A[A-Za-z0-9._-]{1,15}\z/', $in['user_plg'])) {
      return 'Username harus 1–15 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.';
    }
    if (!$isEdit && strlen($password) < 8) {
      return 'Password minimal 8 karakter.';
    }
    if ($password !== '' && (strlen($password) < 8 || strlen($password) > 72)) {
      return 'Password harus 8–72 karakter' . ($isEdit ? ' (kosongkan jika tidak diganti).' : '.');
    }
    if (!preg_match('/\A[0-9]{16}\z/', $in['nik'])) {
      return 'NIK harus terdiri dari 16 digit angka.';
    }
    if ($in['nm_lngkp'] === '' || mb_strlen($in['nm_lngkp']) > 100) {
      return 'Nama lengkap wajib diisi, maksimal 100 karakter.';
    }
    if ($in['no_hp'] === '' || strlen($in['no_hp']) > 15 || !preg_match('/\A[0-9+(). -]+\z/', $in['no_hp'])) {
      return 'Nomor HP wajib diisi dan formatnya tidak valid.';
    }
    if ($in['almt_lngkp'] === '' || mb_strlen($in['almt_lngkp']) > 300) {
      return 'Alamat wajib diisi, maksimal 300 karakter.';
    }
    return null;
  }

  /**
   * Simpan satu foto ke uploads/penyewa. File kosong dianggap tidak diunggah.
   * Hanya JPEG/PNG/WEBP valid, maksimal 5 MB. Pola nama sama dengan Penyewa_controller.
   */
  private function uploadFoto(string $field): array
  {
    $gagal = fn(string $msg) => ['success' => false, 'filename' => null, 'error' => $msg];

    if (!in_array($field, ['ft_ktp', 'ft_sim_a'], true)) {
      return $gagal('Field foto tidak valid.');
    }
    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      return ['success' => true, 'filename' => null, 'error' => null];
    }

    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      return $gagal('Unggah foto gagal. Periksa ukuran file dan konfigurasi PHP.');
    }
    if (
      !isset($file['tmp_name'], $file['size']) || !is_uploaded_file($file['tmp_name'])
      || (int) $file['size'] < 1 || (int) $file['size'] > 5 * 1024 * 1024
    ) {
      return $gagal('Foto harus berukuran lebih dari 0 byte dan maksimal 5 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
      return $gagal('Format foto harus JPEG, PNG, atau WEBP yang valid.');
    }

    $dir = dirname(APPPATH) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'penyewa' . DIRECTORY_SEPARATOR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
      return $gagal('Folder penyimpanan foto penyewa tidak dapat dibuat.');
    }
    if (!is_writable($dir)) {
      return $gagal('Folder penyimpanan foto penyewa tidak dapat ditulis.');
    }

    try {
      $filename = $field . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    } catch (Throwable $e) {
      return $gagal('Nama file foto gagal dibuat.');
    }
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
      return $gagal('Foto gagal dipindahkan ke folder penyimpanan.');
    }
    return ['success' => true, 'filename' => $filename, 'error' => null];
  }

  // Hapus file foto; hanya nama yang cocok dengan pola buatan uploadFoto().
  private function hapusFoto(array $filenames): void
  {
    $dir = dirname(APPPATH) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'penyewa' . DIRECTORY_SEPARATOR;
    foreach ($filenames as $filename) {
      if (
        is_string($filename)
        && preg_match('/^(ft_ktp|ft_sim_a)_\d{10,14}_[a-f0-9]{12,16}\.(jpg|jpeg|png|webp)$/', $filename)
        && is_file($dir . $filename)
      ) {
        @unlink($dir . $filename);
      }
    }
  }
}

<?php
// P9 BARU - application/views/pembayaran/form_dp.php
require APPPATH . 'views/katalog/header.php';
$e = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
require APPPATH . 'views/katalog/navbar.php';

$formatRupiah = static function ($nilai): string {
    return 'Rp ' . number_format((float) $nilai, 0, ',', '.');
};
$formatWaktu = static function (?string $waktu): string {
    $ts = $waktu === null || $waktu === '' ? false : strtotime($waktu);
    return $ts === false ? '-' : date('d-m-Y H:i', $ts);
};

$dp = is_array($dp ?? null) ? $dp : [];
$detail = is_array($dp['detail'] ?? null) ? $dp['detail'] : [];
$kodeSewa = (string) ($dp['kode_sewa'] ?? '');
$statusDp = (string) ($dp['sttus_byr'] ?? '');
$namaBukti = (string) ($dp['ft_byr'] ?? '');
$bisaBayar = ($dp['status_sewa'] ?? '') === 'diajukan'
    && in_array($statusDp, ['pending', 'invalid'], true);
?>
<section class="bg-success py-4">
    <div class="container">
        <h1 class="h2 text-white mt-3">Pembayaran Uang Muka (DP)</h1>
        <p class="text-white mb-0">Unggah bukti transfer DP agar transaksi dapat diverifikasi admin.</p>
    </div>
</section>
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light"><h2 class="h5 mb-0">Ringkasan Transaksi</h2></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">Kode transaksi</dt>
                            <dd class="col-sm-7"><?= $e($kodeSewa) ?></dd>
                            <dt class="col-sm-5">Kode pembayaran</dt>
                            <dd class="col-sm-7"><?= $e($dp['kode_bayar'] ?? '-') ?></dd>
                            <dt class="col-sm-5">Periode sewa</dt>
                            <dd class="col-sm-7"><?= $e($dp['tgl_mulai'] ?? '-') ?> s.d. <?= $e($dp['tgl_selesai'] ?? '-') ?></dd>
                            <dt class="col-sm-5">Waktu pengambilan</dt>
                            <dd class="col-sm-7"><?= $e(substr((string) ($dp['wkt_ambil'] ?? '-'), 0, 5)) ?> WIB</dd>
                            <dt class="col-sm-5">Status transaksi</dt>
                            <dd class="col-sm-7"><span class="badge bg-secondary"><?= $e($dp['status_sewa'] ?? '-') ?></span></dd>
                        </dl>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle bg-white mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Kendaraan</th>
                                <th scope="col" class="text-end">Harga per Hari</th>
                                <th scope="col" class="text-center">Durasi</th>
                                <th scope="col" class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detail as $item): ?>
                                <tr>
                                    <td><?= $e($item['nm_mobil'] ?? '-') ?> <small class="text-muted">(<?= $e($item['no_plat'] ?? '') ?>)</small></td>
                                    <td class="text-end text-nowrap"><?= $e($formatRupiah($item['hrg_sewa'] ?? 0)) ?></td>
                                    <td class="text-center"><?= $e($item['n_hari'] ?? 0) ?> hari</td>
                                    <td class="text-end text-nowrap"><?= $e($formatRupiah((int) ($item['hrg_sewa'] ?? 0) * (int) ($item['n_hari'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-success shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total sewa</span>
                            <strong class="text-nowrap"><?= $e($formatRupiah($dp['total'] ?? 0)) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Nominal DP yang harus dibayar</span>
                            <strong class="text-nowrap text-success"><?= $e($formatRupiah($dp['nominal'] ?? 0)) ?></strong>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <span class="me-2">Status DP:</span>
                            <?php if ($statusDp === 'valid'): ?>
                                <span class="badge bg-success">Terverifikasi</span>
                                <div class="small text-muted mt-1">Diverifikasi pada <?= $e($formatWaktu($dp['tgl_wkt_ver'] ?? null)) ?></div>
                            <?php elseif ($statusDp === 'invalid'): ?>
                                <span class="badge bg-danger">Bukti ditolak</span>
                                <div class="small text-muted mt-1">Silakan unggah bukti transfer yang benar.</div>
                            <?php elseif ($namaBukti !== ''): ?>
                                <span class="badge bg-info text-dark">Menunggu verifikasi admin</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Menunggu pembayaran</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($namaBukti !== ''): ?>
                            <div class="mb-3">
                                <div class="small text-muted mb-1">Bukti yang tersimpan saat ini</div>
                                <a href="<?= $e(base_url('uploads/bayar/' . rawurlencode($namaBukti))) ?>" target="_blank" rel="noopener">
                                    <img src="<?= $e(base_url('uploads/bayar/' . rawurlencode($namaBukti))) ?>"
                                        alt="Bukti pembayaran" class="img-thumbnail" style="max-height: 160px;">
                                </a>
                                <div class="small text-muted mt-1">Diunggah <?= $e($formatWaktu($dp['tgl_wkt_byr'] ?? null)) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($bisaBayar): ?>
                            <form method="post" enctype="multipart/form-data"
                                action="<?= $e(site_url('pembayaran/dp')) ?>">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrf ?? '') ?>">
                                <input type="hidden" name="kode_sewa" value="<?= $e($kodeSewa) ?>">
                                <div class="mb-3">
                                    <label for="ft_byr" class="form-label">
                                        <?= $namaBukti !== '' ? 'Ganti bukti transfer' : 'Bukti transfer' ?>
                                    </label>
                                    <input type="file" class="form-control" id="ft_byr" name="ft_byr"
                                        accept="image/jpeg,image/png,image/webp" required>
                                    <div class="form-text">Format JPEG, PNG, atau WEBP. Maksimal 5 MB.</div>
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-upload me-1" aria-hidden="true"></i> Kirim Bukti Pembayaran
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-secondary mb-0">
                                Pembayaran DP untuk transaksi ini tidak dapat diubah lagi.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-3">
                    <a class="btn btn-outline-secondary" href="<?= $e(site_url('pembayaran')) ?>">
                        <i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Kembali ke Transaksi Saya
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require APPPATH . 'views/katalog/footer.php'; ?>

<?php
// P9 BARU - application/views/pembayaran/riwayat.php
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
$daftar = is_array($daftar ?? null) ? $daftar : [];

// Label status DP hanya memakai nilai ENUM yang ada di skema: pending | valid | invalid.
$labelDp = static function (array $row): array {
    $status = (string) ($row['sttus_byr'] ?? '');
    $adaBukti = (string) ($row['ft_byr'] ?? '') !== '';
    if ($status === '') {
        return ['-', 'bg-secondary'];
    }
    if ($status === 'valid') {
        return ['Terverifikasi', 'bg-success'];
    }
    if ($status === 'invalid') {
        return ['Bukti ditolak - unggah ulang', 'bg-danger'];
    }
    return $adaBukti
        ? ['Menunggu verifikasi admin', 'bg-info text-dark']
        : ['Menunggu pembayaran', 'bg-warning text-dark'];
};
?>
<section class="bg-success py-4">
    <div class="container">
        <h1 class="h2 text-white mt-3">Transaksi Saya</h1>
        <p class="text-white mb-0">Pantau status transaksi dan pembayaran uang muka (DP) Anda.</p>
    </div>
</section>
<section class="py-5">
    <div class="container">
        <?php if ($daftar === []): ?>
            <div class="alert alert-info">Belum ada transaksi yang diajukan.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle bg-white">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Kode Transaksi</th>
                            <th scope="col">Periode Sewa</th>
                            <th scope="col" class="text-end">Nominal DP</th>
                            <th scope="col">Status Transaksi</th>
                            <th scope="col">Status DP</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftar as $row): ?>
                            <?php
                            [$teksDp, $kelasDp] = $labelDp($row);
                            $statusDp = (string) ($row['sttus_byr'] ?? '');
                            $bisaBayar = ($row['status_sewa'] ?? '') === 'diajukan'
                                && in_array($statusDp, ['pending', 'invalid'], true);
                            $adaBukti = (string) ($row['ft_byr'] ?? '') !== '';
                            $kode = (string) ($row['kode_sewa'] ?? '');
                            ?>
                            <tr>
                                <td class="text-nowrap"><?= $e($kode) ?></td>
                                <td class="text-nowrap">
                                    <?= $e($row['tgl_mulai'] ?? '-') ?> s.d. <?= $e($row['tgl_selesai'] ?? '-') ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?= $row['nominal'] === null ? '-' : $e($formatRupiah($row['nominal'])) ?>
                                </td>
                                <td><span class="badge bg-secondary"><?= $e($row['status_sewa'] ?? '-') ?></span></td>
                                <td>
                                    <span class="badge <?= $e($kelasDp) ?>"><?= $e($teksDp) ?></span>
                                    <?php if ($statusDp === 'valid' && !empty($row['tgl_wkt_ver'])): ?>
                                        <div class="small text-muted"><?= $e($formatWaktu($row['tgl_wkt_ver'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($bisaBayar): ?>
                                        <a class="btn btn-success btn-sm"
                                            href="<?= $e(site_url('pembayaran/dp?kode_sewa=' . rawurlencode($kode))) ?>">
                                            <?= $statusDp === 'invalid' ? 'Unggah Ulang' : ($adaBukti ? 'Ganti Bukti' : 'Bayar DP') ?>
                                        </a>
                                    <?php elseif ($statusDp !== ''): ?>
                                        <a class="btn btn-outline-secondary btn-sm"
                                            href="<?= $e(site_url('pembayaran/dp?kode_sewa=' . rawurlencode($kode))) ?>">
                                            Lihat
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require APPPATH . 'views/katalog/footer.php'; ?>

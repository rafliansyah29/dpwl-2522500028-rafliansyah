<?php
// P9 BARU - application/views/admin/verifikasi_view.php
require APPPATH . '/views/templates/header.php';
require APPPATH . '/views/templates/sidebar.php';

$e = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$formatRupiah = static function ($nilai): string {
    return 'Rp ' . number_format((float) $nilai, 0, ',', '.');
};
$formatWaktu = static function (?string $waktu): string {
    $ts = $waktu === null || $waktu === '' ? false : strtotime($waktu);
    return $ts === false ? '-' : date('d-m-Y H:i', $ts);
};

$daftar = is_array($daftar ?? null) ? $daftar : [];
$filter = $filter ?? null;
$flash = $flash ?? null;
$flashClass = (($flash['type'] ?? '') === 'success') ? 'alert-success' : 'alert-danger';

$labelStatus = [
    'pending' => 'label-warning',
    'valid' => 'label-success',
    'invalid' => 'label-danger'
];
?>
<div class="content-wrapper">
    <section class="content-header">
        <h1>
            Verifikasi Pembayaran
            <small>Pembayaran uang muka (DP) penyewa</small>
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?= $e(site_url('admin')) ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
            <li class="active">Verifikasi Pembayaran</li>
        </ol>
    </section>
    <section class="content">
        <?php if (!empty($flash)): ?>
            <div class="alert <?= $e($flashClass) ?> alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <i class="fa <?= (($flash['type'] ?? '') === 'success') ? 'fa-check' : 'fa-warning' ?>"></i>
                <?= $e($flash['message'] ?? '') ?>
            </div>
        <?php endif; ?>

        <div class="box">
            <div class="box-header">
                <h3 class="box-title">Data Pembayaran DP</h3>
                <div class="box-tools">
                    <div class="btn-group">
                        <a href="<?= $e(site_url('admin/verifikasi')) ?>"
                            class="btn btn-sm <?= $filter === null ? 'btn-primary' : 'btn-default' ?>">Semua</a>
                        <a href="<?= $e(site_url('admin/verifikasi?status=pending')) ?>"
                            class="btn btn-sm <?= $filter === 'pending' ? 'btn-primary' : 'btn-default' ?>">Pending</a>
                        <a href="<?= $e(site_url('admin/verifikasi?status=valid')) ?>"
                            class="btn btn-sm <?= $filter === 'valid' ? 'btn-primary' : 'btn-default' ?>">Valid</a>
                        <a href="<?= $e(site_url('admin/verifikasi?status=invalid')) ?>"
                            class="btn btn-sm <?= $filter === 'invalid' ? 'btn-primary' : 'btn-default' ?>">Invalid</a>
                    </div>
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table id="verifikasiTable" class="table table-bordered table-striped js-data-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode Bayar</th>
                                <th>Kode Sewa</th>
                                <th>Penyewa</th>
                                <th>Periode</th>
                                <th>Nominal DP</th>
                                <th>Waktu Bayar</th>
                                <th class="no-sort">Bukti</th>
                                <th>Status DP</th>
                                <th>Status Sewa</th>
                                <th class="no-sort">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftar as $no => $row): ?>
                                <?php
                                $statusDp = (string) ($row['sttus_byr'] ?? '');
                                $adaBukti = (string) ($row['ft_byr'] ?? '') !== '';
                                $bisaVerifikasi = $statusDp === 'pending'
                                    && $adaBukti
                                    && ($row['status_sewa'] ?? '') === 'diajukan';
                                $urlBukti = $adaBukti
                                    ? base_url('uploads/bayar/' . rawurlencode((string) $row['ft_byr']))
                                    : '';
                                ?>
                                <tr>
                                    <td><?= $no + 1 ?></td>
                                    <td class="text-nowrap"><?= $e($row['kode_bayar'] ?? '') ?></td>
                                    <td class="text-nowrap"><?= $e($row['kode_sewa'] ?? '') ?></td>
                                    <td><?= $e($row['nm_lngkp'] ?? '-') ?><br><small class="text-muted"><?= $e($row['user_plg'] ?? '') ?></small></td>
                                    <td class="text-nowrap"><?= $e($row['tgl_mulai'] ?? '') ?> s.d. <?= $e($row['tgl_selesai'] ?? '') ?></td>
                                    <td class="text-right text-nowrap"><?= $e($formatRupiah($row['nominal'] ?? 0)) ?></td>
                                    <td class="text-nowrap"><?= $e($formatWaktu($row['tgl_wkt_byr'] ?? null)) ?></td>
                                    <td>
                                        <?php if ($adaBukti): ?>
                                            <a href="<?= $e($urlBukti) ?>" target="_blank" rel="noopener">
                                                <img src="<?= $e($urlBukti) ?>" alt="Bukti pembayaran"
                                                    style="max-height: 60px; max-width: 90px;">
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Belum ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="label <?= $e($labelStatus[$statusDp] ?? 'label-default') ?>"><?= $e($statusDp) ?></span>
                                        <?php if ($statusDp !== 'pending' && !empty($row['tgl_wkt_ver'])): ?>
                                            <br><small class="text-muted"><?= $e($formatWaktu($row['tgl_wkt_ver'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="label label-default"><?= $e($row['status_sewa'] ?? '') ?></span></td>
                                    <td class="text-nowrap">
                                        <a href="<?= $e(site_url('admin/verifikasi/detail?kode_bayar=' . rawurlencode((string) ($row['kode_bayar'] ?? '')))) ?>"
                                            class="btn btn-info btn-xs">
                                            <i class="fa fa-eye"></i> Detail
                                        </a>
                                        <?php if ($bisaVerifikasi): ?>
                                            <form method="post" style="display:inline"
                                                action="<?= $e(site_url('admin/verifikasi/proses')) ?>"
                                                onsubmit="return confirm('Nyatakan pembayaran ini VALID dan setujui transaksi?');">
                                                <input type="hidden" name="csrf_token" value="<?= $e($csrf ?? '') ?>">
                                                <input type="hidden" name="kode_bayar" value="<?= $e($row['kode_bayar'] ?? '') ?>">
                                                <input type="hidden" name="keputusan" value="valid">
                                                <button type="submit" class="btn btn-success btn-xs">
                                                    <i class="fa fa-check"></i> Valid
                                                </button>
                                            </form>
                                            <form method="post" style="display:inline"
                                                action="<?= $e(site_url('admin/verifikasi/proses')) ?>"
                                                onsubmit="return confirm('Nyatakan pembayaran ini INVALID? Penyewa akan diminta mengunggah ulang bukti.');">
                                                <input type="hidden" name="csrf_token" value="<?= $e($csrf ?? '') ?>">
                                                <input type="hidden" name="kode_bayar" value="<?= $e($row['kode_bayar'] ?? '') ?>">
                                                <input type="hidden" name="keputusan" value="invalid">
                                                <button type="submit" class="btn btn-danger btn-xs">
                                                    <i class="fa fa-times"></i> Invalid
                                                </button>
                                            </form>
                                        <?php elseif ($statusDp === 'pending' && !$adaBukti): ?>
                                            <span class="text-muted">Menunggu bukti</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
<?php require APPPATH . '/views/templates/footer.php'; ?>

<?php
// P9 BARU - application/views/admin/verifikasi_detail_view.php
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

$dp = is_array($dp ?? null) ? $dp : [];
$rincian = is_array($dp['detail'] ?? null) ? $dp['detail'] : [];
$flash = $flash ?? null;
$flashClass = (($flash['type'] ?? '') === 'success') ? 'alert-success' : 'alert-danger';

$kodeBayar = (string) ($dp['kode_bayar'] ?? '');
$statusDp = (string) ($dp['sttus_byr'] ?? '');
$namaBukti = (string) ($dp['ft_byr'] ?? '');
$adaBukti = $namaBukti !== '';
$urlBukti = $adaBukti ? base_url('uploads/bayar/' . rawurlencode($namaBukti)) : '';
$bisaVerifikasi = $statusDp === 'pending'
    && $adaBukti
    && ($dp['status_sewa'] ?? '') === 'diajukan';

$labelStatus = [
    'pending' => 'label-warning',
    'valid' => 'label-success',
    'invalid' => 'label-danger'
];
?>
<div class="content-wrapper">
    <section class="content-header">
        <h1>
            Detail Pembayaran
            <small><?= $e($kodeBayar) ?></small>
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?= $e(site_url('admin')) ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
            <li><a href="<?= $e(site_url('admin/verifikasi')) ?>">Verifikasi Pembayaran</a></li>
            <li class="active">Detail</li>
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

        <div class="row">
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Data Pembayaran</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-condensed">
                            <tbody>
                                <tr><th style="width: 40%;">Kode pembayaran</th><td><?= $e($kodeBayar) ?></td></tr>
                                <tr><th>Jenis</th><td>DP (uang muka)</td></tr>
                                <tr><th>Metode</th><td><?= $e($dp['mtode_byr'] ?? '-') ?></td></tr>
                                <tr><th>Nominal DP</th><td><strong><?= $e($formatRupiah($dp['nominal'] ?? 0)) ?></strong></td></tr>
                                <tr><th>Waktu bayar</th><td><?= $e($formatWaktu($dp['tgl_wkt_byr'] ?? null)) ?></td></tr>
                                <tr>
                                    <th>Status DP</th>
                                    <td><span class="label <?= $e($labelStatus[$statusDp] ?? 'label-default') ?>"><?= $e($statusDp) ?></span></td>
                                </tr>
                                <tr><th>Waktu verifikasi</th><td><?= $e($formatWaktu($dp['tgl_wkt_ver'] ?? null)) ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Data Penyewa</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-condensed">
                            <tbody>
                                <tr><th style="width: 40%;">Username</th><td><?= $e($dp['user_plg'] ?? '-') ?></td></tr>
                                <tr><th>Nama lengkap</th><td><?= $e($dp['nm_lngkp'] ?? '-') ?></td></tr>
                                <tr><th>NIK</th><td><?= $e($dp['nik'] ?? '-') ?></td></tr>
                                <tr><th>No. HP</th><td><?= $e($dp['no_hp'] ?? '-') ?></td></tr>
                                <tr><th>Alamat</th><td><?= $e($dp['almt_lngkp'] ?? '-') ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Bukti Pembayaran</h3>
                    </div>
                    <div class="box-body text-center">
                        <?php if ($adaBukti): ?>
                            <a href="<?= $e($urlBukti) ?>" target="_blank" rel="noopener">
                                <img src="<?= $e($urlBukti) ?>" alt="Bukti pembayaran"
                                    class="img-responsive img-thumbnail" style="max-height: 420px; margin: 0 auto;">
                            </a>
                            <p class="text-muted" style="margin-top: 8px;">
                                <small>Klik gambar untuk membuka ukuran penuh.</small>
                            </p>
                        <?php else: ?>
                            <p class="text-muted">Penyewa belum mengunggah bukti pembayaran.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Data Transaksi</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-condensed">
                            <tbody>
                                <tr><th style="width: 40%;">Kode sewa</th><td><?= $e($dp['kode_sewa'] ?? '-') ?></td></tr>
                                <tr><th>Periode sewa</th><td><?= $e($dp['tgl_mulai'] ?? '-') ?> s.d. <?= $e($dp['tgl_selesai'] ?? '-') ?></td></tr>
                                <tr><th>Waktu pengambilan</th><td><?= $e(substr((string) ($dp['wkt_ambil'] ?? '-'), 0, 5)) ?></td></tr>
                                <tr><th>Status sewa</th><td><span class="label label-default"><?= $e($dp['status_sewa'] ?? '-') ?></span></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Rincian Kendaraan</h3>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Kendaraan</th>
                                <th>No. Plat</th>
                                <th class="text-right">Harga/Hari</th>
                                <th class="text-center">Durasi</th>
                                <th class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rincian as $item): ?>
                                <tr>
                                    <td><?= $e($item['nm_mobil'] ?? '-') ?></td>
                                    <td class="text-nowrap"><?= $e($item['no_plat'] ?? '-') ?></td>
                                    <td class="text-right text-nowrap"><?= $e($formatRupiah($item['hrg_sewa'] ?? 0)) ?></td>
                                    <td class="text-center"><?= $e($item['n_hari'] ?? 0) ?> hari</td>
                                    <td class="text-right text-nowrap"><?= $e($formatRupiah((int) ($item['hrg_sewa'] ?? 0) * (int) ($item['n_hari'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-right">Total sewa</th>
                                <th class="text-right text-nowrap"><?= $e($formatRupiah($dp['total'] ?? 0)) ?></th>
                            </tr>
                            <tr>
                                <th colspan="4" class="text-right">Nominal DP</th>
                                <th class="text-right text-nowrap"><?= $e($formatRupiah($dp['nominal'] ?? 0)) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="box-footer">
                <a href="<?= $e(site_url('admin/verifikasi')) ?>" class="btn btn-default">
                    <i class="fa fa-arrow-left"></i> Kembali
                </a>
                <?php if ($bisaVerifikasi): ?>
                    <span class="pull-right">
                        <form method="post" style="display:inline"
                            action="<?= $e(site_url('admin/verifikasi/proses')) ?>"
                            onsubmit="return confirm('Nyatakan pembayaran ini INVALID? Penyewa akan diminta mengunggah ulang bukti.');">
                            <input type="hidden" name="csrf_token" value="<?= $e($csrf ?? '') ?>">
                            <input type="hidden" name="kode_bayar" value="<?= $e($kodeBayar) ?>">
                            <input type="hidden" name="keputusan" value="invalid">
                            <input type="hidden" name="asal" value="detail">
                            <button type="submit" class="btn btn-danger">
                                <i class="fa fa-times"></i> Invalid
                            </button>
                        </form>
                        <form method="post" style="display:inline"
                            action="<?= $e(site_url('admin/verifikasi/proses')) ?>"
                            onsubmit="return confirm('Nyatakan pembayaran ini VALID dan setujui transaksi?');">
                            <input type="hidden" name="csrf_token" value="<?= $e($csrf ?? '') ?>">
                            <input type="hidden" name="kode_bayar" value="<?= $e($kodeBayar) ?>">
                            <input type="hidden" name="keputusan" value="valid">
                            <input type="hidden" name="asal" value="detail">
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-check"></i> Valid
                            </button>
                        </form>
                    </span>
                <?php elseif ($statusDp === 'pending' && !$adaBukti): ?>
                    <span class="pull-right text-muted">Menunggu penyewa mengunggah bukti.</span>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
<?php require APPPATH . '/views/templates/footer.php'; ?>

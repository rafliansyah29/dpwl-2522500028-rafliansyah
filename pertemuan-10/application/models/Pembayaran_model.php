<?php
// P9 & P10 - application/models/Pembayaran_model.php
// Mengelola pembayaran DP (P9) dan pelunasan transaksi (P10).
class Pembayaran_model
{
    private mysqli $db;

    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    private function siapkan(
        string $sql,
        string $tipe,
        array $param,
        string $konteks
    ): mysqli_stmt {
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException($konteks . ' gagal disiapkan.');
        }
        if ($tipe !== '') {
            $stmt->bind_param($tipe, ...$param);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException($konteks . ' gagal dijalankan.');
        }
        return $stmt;
    }

    // ---------------------------------------------------------------
    // Sisi penyewa (P9)
    // ---------------------------------------------------------------

    // Daftar transaksi penyewa (selain keranjang) beserta status DP-nya.
    public function getRiwayatDpPenyewa(string $userPlg): array
    {
        $stmt = $this->siapkan(
            "SELECT
                s.kode_sewa, s.tgl_mulai, s.tgl_selesai, s.wkt_ambil,
                s.status_sewa,
                b.kode_bayar, b.nominal, b.sttus_byr, b.mtode_byr,
                b.ft_byr, b.tgl_wkt_byr, b.tgl_wkt_ver
            FROM t_sewa s
            LEFT JOIN t_bayar b
                ON b.kode_sewa = s.kode_sewa AND b.jns_byr = 'dp'
            WHERE s.user_plg = ?
            AND s.status_sewa <> 'keranjang'
            ORDER BY s.kode_sewa DESC",
            's',
            [$userPlg],
            'Pengambilan riwayat transaksi'
        );
        $hasil = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $hasil;
    }

    // Satu transaksi + record DP miliknya, hanya jika milik penyewa tersebut.
    public function getDpPenyewa(string $kodeSewa, string $userPlg): ?array
    {
        $stmt = $this->siapkan(
            "SELECT
                s.kode_sewa, s.tgl_mulai, s.tgl_selesai, s.wkt_ambil,
                s.status_sewa,
                b.kode_bayar, b.nominal, b.sttus_byr, b.mtode_byr,
                b.ft_byr, b.tgl_wkt_byr, b.tgl_wkt_ver
            FROM t_sewa s
            INNER JOIN t_bayar b
                ON b.kode_sewa = s.kode_sewa AND b.jns_byr = 'dp'
            WHERE s.kode_sewa = ?
            AND s.user_plg = ?
            ORDER BY b.kode_bayar ASC
            LIMIT 1",
            'ss',
            [$kodeSewa, $userPlg],
            'Pengambilan pembayaran DP'
        );
        $baris = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($baris === null) {
            return null;
        }

        $stmt = $this->siapkan(
            "SELECT p.no_plat, p.hrg_sewa, p.n_hari, m.nm_mobil
            FROM t_pilih p
            INNER JOIN t_mobil m ON m.no_plat = p.no_plat
            WHERE p.kode_sewa = ?
            ORDER BY m.nm_mobil ASC",
            's',
            [$kodeSewa],
            'Pengambilan rincian kendaraan'
        );
        $detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $total = 0;
        foreach ($detail as $item) {
            $total += (int) $item['hrg_sewa'] * (int) $item['n_hari'];
        }
        $baris['detail'] = $detail;
        $baris['total'] = $total;
        return $baris;
    }

    /**
     * Menyimpan bukti transfer DP dengan meng-UPDATE record DP yang dibuat saat checkout.
     * Boleh dipanggil ulang selama DP belum 'valid' (mengganti bukti, atau mengunggah
     * ulang setelah 'invalid'). Mengembalikan nama file bukti lama (atau null)
     * agar Controller dapat menghapusnya setelah commit.
     *
     * @throws InvalidArgumentException untuk pelanggaran aturan bisnis (aman ditampilkan).
     */
    public function simpanBuktiDp(
        string $kodeSewa,
        string $userPlg,
        string $namaFile
    ): ?string {
        $this->db->begin_transaction();
        try {
            $stmt = $this->siapkan(
                "SELECT b.kode_bayar, b.ft_byr, b.sttus_byr, s.status_sewa
                FROM t_bayar b
                INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
                WHERE b.kode_sewa = ?
                AND b.jns_byr = 'dp'
                AND s.user_plg = ?
                ORDER BY b.kode_bayar ASC
                LIMIT 1
                FOR UPDATE",
                'ss',
                [$kodeSewa, $userPlg],
                'Penguncian pembayaran DP'
            );
            $baris = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($baris === null) {
                throw new InvalidArgumentException(
                    'Pembayaran DP untuk transaksi ini tidak ditemukan.'
                );
            }
            if ($baris['status_sewa'] !== 'diajukan') {
                throw new InvalidArgumentException(
                    'Transaksi tidak berstatus diajukan, sehingga pembayaran DP tidak dapat diubah.'
                );
            }
            if ($baris['sttus_byr'] === 'valid') {
                throw new InvalidArgumentException(
                    'Pembayaran DP sudah diverifikasi valid dan tidak dapat diubah.'
                );
            }

            $kodeBayar = (string) $baris['kode_bayar'];
            $stmt = $this->siapkan(
                "UPDATE t_bayar
                SET ft_byr = ?,
                    mtode_byr = 'transfer',
                    tgl_wkt_byr = NOW(),
                    sttus_byr = 'pending',
                    tgl_wkt_ver = NULL
                WHERE kode_bayar = ?
                AND jns_byr = 'dp'
                AND sttus_byr IN ('pending', 'invalid')",
                'ss',
                [$namaFile, $kodeBayar],
                'Penyimpanan bukti pembayaran DP'
            );
            $berubah = $stmt->affected_rows;
            $stmt->close();
            if ($berubah !== 1) {
                throw new RuntimeException(
                    'Record pembayaran DP tidak berubah; penyimpanan dibatalkan.'
                );
            }

            $this->db->commit();

            $lama = (string) ($baris['ft_byr'] ?? '');
            return $lama === '' ? null : $lama;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // ---------------------------------------------------------------
    // Sisi admin - Verifikasi DP (P9)
    // ---------------------------------------------------------------

    // Daftar DP untuk admin. $status null = semua; selain itu: pending | valid | invalid.
    public function getDaftarDpAdmin(?string $status): array
    {
        $sql = "SELECT
                b.kode_bayar, b.kode_sewa, b.tgl_wkt_byr, b.ft_byr,
                b.mtode_byr, b.nominal, b.sttus_byr, b.tgl_wkt_ver,
                s.status_sewa, s.user_plg, s.tgl_mulai, s.tgl_selesai,
                p.nm_lngkp
            FROM t_bayar b
            INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
            LEFT JOIN t_penyewa p ON p.user_plg = s.user_plg
            WHERE b.jns_byr = 'dp'";

        if ($status !== null) {
            if (!in_array($status, ['pending', 'valid', 'invalid'], true)) {
                throw new InvalidArgumentException('Filter status tidak valid.');
            }
            $sql .= " AND b.sttus_byr = ?";
            $tipe = 's';
            $param = [$status];
        } else {
            $tipe = '';
            $param = [];
        }

        $sql .= " ORDER BY (b.sttus_byr = 'pending') DESC, b.tgl_wkt_byr DESC";

        $stmt = $this->siapkan($sql, $tipe, $param, 'Pengambilan daftar pembayaran DP');
        $hasil = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $hasil;
    }

    // Nama berkas bukti pada record DP (null jika record tidak ada atau belum ada bukti).
    public function getBuktiDpAdmin(string $kodeBayar): ?string
    {
        $stmt = $this->siapkan(
            "SELECT ft_byr
            FROM t_bayar
            WHERE kode_bayar = ?
            AND jns_byr = 'dp'
            LIMIT 1",
            's',
            [$kodeBayar],
            'Pengambilan bukti pembayaran DP'
        );
        $baris = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $nama = (string) ($baris['ft_byr'] ?? '');
        return $nama === '' ? null : $nama;
    }

    /**
     * Verifikasi DP oleh admin.
     * 'valid'   : sttus_byr = 'valid', tgl_wkt_ver = NOW(), dan t_sewa.status_sewa menjadi 'disetujui'.
     * 'invalid' : sttus_byr = 'invalid', tgl_wkt_ver = NOW(); t_sewa tetap 'diajukan'
     *             sehingga penyewa dapat mengunggah ulang bukti pada record DP yang sama.
     *
     * @throws InvalidArgumentException untuk pelanggaran aturan bisnis (aman ditampilkan).
     */
    public function verifikasiDp(string $kodeBayar, string $keputusan): void
    {
        if (!in_array($keputusan, ['valid', 'invalid'], true)) {
            throw new InvalidArgumentException('Keputusan verifikasi tidak valid.');
        }

        $this->db->begin_transaction();
        try {
            $stmt = $this->siapkan(
                "SELECT b.kode_bayar, b.kode_sewa, b.ft_byr, b.jns_byr,
                        b.sttus_byr, s.status_sewa
                FROM t_bayar b
                INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
                WHERE b.kode_bayar = ?
                LIMIT 1
                FOR UPDATE",
                's',
                [$kodeBayar],
                'Penguncian pembayaran untuk verifikasi'
            );
            $baris = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($baris === null || $baris['jns_byr'] !== 'dp') {
                throw new InvalidArgumentException('Pembayaran DP tidak ditemukan.');
            }
            if ($baris['sttus_byr'] !== 'pending') {
                throw new InvalidArgumentException(
                    'Pembayaran ini sudah diverifikasi atau tidak lagi berstatus pending.'
                );
            }
            if ((string) ($baris['ft_byr'] ?? '') === '') {
                throw new InvalidArgumentException(
                    'Penyewa belum mengunggah bukti pembayaran, sehingga belum dapat diverifikasi.'
                );
            }
            if ($baris['status_sewa'] !== 'diajukan') {
                throw new InvalidArgumentException(
                    'Transaksi tidak berstatus diajukan, sehingga verifikasi tidak dapat dilakukan.'
                );
            }

            $stmt = $this->siapkan(
                "UPDATE t_bayar
                SET sttus_byr = ?, tgl_wkt_ver = NOW()
                WHERE kode_bayar = ?
                AND jns_byr = 'dp'
                AND sttus_byr = 'pending'",
                'ss',
                [$keputusan, $kodeBayar],
                'Pembaruan status pembayaran'
            );
            $berubah = $stmt->affected_rows;
            $stmt->close();
            if ($berubah !== 1) {
                throw new RuntimeException('Status pembayaran tidak berubah; verifikasi dibatalkan.');
            }

            if ($keputusan === 'valid') {
                $kodeSewa = (string) $baris['kode_sewa'];
                $stmt = $this->siapkan(
                    "UPDATE t_sewa
                    SET status_sewa = 'disetujui'
                    WHERE kode_sewa = ?
                    AND status_sewa = 'diajukan'",
                    's',
                    [$kodeSewa],
                    'Pembaruan status transaksi'
                );
                $berubahSewa = $stmt->affected_rows;
                $stmt->close();
                if ($berubahSewa !== 1) {
                    throw new RuntimeException('Status transaksi tidak berubah; verifikasi dibatalkan.');
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // ---------------------------------------------------------------
    // Sisi admin - Pelunasan Transaksi (P10)
    // ---------------------------------------------------------------

    /**
     * P10: Mengambil daftar transaksi yang dapat diproses pelunasannya.
     * Total transaksi menggunakan snapshot tarif pada t_pilih.
     * Pembayaran yang mengurangi kewajiban hanya yang berstatus valid.
     */
    public function getDaftarPelunasan(): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                s.kode_sewa,
                s.tgl_mulai,
                s.tgl_selesai,
                s.status_sewa,
                p.user_plg,
                p.nm_lngkp,
                p.no_hp,
                COALESCE((
                    SELECT SUM(tp.hrg_sewa * tp.n_hari)
                    FROM t_pilih tp
                    WHERE tp.kode_sewa = s.kode_sewa
                ), 0) AS total_transaksi,
                COALESCE((
                    SELECT SUM(b.nominal)
                    FROM t_bayar b
                    WHERE b.kode_sewa = s.kode_sewa
                      AND b.sttus_byr = 'valid'
                ), 0) AS total_terbayar
            FROM t_sewa s
            INNER JOIN t_penyewa p
                ON p.user_plg = s.user_plg
            WHERE s.status_sewa IN ('disetujui', 'lunas')
              AND EXISTS (
                  SELECT 1
                  FROM t_pilih tp
                  WHERE tp.kode_sewa = s.kode_sewa
              )
            ORDER BY s.tgl_mulai DESC, s.kode_sewa DESC"
        );

        if (!$stmt) {
            throw new RuntimeException(
                'Query daftar pelunasan gagal disiapkan.'
            );
        }

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException(
                'Daftar pelunasan gagal diambil.'
            );
        }

        $daftar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($daftar as &$item) {
            $item['total_transaksi'] = (int) $item['total_transaksi'];
            $item['total_terbayar']  = (int) $item['total_terbayar'];
            $item['sisa_kewajiban']  = max(
                0,
                $item['total_transaksi'] - $item['total_terbayar']
            );
        }
        unset($item);

        return array_values(array_filter(
            $daftar,
            static fn(array $item): bool =>
                $item['sisa_kewajiban'] > 0 || $item['status_sewa'] === 'lunas'
        ));
    }

    /**
     * P10: Mengambil detail transaksi, penyewa, dan riwayat pembayaran
     * untuk halaman pelunasan.
     */
    public function getDetailPelunasan(string $kodeSewa): ?array
    {
        $kodeSewa = trim($kodeSewa);
        if ($kodeSewa === '') {
            throw new InvalidArgumentException(
                'Kode transaksi sewa wajib diisi.'
            );
        }

        $stmt = $this->db->prepare(
            "SELECT
                s.kode_sewa,
                s.tgl_mulai,
                s.tgl_selesai,
                s.wkt_ambil,
                s.status_sewa,
                p.user_plg,
                p.nm_lngkp,
                p.no_hp,
                p.nik,
                p.almt_lngkp,
                COALESCE((
                    SELECT SUM(tp.hrg_sewa * tp.n_hari)
                    FROM t_pilih tp
                    WHERE tp.kode_sewa = s.kode_sewa
                ), 0) AS total_transaksi,
                COALESCE((
                    SELECT SUM(b.nominal)
                    FROM t_bayar b
                    WHERE b.kode_sewa = s.kode_sewa
                      AND b.sttus_byr = 'valid'
                ), 0) AS total_terbayar
            FROM t_sewa s
            INNER JOIN t_penyewa p
                ON p.user_plg = s.user_plg
            WHERE s.kode_sewa = ?
            LIMIT 1"
        );

        if (!$stmt) {
            throw new RuntimeException(
                'Query detail pelunasan gagal disiapkan.'
            );
        }

        $stmt->bind_param('s', $kodeSewa);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException(
                'Detail transaksi pelunasan gagal diambil.'
            );
        }

        $detail = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($detail === null) {
            return null;
        }

        $detail['total_transaksi'] = (int) $detail['total_transaksi'];
        $detail['total_terbayar']  = (int) $detail['total_terbayar'];
        $detail['sisa_kewajiban']  = max(
            0,
            $detail['total_transaksi'] - $detail['total_terbayar']
        );

        $stmt = $this->db->prepare(
            "SELECT
                kode_bayar,
                tgl_wkt_byr,
                mtode_byr,
                jns_byr,
                nominal,
                sttus_byr,
                ft_byr,
                tgl_wkt_ver
            FROM t_bayar
            WHERE kode_sewa = ?
            ORDER BY tgl_wkt_byr ASC, kode_bayar ASC"
        );

        if (!$stmt) {
            throw new RuntimeException(
                'Query riwayat pembayaran gagal disiapkan.'
            );
        }

        $stmt->bind_param('s', $kodeSewa);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException(
                'Riwayat pembayaran gagal diambil.'
            );
        }

        $detail['riwayat_pembayaran'] =
            $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $this->db->prepare(
            "SELECT
                m.no_plat,
                m.nm_mobil,
                tp.hrg_sewa,
                tp.n_hari,
                (tp.hrg_sewa * tp.n_hari) AS subtotal
            FROM t_pilih tp
            INNER JOIN t_mobil m
                ON m.no_plat = tp.no_plat
            WHERE tp.kode_sewa = ?
            ORDER BY m.nm_mobil ASC, m.no_plat ASC"
        );

        if (!$stmt) {
            throw new RuntimeException(
                'Query detail kendaraan pelunasan gagal disiapkan.'
            );
        }

        $stmt->bind_param('s', $kodeSewa);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException(
                'Detail kendaraan pelunasan gagal diambil.'
            );
        }

        $detail['daftar_kendaraan'] =
            $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $detail;
    }

    /**
     * P10: Membuat kode pembayaran dengan format PAY/YYYYMMDD/NN.
     * Pemanggil harus memegang named lock pembayaran harian.
     */
    private function buatKodeBayarPelunasan(string $tanggalKode): string
    {
        $pola = 'PAY/' . $tanggalKode . '/%';
        $stmt = $this->db->prepare(
            "SELECT kode_bayar
            FROM t_bayar
            WHERE kode_bayar LIKE ?
            ORDER BY kode_bayar DESC
            LIMIT 1"
        );

        if (!$stmt) {
            throw new RuntimeException(
                'Pembuatan kode pembayaran gagal disiapkan.'
            );
        }

        $stmt->bind_param('s', $pola);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException(
                'Pembuatan kode pembayaran gagal dijalankan.'
            );
        }

        $terakhir = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $nomorBerikutnya = 1;
        if ($terakhir !== null) {
            $bagianKode = explode('/', (string) $terakhir['kode_bayar']);
            $nomorBerikutnya = ((int) end($bagianKode)) + 1;

            if ($nomorBerikutnya > 99) {
                throw new RuntimeException(
                    'Nomor pembayaran harian telah mencapai batas 99.'
                );
            }
        }

        return sprintf('PAY/%s/%02d', $tanggalKode, $nomorBerikutnya);
    }

    /**
     * P10: Mencatat pelunasan langsung oleh admin.
     * Pembayaran langsung dicatat sebagai valid.
     * Status transaksi diperbarui menjadi lunas dalam transaksi database
     * yang sama. Status kendaraan tidak diubah pada P10.
     */
    public function simpanPelunasan(
        string $kodeSewa,
        string $metodeBayar,
        ?string $namaFileBukti = null
    ): string {
        $kodeSewa    = trim($kodeSewa);
        $metodeBayar = trim($metodeBayar);

        if ($kodeSewa === '') {
            throw new InvalidArgumentException(
                'Kode transaksi sewa wajib diisi.'
            );
        }

        if (!in_array($metodeBayar, ['tunai', 'transfer'], true)) {
            throw new InvalidArgumentException(
                'Metode pembayaran tidak valid.'
            );
        }

        if ($metodeBayar === 'transfer') {
            $namaFileBukti = trim((string) $namaFileBukti);
            if ($namaFileBukti === '') {
                throw new InvalidArgumentException(
                    'Bukti transfer wajib tersedia.'
                );
            }
        } else {
            $namaFileBukti = null;
        }

        // Harus sama dengan kunci yang digunakan Transaksi_model::checkout()
        // ketika membuat kode pembayaran DP.
        $namaKunci = 'dpwl_payment_code_sequence';
        $kunciDiperoleh = false;
        $transaksiDimulai = false;

        try {
            $stmt = $this->db->prepare(
                'SELECT GET_LOCK(?, 10) AS lock_status'
            );
            if (!$stmt) {
                throw new RuntimeException(
                    'Kunci pembuatan kode pembayaran gagal disiapkan.'
                );
            }

            $stmt->bind_param('s', $namaKunci);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException(
                    'Kunci pembuatan kode pembayaran gagal dijalankan.'
                );
            }

            $hasilKunci = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ((int) ($hasilKunci['lock_status'] ?? 0) !== 1) {
                throw new RuntimeException(
                    'Proses pembayaran sedang digunakan. Silakan coba kembali.'
                );
            }

            $kunciDiperoleh = true;

            $this->db->begin_transaction();
            $transaksiDimulai = true;

            // Kunci transaksi agar dua permintaan pelunasan tidak berjalan
            // bersamaan untuk transaksi yang sama.
            $stmt = $this->db->prepare(
                "SELECT
                    s.kode_sewa,
                    s.status_sewa,
                    COALESCE((
                        SELECT SUM(tp.hrg_sewa * tp.n_hari)
                        FROM t_pilih tp
                        WHERE tp.kode_sewa = s.kode_sewa
                    ), 0) AS total_transaksi,
                    COALESCE((
                        SELECT SUM(b.nominal)
                        FROM t_bayar b
                        WHERE b.kode_sewa = s.kode_sewa
                          AND b.sttus_byr = 'valid'
                    ), 0) AS total_terbayar
                FROM t_sewa s
                WHERE s.kode_sewa = ?
                LIMIT 1
                FOR UPDATE"
            );

            if (!$stmt) {
                throw new RuntimeException(
                    'Pemeriksaan transaksi pelunasan gagal disiapkan.'
                );
            }

            $stmt->bind_param('s', $kodeSewa);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException(
                    'Pemeriksaan transaksi pelunasan gagal dijalankan.'
                );
            }

            $transaksi = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($transaksi === null) {
                throw new RuntimeException(
                    'Transaksi sewa tidak ditemukan.'
                );
            }

            if (!in_array(
                $transaksi['status_sewa'],
                ['disetujui', 'lunas'],
                true
            )) {
                throw new RuntimeException(
                    'Status transaksi belum memenuhi syarat pelunasan.'
                );
            }

            $totalTransaksi = (int) $transaksi['total_transaksi'];
            $totalTerbayar  = (int) $transaksi['total_terbayar'];
            $sisaKewajiban  = $totalTransaksi - $totalTerbayar;

            if ($totalTransaksi <= 0 || $sisaKewajiban <= 0) {
                throw new RuntimeException(
                    'Transaksi tidak memiliki sisa kewajiban untuk dilunasi.'
                );
            }

            if ($sisaKewajiban > 8388607) {
                throw new RuntimeException(
                    'Sisa kewajiban melebihi kapasitas kolom nominal pada database.'
                );
            }

            // Pastikan transaksi belum memiliki record pelunasan.
            $stmt = $this->db->prepare(
                "SELECT kode_bayar
                FROM t_bayar
                WHERE kode_sewa = ?
                  AND jns_byr = 'pelunasan'
                LIMIT 1
                FOR UPDATE"
            );

            if (!$stmt) {
                throw new RuntimeException(
                    'Pemeriksaan pelunasan sebelumnya gagal disiapkan.'
                );
            }

            $stmt->bind_param('s', $kodeSewa);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException(
                    'Pemeriksaan pelunasan sebelumnya gagal dijalankan.'
                );
            }

            $pelunasanAda = $stmt->get_result()->fetch_assoc() !== null;
            $stmt->close();

            if ($pelunasanAda) {
                throw new RuntimeException(
                    'Transaksi ini sudah memiliki record pelunasan.'
                );
            }

            $hasilWaktu = $this->db->query(
                "SELECT
                    DATE_FORMAT(NOW(), '%Y%m%d') AS tanggal_kode,
                    NOW() AS waktu_pembayaran"
            );

            if (!$hasilWaktu) {
                throw new RuntimeException(
                    'Waktu pembayaran gagal dibaca.'
                );
            }

            $waktu = $hasilWaktu->fetch_assoc();
            $hasilWaktu->free();

            $tanggalKode     = (string) ($waktu['tanggal_kode'] ?? '');
            $waktuPembayaran = (string) ($waktu['waktu_pembayaran'] ?? '');

            if (
                !preg_match('/^\d{8}$/', $tanggalKode) ||
                $waktuPembayaran === ''
            ) {
                throw new RuntimeException(
                    'Waktu pembayaran tidak valid.'
                );
            }

            $kodeBayar = $this->buatKodeBayarPelunasan($tanggalKode);

            $stmt = $this->db->prepare(
                "INSERT INTO t_bayar (
                    kode_bayar,
                    tgl_wkt_byr,
                    ft_byr,
                    mtode_byr,
                    jns_byr,
                    nominal,
                    sttus_byr,
                    tgl_wkt_ver,
                    kode_sewa
                ) VALUES (?, ?, ?, ?, 'pelunasan', ?, 'valid', NOW(), ?)"
            );

            if (!$stmt) {
                throw new RuntimeException(
                    'Pencatatan pelunasan gagal disiapkan.'
                );
            }

            $stmt->bind_param(
                'ssssis',
                $kodeBayar,
                $waktuPembayaran,
                $namaFileBukti,
                $metodeBayar,
                $sisaKewajiban,
                $kodeSewa
            );

            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException(
                    'Pelunasan gagal disimpan.'
                );
            }
            $stmt->close();

            // Pelunasan berhasil dicatat; perbarui status transaksi
            // dalam transaksi database yang sama.
            $stmt = $this->db->prepare(
                "UPDATE t_sewa
                SET status_sewa = 'lunas'
                WHERE kode_sewa = ?
                  AND status_sewa IN ('disetujui', 'lunas')"
            );

            if (!$stmt) {
                throw new RuntimeException(
                    'Pembaruan status transaksi menjadi lunas gagal disiapkan.'
                );
            }

            $stmt->bind_param('s', $kodeSewa);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException(
                    'Status transaksi gagal diperbarui menjadi lunas.'
                );
            }

            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new RuntimeException(
                    'Status transaksi tidak berubah menjadi lunas. ' .
                    'Periksa kembali status transaksi.'
                );
            }
            $stmt->close();

            $this->db->commit();
            $transaksiDimulai = false;

            return $kodeBayar;
        } catch (Throwable $e) {
            if ($transaksiDimulai) {
                $this->db->rollback();
            }
            throw $e;
        } finally {
            if ($kunciDiperoleh) {
                $stmt = $this->db->prepare(
                    'SELECT RELEASE_LOCK(?)'
                );
                if ($stmt) {
                    $stmt->bind_param('s', $namaKunci);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
}
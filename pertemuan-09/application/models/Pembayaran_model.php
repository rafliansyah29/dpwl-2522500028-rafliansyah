<?php
// P9 BARU - application/models/Pembayaran_model.php
// Mengelola pembayaran DP yang sudah dibuat saat checkout (t_bayar, jns_byr = 'dp').
// Tidak ada INSERT: seluruh proses P9 hanya melakukan UPDATE pada record DP yang ada.
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
    // Sisi penyewa
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
    // Sisi admin
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
}

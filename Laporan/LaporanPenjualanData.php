<?php
include __DIR__ . '/../Database/DBConnection.php';
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);

try {
    if ($conn->connect_error) {
        throw new Exception("Koneksi Database Gagal");
    }

    $bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
    $tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

    /* PERBAIKAN QUERY (NET SALES):
       Kita menghitung 'Terjual' dengan rumus:
       (Total Keluar dari Penjualan) DIKURANGI (Total Masuk dari Retur)
    */
    $sql = "SELECT 
                b.KodeBarang, 
                b.NamaBarang, 
                k.NamaKategori,
                s.NamaSatuan,
                b.Jumlah AS StokSisa,
                
                -- HITUNG TOTAL KELUAR (Penjualan Kasir + SJ)
                COALESCE(SUM(CASE 
                    WHEN ks.JenisTransaksi IN ('KASIR', 'SJ') THEN ks.Keluar 
                    ELSE 0 
                END), 0) AS TotalJualKotor,

                -- HITUNG TOTAL RETUR (Barang Masuk karena Retur)
                COALESCE(SUM(CASE 
                    WHEN ks.JenisTransaksi = 'RETUR' THEN ks.Masuk 
                    ELSE 0 
                END), 0) AS TotalRetur

            FROM tblbarang b
            LEFT JOIN tblkategoribarang k ON b.idKategori = k.KodeKategori
            LEFT JOIN tblsatuan s ON b.Satuan = s.KodeSatuan
            LEFT JOIN tblkartustok ks ON b.KodeBarang = ks.idBarang 
                AND MONTH(ks.Tanggal) = ? 
                AND YEAR(ks.Tanggal) = ?
            WHERE b.Aktif = '1'
            GROUP BY b.KodeBarang
            ORDER BY b.NamaBarang ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];

    while ($row = $result->fetch_assoc()) {
        $jualKotor = (int)$row['TotalJualKotor'];
        $totalRetur = (int)$row['TotalRetur'];
        $stokSisa = (int)$row['StokSisa'];
        
        // --- RUMUS BARU: PENJUALAN BERSIH ---
        // Jika laku 10 tapi retur 2, maka dianggap terjual 8.
        $terjualBersih = $jualKotor - $totalRetur;
        
        // Hindari angka minus (antisipasi kesalahan data)
        if ($terjualBersih < 0) $terjualBersih = 0;

        $namaSatuan = !empty($row['NamaSatuan']) ? $row['NamaSatuan'] : '-';

        // --- RUMUS FSN (Berdasarkan Penjualan Bersih) ---
        // Total Sedia = Apa yang ada di rak sekarang + Apa yang sudah laku terjual bersih
        $totalSedia = $stokSisa + $terjualBersih;
        
        $persen = 0;
        if ($totalSedia > 0) {
            $persen = ($terjualBersih / $totalSedia) * 100;
        }

        // Klasifikasi
        $status = ($persen > 30) ? "FAST" : "SLOW";

        // Kita kirimkan data bersih ke layar
        $data[] = [
            'kode'     => $row['KodeBarang'],
            'nama'     => $row['NamaBarang'],
            'kategori' => $row['NamaKategori'],
            'satuan'   => $namaSatuan,
            'qty'      => $terjualBersih, // <-- Ini sekarang angka bersih
            'stok'     => $stokSisa,
            'persen'   => round($persen, 0),
            'status'   => $status,
            'info_retur' => $totalRetur // Opsional: jika ingin melihat berapa yg diretur di console
        ];
    }

    echo json_encode($data);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
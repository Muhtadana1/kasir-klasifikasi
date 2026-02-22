<?php
// FILE: Laporan/LaporanPenjualanExcel.php
include '../Database/DBConnection.php';

// 1. HEADER AGAR BROWSER DOWNLOAD SEBAGAI EXCEL
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Semua_Fast_Slow.xls");

// 2. AMBIL FILTER (Jika ada), Default = Tampilkan Semua
$bulan    = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun    = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Nama Bulan untuk Judul
$namaBulan = DateTime::createFromFormat('!m', $bulan)->format('F');
?>

<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid black; padding: 8px; vertical-align: middle; }
        th { background-color: #343a40; color: white; font-weight: bold; text-align: center; }
        .text-center { text-align: center; }
        .bg-success { background-color: #d1e7dd; color: #0f5132; } /* Hijau (Fast) */
        .bg-danger { background-color: #f8d7da; color: #842029; } /* Merah (Slow) */
    </style>
</head>
<body>

    <center>
        <h2>LAPORAN ANALISIS BARANG (FAST & SLOW MOVING)</h2>
        <p>Periode: <?= $namaBulan . " " . $tahun; ?></p>
    </center>
    <br>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th>Satuan</th>
                <th style="background-color: #e2e3e5; color: black;">Terjual (Bersih)</th>
                <th>Sisa Stok</th>
                <th>Rasio (%)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // QUERY MENGAMBIL SEMUA BARANG AKTIF
            // Kita tidak pakai filter kategori/status di sini agar SEMUA muncul
            $sql = "SELECT 
                        b.KodeBarang, 
                        b.NamaBarang, 
                        k.NamaKategori,
                        s.NamaSatuan,
                        b.Jumlah AS StokSisa,
                        
                        -- Total Jual
                        COALESCE(SUM(CASE WHEN ks.JenisTransaksi IN ('KASIR', 'SJ') THEN ks.Keluar ELSE 0 END), 0) AS TotalJualKotor,
                        
                        -- Total Retur
                        COALESCE(SUM(CASE WHEN ks.JenisTransaksi = 'RETUR' THEN ks.Masuk ELSE 0 END), 0) AS TotalRetur

                    FROM tblbarang b
                    LEFT JOIN tblkategoribarang k ON b.idKategori = k.KodeKategori
                    LEFT JOIN tblsatuan s ON b.Satuan = s.KodeSatuan
                    LEFT JOIN tblkartustok ks ON b.KodeBarang = ks.idBarang 
                        AND MONTH(ks.Tanggal) = '$bulan' 
                        AND YEAR(ks.Tanggal) = '$tahun'
                    WHERE b.Aktif = '1'
                    GROUP BY b.KodeBarang
                    ORDER BY b.NamaBarang ASC";

            $result = $conn->query($sql);
            $no = 1;

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $jualKotor  = (int)$row['TotalJualKotor'];
                    $totalRetur = (int)$row['TotalRetur'];
                    $stokSisa   = (int)$row['StokSisa'];

                    // 1. Hitung Penjualan Bersih
                    $terjualBersih = $jualKotor - $totalRetur;
                    if ($terjualBersih < 0) $terjualBersih = 0;

                    // 2. Hitung Total Ketersediaan (Stok Akhir + Yang Laku)
                    $totalSedia = $stokSisa + $terjualBersih;

                    // 3. Hitung Persentase
                    $persen = 0;
                    if ($totalSedia > 0) {
                        $persen = ($terjualBersih / $totalSedia) * 100;
                    }

                    // 4. Tentukan Status (Batas 30%)
                    if ($persen > 30) {
                        $status = "FAST MOVING";
                        $bgClass = "bg-success";
                    } else {
                        $status = "SLOW MOVING";
                        $bgClass = "bg-danger";
                    }

                    $satuan = !empty($row['NamaSatuan']) ? $row['NamaSatuan'] : '-';
            ?>
                    <tr>
                        <td class="text-center"><?= $no++; ?></td>
                        <td class="text-center"><?= htmlspecialchars($row['KodeBarang']); ?></td>
                        <td><?= htmlspecialchars($row['NamaBarang']); ?></td>
                        <td class="text-center"><?= htmlspecialchars($row['NamaKategori']); ?></td>
                        <td class="text-center"><?= $satuan; ?></td>
                        
                        <td class="text-center" style="background-color: #f8f9fa;"><strong><?= $terjualBersih; ?></strong></td>
                        <td class="text-center"><?= $stokSisa; ?></td>
                        <td class="text-center"><?= round($persen, 1); ?>%</td>
                        
                        <td class="text-center <?= $bgClass; ?>"><strong><?= $status; ?></strong></td>
                    </tr>
            <?php
                }
            } else {
                echo "<tr><td colspan='9' class='text-center'>Belum ada data barang.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</body>
</html>
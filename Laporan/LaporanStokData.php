<?php
// File: Laporan/LaporanStokData.php
include __DIR__ . '/../Database/DBConnection.php';
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);

if ($conn === null) {
    echo json_encode(['error' => 'Koneksi gagal']);
    exit;
}

$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$sql = "SELECT 
            b.KodeBarang, 
            b.NamaBarang, 
            s.NamaSatuan,  
            b.Jumlah AS StokAkhir, 
            b.PO AS StokPO,
            k.NamaKategori,
            COALESCE(SUM(ks.Masuk), 0) AS TotalMasuk,
            COALESCE(SUM(ks.Keluar), 0) AS TotalKeluar
        FROM tblbarang b
        LEFT JOIN tblkategoribarang k ON b.idKategori = k.KodeKategori
        LEFT JOIN tblsatuan s ON b.Satuan = s.KodeSatuan 
        LEFT JOIN tblkartustok ks ON b.KodeBarang = ks.idBarang 
            AND MONTH(ks.Tanggal) = ? 
            AND YEAR(ks.Tanggal) = ?
        GROUP BY b.KodeBarang, b.NamaBarang, s.NamaSatuan, b.Jumlah, b.PO, k.NamaKategori
        ORDER BY b.NamaBarang ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $bulan, $tahun);
$stmt->execute();
$result = $stmt->get_result();

$data = [];

while ($row = $result->fetch_assoc()) {
    $stokAkhir   = (int)$row['StokAkhir'];
    $totalMasuk  = (int)$row['TotalMasuk'];
    $totalKeluar = (int)$row['TotalKeluar'];
    $stokPO      = (int)$row['StokPO'];
    
    $stokAwal = ($stokAkhir - $totalMasuk) + $totalKeluar;
    $satuanTeks = !empty($row['NamaSatuan']) ? $row['NamaSatuan'] : '-';

    // --- LOGIKA BARU (BATAS 10) ---
    $status = "AMAN";
    $pesan = "Stok Tersedia";
    
    if ($stokAkhir == 0) {
        $status = "HABIS";
        $pesan = "Stok Kosong!";
    } elseif ($stokAkhir < 10) { // <--- DIGANTI JADI 10
        $status = "KRITIS";
        $pesan = "Menipis ($stokAkhir unit)";
    }

    $data[] = [
        'KodeBarang' => $row['KodeBarang'],
        'NamaBarang' => $row['NamaBarang'],
        'Kategori'   => $row['NamaKategori'],
        'Satuan'     => $satuanTeks,
        'StokAwal'   => $stokAwal,
        'Masuk'      => $totalMasuk,
        'Keluar'     => $totalKeluar,
        'StokAkhir'  => $stokAkhir,
        'StokPO'     => $stokPO,
        'Status'     => $status,
        'Pesan'      => $pesan
    ];
}

echo json_encode($data);
?>
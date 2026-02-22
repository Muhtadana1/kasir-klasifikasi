<?php
session_start(); // PENTING: Start session untuk baca hak akses
include __DIR__ . '/../Database/DBConnection.php';

if ($conn === null) exit;

// Ambil Izin dari Session (Default 0 jika tidak ada)
$akses = $_SESSION['hak_akses']['Barang'] ?? ['Edit' => 0, 'Delete' => 0];

$cari = isset($_GET['cari']) ? $_GET['cari'] : '';
$searchTerm = "%" . $cari . "%";

$sql = "SELECT *, IF(HarusOrder='1','YA','TIDAK') AS HrsOrder FROM viewBarang 
        WHERE NamaBarang LIKE ? OR NamaKategori LIKE ? 
        ORDER BY NamaBarang LIMIT 100";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Siapkan data untuk parameter fungsi Edit JS (Escape string agar aman dari tanda kutip)
        $kode   = htmlspecialchars($row['KodeBarang']);
        $nama   = htmlspecialchars(addslashes($row['NamaBarang']));
        $idkat  = $row['idKategori'];
        $kat    = htmlspecialchars(addslashes($row['NamaKategori']));
        $sat    = htmlspecialchars($row['Satuan']);
        $beli   = $row['HargaBeli'];
        $jual   = $row['HargaJual'];
        $stok   = $row['Jumlah'];
        $lama   = $row['LamaOrder'];
        $cat    = htmlspecialchars(addslashes($row['Catatan']));

        echo '<tr style="height:30px">';
        echo '<td>' . htmlspecialchars($row['NamaBarang']) . '</td>';
        echo '<td>' . htmlspecialchars($row['NamaKategori']) . '</td>';            
        echo '<td align="right">' . number_format($row['HargaBeli'], 0, ',', '.') . '</td>';         
        echo '<td align="right">' . number_format($row['HargaJual'], 0, ',', '.') . '</td>';
        echo '<td align="right">' . $row['Jumlah'] . '</td>';
        echo '<td>' . htmlspecialchars($row['Satuan']) . '</td>';
        echo '<td align="right">' . $row['LamaOrder'] . '</td>';
        echo '<td align="center">' . $row['HrsOrder'] . '</td>';
        
        // --- KOLOM AKSI DINAMIS ---
        if ($akses['Edit'] || $akses['Delete']) {
            echo '<td align="center">';
            
            if ($akses['Edit']) {
                echo "<button class='btn btn-sm btn-primary me-1' onclick=\"EditBarang('$kode','$nama','$idkat','$kat','$sat','$beli','$jual','$stok','$lama','$cat')\"><i class='fa-solid fa-pen'></i></button>";
            }
            
            if ($akses['Delete']) {
                echo "<button class='btn btn-sm btn-danger' onclick=\"DeleteBarang('$kode','$nama')\"><i class='fa-solid fa-trash'></i></button>";
            }
            
            echo '</td>';
        }
        echo '</tr>';
    }
} else {
    // Hitung jumlah kolom agar "Data tidak ditemukan" rapi di tengah
    $colspan = 8 + (($akses['Edit'] || $akses['Delete']) ? 1 : 0);
    echo "<tr><td colspan='$colspan' class='text-center p-3'>Data tidak ditemukan!</td></tr>";
}
?>
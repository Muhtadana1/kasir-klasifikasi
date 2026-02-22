<?php
if (isset($_GET['func'])) {
  
    // Mundur satu folder (..) lalu masuk ke folder Database
include __DIR__ . '/../Database/DBConnection.php';
    if ($_GET['func'] == 'kategoribarang') {

        $sql = "SELECT KodeKategori, NamaKategori FROM TblKategoriBarang ORDER BY NamaKategori";
        $result = $conn->query($sql);

        $kategori = [];
        while ($row = $result->fetch_assoc()) {
            $kategori[] = $row;
        }
        echo json_encode($kategori);
    } else if ($_GET['func'] == 'satuan') {

        $sql = "SELECT KodeSatuan, NamaSatuan FROM TblSatuan ORDER BY NamaSatuan";
        $result = $conn->query($sql);

        $kategori = [];
        while ($row = $result->fetch_assoc()) {
            $kategori[] = $row;
        }
    
        echo json_encode($kategori);
    } else if ($_GET['func'] == 'kategoriuser') {
        $sql = "SELECT NamaKategoriUser FROM TblKategoriUser ORDER BY NamaKategoriUser";
        $result = $conn->query($sql);
        $kategori = [];
        while ($row = $result->fetch_assoc()) {
            $kategori[] = $row;
        }
        echo json_encode($kategori);
    } else if ($_GET['func'] == 'namabarangada') {
        $namabarang = $_GET['namabarang'];
        $idkategori = $_GET['idkategori'];
        $sql = "SELECT NamaBarang FROM viewBarang WHERE NamaBarang= ? AND idKategori= ? ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $namabarang, $idkategori);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'Barang sudah ada']);
        } else {
            echo json_encode(['success' => true, 'error' => 'Barang Belum ada']);
        }
    } else if ($_GET['func'] == 'getnotano') {
        $JenisNota = $_GET['jenisnota'];
        $Tanggal = $_GET['tanggal'];
        $sql = "SELECT FuncGetNotaNo( ? , ? ) AS LastNo; ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $Tanggal, $JenisNota);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc(); // Mengambil baris pertama sebagai array asosiatif
        echo $row['LastNo'];
    }
  
}
?>

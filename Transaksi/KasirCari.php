<?php
include __DIR__ . '/../Database/DBConnection.php';
header('Content-Type: application/json');

if ($conn === null) {
    echo json_encode([]);
    exit;
}

$keyword = isset($_GET['q']) ? $_GET['q'] : '';
$kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';

if ($kategori != '') {
    // Mode Kategori: Tampilkan semua barang di kategori tersebut
    $sql = "SELECT KodeBarang, NamaBarang, HargaJual 
            FROM viewBarang 
            WHERE idKategori = ? AND Aktif='1' 
            ORDER BY NamaBarang ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $kategori);

} else {
    // Mode Cari Manual: Cari berdasarkan Nama Barang
    $sql = "SELECT KodeBarang, NamaBarang, HargaJual 
            FROM viewBarang 
            WHERE NamaBarang LIKE ? AND Aktif='1' 
            ORDER BY NamaBarang ASC LIMIT 15"; // Batasi 15 agar ringan
    $stmt = $conn->prepare($sql);
    $searchParam = "%" . $keyword . "%";
    $stmt->bind_param("s", $searchParam);
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id' => $row['KodeBarang'],
        'text' => $row['NamaBarang'],
        'harga' => $row['HargaJual']
    ];
}

echo json_encode($data);
$conn->close();
?>
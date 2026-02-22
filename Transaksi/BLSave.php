<?php
// Sambungkan ke database
include __DIR__ . '/../Database/DBConnection.php';
include __DIR__ . '/../Database/UserLoginAuth.php';
if ($conn === null) {
    echo json_encode(['success' => false, 'error' => 'koneksi gagal']);
    exit;
}

// Ambil data JSON dari request
$data = json_decode(file_get_contents("php://input"), true);

if ($data === null) {
    echo json_encode(['success' => false, 'error' => 'Data BL tidak valid !']);
    exit;
}


// Ambil nilai dari data
$kodesupplier = $data['kodesupplier'];
$tanggal = $data['tanggal'];
$kodepo = $data['kodepo'];
$subtotal = $data['subtotal'];
$ppn = $data['ppn'];
$grandtotal = $data['grandtotal'];
$catatan = $data['catatan'];
$totalbarang=$data['totalbarang'];
$detailBarang = $data['detailbarang'];
$ppnrp = $grandtotal - $subtotal;
$kodebl = $data["idbl"];
// Simpan data ke database
if ($kodebl == "") {
    $auth = new Auth($conn);
    $kodebl = $auth->GetNotaNo($tanggal, 'BL');    
    $sql = "INSERT INTO TblPenerimaanBarang (KodePenerimaan,Tanggal,idSupplier,TotalBarang,SubTotal,PPN,PPNRp,Diskon,GrandTotal,Catatan,idPO,Aktif) VALUES (?,?,?,?,?,?,?,0,?,?,?,'1')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", $kodebl, $tanggal, $kodesupplier, $totalbarang, $subtotal, $ppn, $ppnrp, $grandtotal, $catatan, $kodepo);
} else {   
    $sql = "UPDATE TblPenerimaanBarang SET Tanggal=?,idSupplier=?,TotalBarang=?,SubTotal=?,PPN=?,PPNRp=?,GrandTotal=?,Catatan=? WHERE KodePenerimaan=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssss", $Tanggal, $kodesupplier,$totalbarang, $subtotal, $ppn, $ppnrp,$grandtotal, $catatan, $Kodebl);
              
    $sql = "DELETE FROM tblpenerimaanbarangdetailbarang WHERE idPenerimaan=?";
    $stmt2 = $conn->prepare($sql);
    $stmt2->bind_param("s", $kodebl);
    $stmt2->execute();   
   
    $stmt2->close();
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);    
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);    
}

$sql = "INSERT INTO tblpenerimaanbarangdetailbarang (idPenerimaan,idBarang,Jumlah,HargaBeli,TotalHarga,idDetailPO) VALUES (?,?,?,?,?,?);";
$stmt2 = $conn->prepare($sql);

foreach ($detailBarang as $item) {
    $stmt2->bind_param("ssssss", $kodebl,$item['kodebarang'], $item['jumlah'], $item['harga'], $item['total'],$item['iddetailpo']);
    $stmt2->execute();   
}
$stmt2->close();
$stmt->close();
$conn->close();
?>

<?php
include __DIR__ . '/../Database/DBConnection.php';
include __DIR__ . '/../Database/UserLoginAuth.php';

if ($conn === null) { echo json_encode(['success'=>false, 'error'=>'Koneksi gagal']); exit; }

// CEK KEAMANAN
$auth = new Auth($conn);
if (!$auth->cekAkses('Barang', 'Delete')) {
    echo json_encode(['success'=>false, 'error'=>'AKSES DITOLAK: Izin Delete tidak ada.']); exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$kodebarang = $data['kodebarang'];

$sql = "DELETE FROM TblBarang WHERE KodeBarang=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $kodebarang);

if ($stmt->execute()) echo json_encode(['success'=>true]);
else echo json_encode(['success'=>false, 'error'=>$stmt->error]);
?>
<?php
include __DIR__ . '/../Database/DBConnection.php';
include __DIR__ . '/../Database/UserLoginAuth.php'; // PENTING

if ($conn === null) { echo json_encode(['success'=>false, 'error'=>'Koneksi gagal']); exit; }

// --- CEK KEAMANAN ---
$auth = new Auth($conn);
if (!$auth->cekAkses('Customer', 'Delete')) {
    echo json_encode(['success'=>false, 'error'=>'AKSES DITOLAK: Anda tidak boleh menghapus Customer.']); exit;
}
// --------------------

$data = json_decode(file_get_contents("php://input"), true);
$kode = $data['kodecustomer'];

$sql = "DELETE FROM TblCustomer WHERE KodeCustomer=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $kode);

if ($stmt->execute()) echo json_encode(['success'=>true]);
else echo json_encode(['success'=>false, 'error'=>$stmt->error]);

$stmt->close();
$conn->close();
?>
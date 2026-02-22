<?php
include __DIR__ . '/../Database/DBConnection.php';
include __DIR__ . '/../Database/UserLoginAuth.php';

if ($conn === null) { echo json_encode(['success'=>false, 'error'=>'Koneksi gagal']); exit; }

$auth = new Auth($conn);
if (!$auth->cekAkses('User', 'Delete')) {
    echo json_encode(['success'=>false, 'error'=>'AKSES DITOLAK: Anda tidak boleh menghapus User.']); exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$kodeuser = $data['kodeuser'];

// Validasi: Tidak boleh hapus SuperAdmin (ID 1 biasanya)
// Sesuaikan ID ini dengan ID Admin utama Anda di database
if ($kodeuser == 1 || $kodeuser == 3) { 
    echo json_encode(['success'=>false, 'error'=>'User Administrator Utama tidak boleh dihapus!']); exit;
}

// Validasi: Tidak boleh hapus diri sendiri
if (isset($_SESSION['kodeuser']) && $_SESSION['kodeuser'] == $kodeuser) {
    echo json_encode(['success'=>false, 'error'=>'Anda tidak bisa menghapus akun sendiri!']); exit;
}

$sql = "DELETE FROM TblLogin WHERE KodeUser=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $kodeuser);

if ($stmt->execute()) echo json_encode(['success'=>true]);
else echo json_encode(['success'=>false, 'error'=>$stmt->error]);

$stmt->close();
$conn->close();
?>
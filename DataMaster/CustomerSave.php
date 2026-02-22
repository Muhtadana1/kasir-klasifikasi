<?php
include __DIR__ . '/../Database/DBConnection.php';
include __DIR__ . '/../Database/UserLoginAuth.php'; // PENTING

if ($conn === null) { echo json_encode(['success'=>false, 'error'=>'Koneksi gagal']); exit; }

// --- CEK KEAMANAN ---
$auth = new Auth($conn);
$data = json_decode(file_get_contents("php://input"), true);

// Tentukan mode: Baru (0) atau Edit (Bukan 0)
$isNew = ($data['kodecustomer'] == "0");

if ($isNew && !$auth->cekAkses('Customer', 'Add')) {
    echo json_encode(['success'=>false, 'error'=>'AKSES DITOLAK: Anda tidak boleh menambah Customer.']); exit;
}
if (!$isNew && !$auth->cekAkses('Customer', 'Edit')) {
    echo json_encode(['success'=>false, 'error'=>'AKSES DITOLAK: Anda tidak boleh mengedit Customer.']); exit;
}
// --------------------

if (!isset($data['namacustomer'], $data['alamat'])) {
    echo json_encode(['success'=>false, 'error'=>'Data tidak lengkap']); exit;
}

$kode = $data['kodecustomer'];
$nama = $data['namacustomer'];
$alm  = $data['alamat'];
$telp = $data['telp'];
$sales= $data['pemasaran'];
$hpsales=$data['telppemasaran'];
$cat  = $data['catatan'];

if ($kode == "0") {
    $sql = "INSERT INTO TblCustomer (NamaCustomer,Alamat,Telp,NamaSales,TelpSales,Catatan,Aktif) VALUES (?,?,?,?,?,?,'1')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $nama, $alm, $telp, $sales, $hpsales, $cat);
} else {
    $sql = "UPDATE TblCustomer SET NamaCustomer=?,Alamat=?,Telp=?,NamaSales=?,TelpSales=?,Catatan=? WHERE KodeCustomer=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $nama, $alm, $telp, $sales, $hpsales, $cat, $kode);
}

if ($stmt->execute()) echo json_encode(['success'=>true]);
else echo json_encode(['success'=>false, 'error'=>$stmt->error]);

$stmt->close();
$conn->close();
?>
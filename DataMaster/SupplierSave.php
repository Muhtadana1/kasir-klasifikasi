<?php
include __DIR__ . '/../Database/DBConnection.php';
include __DIR__ . '/../Database/UserLoginAuth.php'; // PENTING: Load Auth

if ($conn === null) { echo json_encode(['success'=>false, 'error'=>'Koneksi gagal']); exit; }

// --- SECURITY CHECK (SATPAM) ---
$auth = new Auth($conn);
$data = json_decode(file_get_contents("php://input"), true);

// Tentukan apakah ini Tambah Baru (0) atau Edit
$isNew = ($data['kodesupplier'] == "0");

// Cek Izin
if ($isNew && !$auth->cekAkses('Supplier', 'Add')) {
    echo json_encode(['success'=>false, 'error'=>'AKSES DITOLAK: Anda tidak boleh menambah Supplier.']); exit;
}
if (!$isNew && !$auth->cekAkses('Supplier', 'Edit')) {
    echo json_encode(['success'=>false, 'error'=>'AKSES DITOLAK: Anda tidak boleh mengedit Supplier.']); exit;
}
// --- END SECURITY ---

if (!isset($data['namasupplier'], $data['alamat'])) {
    echo json_encode(['success'=>false, 'error'=>'Data tidak lengkap']); exit;
}

$kode = $data['kodesupplier'];
$nama = $data['namasupplier'];
$alm  = $data['alamat'];
$telp = $data['telp'];
$sales= $data['pemasaran'];
$hpsales=$data['telppemasaran'];
$cat  = $data['catatan'];

if ($kode == "0") {
    $sql = "INSERT INTO TblSupplier (NamaSupplier,Alamat,Telp,NamaSales,TelpSales,Catatan,Aktif) VALUES (?,?,?,?,?,?,'1')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $nama, $alm, $telp, $sales, $hpsales, $cat);
} else {
    $sql = "UPDATE TblSupplier SET NamaSupplier=?,Alamat=?,Telp=?,NamaSales=?,TelpSales=?,Catatan=? WHERE KodeSupplier=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $nama, $alm, $telp, $sales, $hpsales, $cat, $kode);
}

if ($stmt->execute()) echo json_encode(['success'=>true]);
else echo json_encode(['success'=>false, 'error'=>$stmt->error]);

$stmt->close();
$conn->close();
?>
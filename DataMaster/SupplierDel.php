<?php
include __DIR__ . '/../Database/DBConnection.php';
include __DIR__ . '/../Database/UserLoginAuth.php'; // PENTING

if ($conn === null) { echo json_encode(['success'=>false, 'error'=>'Koneksi gagal']); exit; }

// --- SECURITY CHECK (SATPAM) ---
$auth = new Auth($conn);
if (!$auth->cekAkses('Supplier', 'Delete')) {
    echo json_encode(['success'=>false, 'error'=>'AKSES DITOLAK: Anda tidak boleh menghapus Supplier.']); exit;
}
// --- END SECURITY ---

$data = json_decode(file_get_contents("php://input"), true);
$kode = $data['kodesupplier'];

// Tips: Sebaiknya cek dulu apakah supplier ini sudah dipakai di PO (Purchase Order)
// $cek = $conn->query("SELECT KodePO FROM tblpo WHERE idSupplier='$kode' LIMIT 1");
// if($cek->num_rows > 0) { echo json_encode(['success'=>false, 'error'=>'Gagal: Supplier ini sudah punya riwayat transaksi PO.']); exit; }

$sql = "DELETE FROM TblSupplier WHERE KodeSupplier=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $kode);

if ($stmt->execute()) echo json_encode(['success'=>true]);
else echo json_encode(['success'=>false, 'error'=>$stmt->error]);

$stmt->close();
$conn->close();
?>
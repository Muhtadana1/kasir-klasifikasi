<?php
include __DIR__ . '/../Database/DBConnection.php';
include __DIR__ . '/../Database/UserLoginAuth.php';

if ($conn === null) { echo json_encode(['success'=>false, 'error'=>'Koneksi gagal']); exit; }

// --- SECURITY CHECK ---
$auth = new Auth($conn);
$data = json_decode(file_get_contents("php://input"), true);

$isNew = ($data['kodeuser'] == "0");
if ($isNew && !$auth->cekAkses('User', 'Add')) {
    echo json_encode(['success'=>false, 'error'=>'AKSES DITOLAK: Anda tidak boleh menambah User.']); exit;
}
if (!$isNew && !$auth->cekAkses('User', 'Edit')) {
    echo json_encode(['success'=>false, 'error'=>'AKSES DITOLAK: Anda tidak boleh mengedit User.']); exit;
}
// ----------------------

$kodeuser = $data['kodeuser'];
$userlogin = $data['userlogin'];
$password = $data['password'];
$kategori = $data['kategori']; // Ini adalah ID (Angka)

// Ambil Nama Kategori (Teks) untuk disimpan di kolom KategoriUser (jika masih diperlukan untuk kompatibilitas)
// Tapi idealnya kita hanya simpan KodeKategori. Untuk sekarang kita update dua-duanya agar aman.
$stmtKat = $conn->prepare("SELECT NamaKategoriUser FROM tblkategoriuser WHERE KodeKategori = ?");
$stmtKat->bind_param("i", $kategori);
$stmtKat->execute();
$resKat = $stmtKat->get_result()->fetch_assoc();
$namaKategori = $resKat['NamaKategoriUser'] ?? 'Unknown';

if ($kodeuser == "0") {
    // INSERT BARU
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    // Simpan ID Kategori ke kolom KodeKategori, dan Nama ke KategoriUser
    $sql = "INSERT INTO TblLogin (UserLogin, PassLogin, KodeKategori, KategoriUser) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssis", $userlogin, $hashed_password, $kategori, $namaKategori);
} else {
    // UPDATE
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE TblLogin SET UserLogin=?, PassLogin=?, KodeKategori=?, KategoriUser=? WHERE KodeUser=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisi", $userlogin, $hashed_password, $kategori, $namaKategori, $kodeuser);
    } else {
        $sql = "UPDATE TblLogin SET UserLogin=?, KodeKategori=?, KategoriUser=? WHERE KodeUser=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisi", $userlogin, $kategori, $namaKategori, $kodeuser);
    }
}

if ($stmt->execute()) echo json_encode(['success'=>true]);
else echo json_encode(['success'=>false, 'error'=>$stmt->error]);

$stmt->close();
$conn->close();
?>
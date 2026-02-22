<?php
// Matikan semua pesan error teks agar tidak merusak JSON
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json'); 

include __DIR__ . '/../Database/DBConnection.php';

try {
    if ($conn === null) throw new Exception("Koneksi database gagal.");
    if (!isset($_SESSION['username'])) throw new Exception("Sesi habis, login ulang.");

    // Ambil User ID
    $userLogin = $_SESSION['username'];
    $sqlUser = "SELECT KodeUser FROM tbllogin WHERE UserLogin = ?";
    $stmtUser = $conn->prepare($sqlUser);
    $stmtUser->bind_param("s", $userLogin);
    $stmtUser->execute();
    $resUser = $stmtUser->get_result()->fetch_assoc();
    $kodeUser = $resUser ? $resUser['KodeUser'] : 1;

    // Ambil Data Input
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) throw new Exception("Data tidak valid.");

    $conn->begin_transaction();

    $tanggal = date('Y-m-d');
    $jam = date('H:i:s');
    $tahun = date('Y');

    // Generate No Nota
    $sqlCek = "SELECT MAX(NoNota) as LastNo FROM tblpenjualankasir WHERE NoNota LIKE CONCAT('POS-', ?, '-','%')";
    $stmtCek = $conn->prepare($sqlCek);
    $stmtCek->bind_param("s", $tahun);
    $stmtCek->execute();
    $resCek = $stmtCek->get_result()->fetch_assoc();
    
    if ($resCek && $resCek['LastNo']) {
        $lastNo = intval(substr($resCek['LastNo'], -6));
        $noNota = 'POS-' . $tahun . '-' . str_pad($lastNo + 1, 6, '0', STR_PAD_LEFT);
    } else {
        $noNota = 'POS-' . $tahun . '-000001';
    }

    // Bersihkan Angka
    $subtotal = str_replace(',', '', $input['subtotal']);
    $diskon = 0;
    $grandtotal = str_replace(',', '', $input['grandtotal']);
    $bayar = str_replace(',', '', $input['bayar']);
    $kembali = str_replace(',', '', $input['kembali']);

    // Insert Header
    $sqlHeader = "INSERT INTO tblpenjualankasir (NoNota, Tanggal, Jam, SubTotal, Diskon, GrandTotal, Bayar, Kembali, KodeUser) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtHeader = $conn->prepare($sqlHeader);
    $stmtHeader->bind_param("ssssddddi", $noNota, $tanggal, $jam, $subtotal, $diskon, $grandtotal, $bayar, $kembali, $kodeUser);
    if (!$stmtHeader->execute()) throw new Exception("Gagal simpan header.");

    // Insert Detail & Update Stok
    $sqlDetail = "INSERT INTO tblpenjualankasirdetail (NoNota, idBarang, Jumlah, HargaSatuan, TotalHarga) VALUES (?, ?, ?, ?, ?)";
    $stmtDetail = $conn->prepare($sqlDetail);

    foreach ($input['detail'] as $item) {
        $jml = str_replace(',', '', $item['qty']);
        $hrg = str_replace(',', '', $item['harga']);
        $tot = $jml * $hrg;
        $idBarang = $item['id'];

        $stmtDetail->bind_param("siidd", $noNota, $idBarang, $jml, $hrg, $tot);
        if (!$stmtDetail->execute()) throw new Exception("Gagal simpan detail.");
        
        // Panggil Stored Procedure (spInsertKS)
        $idDetailTrans = $conn->insert_id;
        $ket = "POS: $noNota";
        $sqlSP = "CALL spInsertKS($idBarang, '$tanggal', $jml, 'KASIR', '$noNota', '$idDetailTrans', '$ket')";
        if (!$conn->query($sqlSP)) throw new Exception("Gagal update stok (SP).");
    }

    $conn->commit();
    echo json_encode(['success' => true, 'noNota' => $noNota]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
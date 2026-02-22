<?php
// FILE: Transaksi/ReturPenjualanCari.php
// VERSI LENGKAP (List + Sumber + Detail)

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

function sendError($msg) {
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

try {
    if (!file_exists(__DIR__ . '/../Database/DBConnection.php')) sendError("DBConnection tidak ketemu");
    include __DIR__ . '/../Database/DBConnection.php';

    $aksi = $_GET['aksi'] ?? '';

    // 1. LIST DATA UTAMA
    if ($aksi == 'list') {
        $q = "%" . ($_GET['q'] ?? '') . "%";
        $status = $_GET['status'] ?? '';
        
        // Cek kolom NoFaktur ada atau tidak (Biar tidak error di db lama)
        $cek = $conn->query("SHOW COLUMNS FROM tblreturpenjualan LIKE 'NoFaktur'");
        $adaNoFaktur = ($cek && $cek->num_rows > 0);
        
        $kolomFaktur = $adaNoFaktur ? ", r.NoFaktur" : ""; 

        $sql = "SELECT r.* $kolomFaktur, c.NamaCustomer 
                FROM tblreturpenjualan r
                LEFT JOIN tblcustomer c ON r.idCustomer = c.KodeCustomer
                WHERE (r.NoRetur LIKE ? OR c.NamaCustomer LIKE ?)";
        
        if($status != '') $sql .= " AND r.Status = '$status'";
        $sql .= " ORDER BY r.Tanggal DESC, r.NoRetur DESC LIMIT 50";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $q, $q);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $data = [];
        while($row = $res->fetch_assoc()) {
            // Fallback: Jika NoFaktur kosong, ambil dari catatan (untuk data lama)
            if(empty($row['NoFaktur']) && !empty($row['Catatan'])) {
                 $parts = explode('(', $row['Catatan']);
                 $row['NoFaktur'] = trim($parts[0]);
            }
            $data[] = $row;
        }
        echo json_encode($data);

    // 2. CARI SUMBER (Input Baru)
    } elseif ($aksi == 'get_sumber') {
        $kode = $_GET['kode'] ?? '';
        
        // A. Cek Surat Jalan
        $stmt = $conn->prepare("SELECT s.KodeSJ as NoFaktur, c.NamaCustomer, c.KodeCustomer FROM tblsuratjalan s LEFT JOIN tblcustomer c ON s.idCustomer=c.KodeCustomer WHERE s.KodeSJ = ?");
        $stmt->bind_param("s", $kode);
        $stmt->execute();
        $head = $stmt->get_result()->fetch_assoc();
        
        if($head) {
             // Ambil detail SJ
             $stmt2 = $conn->prepare("SELECT d.idBarang, b.NamaBarang, d.Jumlah, d.HargaJual FROM tblsuratjalandetailbarang d LEFT JOIN tblbarang b ON d.idBarang=b.KodeBarang WHERE d.idSJ = ?");
             $stmt2->bind_param("s", $kode);
             $stmt2->execute();
             echo json_encode(['success'=>true, 'header'=>$head, 'detail'=>$stmt2->get_result()->fetch_all(MYSQLI_ASSOC)]);
             exit;
        }

        // B. Cek Nota Kasir
        $stmt = $conn->prepare("SELECT NoNota as NoFaktur, 'Pelanggan Umum' as NamaCustomer, 'UMUM' as KodeCustomer FROM tblpenjualankasir WHERE NoNota = ?");
        $stmt->bind_param("s", $kode);
        $stmt->execute();
        $head = $stmt->get_result()->fetch_assoc();

        if($head) {
             // Ambil detail Kasir
             $stmt2 = $conn->prepare("SELECT d.idBarang, b.NamaBarang, d.Jumlah, d.HargaSatuan as HargaJual FROM tblpenjualankasirdetail d LEFT JOIN tblbarang b ON d.idBarang=b.KodeBarang WHERE d.NoNota = ?");
             $stmt2->bind_param("s", $kode);
             $stmt2->execute();
             echo json_encode(['success'=>true, 'header'=>$head, 'detail'=>$stmt2->get_result()->fetch_all(MYSQLI_ASSOC)]);
             exit;
        }
        
        sendError("Dokumen tidak ditemukan.");

    // 3. LIHAT DETAIL (YANG SUDAH DISIMPAN) <-- INI YANG BARU
    } elseif ($aksi == 'get_detail_saved') {
        $noRetur = $_GET['no'] ?? '';
        
        // Header
        $sqlH = "SELECT r.*, c.NamaCustomer FROM tblreturpenjualan r LEFT JOIN tblcustomer c ON r.idCustomer=c.KodeCustomer WHERE r.NoRetur = ?";
        $stmt = $conn->prepare($sqlH);
        $stmt->bind_param("s", $noRetur);
        $stmt->execute();
        $head = $stmt->get_result()->fetch_assoc();

        // Detail
        $sqlD = "SELECT d.idBarang, b.NamaBarang, d.Jumlah, d.Alasan 
                 FROM tblreturpenjualandetail d 
                 LEFT JOIN tblbarang b ON d.idBarang = b.KodeBarang 
                 WHERE d.NoRetur = ?";
        $stmt2 = $conn->prepare($sqlD);
        $stmt2->bind_param("s", $noRetur);
        $stmt2->execute();
        
        echo json_encode([
            'success' => true,
            'header' => $head,
            'detail' => $stmt2->get_result()->fetch_all(MYSQLI_ASSOC)
        ]);

    } else {
        sendError("Aksi salah.");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
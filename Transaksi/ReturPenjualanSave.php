<?php
// FILE: Transaksi/ReturPenjualanSave.php
// Versi: FIX KASIR (Tanpa Cek Database yang bikin error)

// 1. TAHAN OUTPUT
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
session_start();

function sendResponse($success, $msg) {
    ob_end_clean(); 
    echo json_encode(['success' => $success, 'error' => $success ? '' : $msg]);
    exit;
}

function generateNoRetur($conn) {
    $prefix = "RJ-" . date("Ym") . "-";
    $sql = "SELECT NoRetur FROM tblreturpenjualan WHERE NoRetur LIKE '$prefix%' ORDER BY NoRetur DESC LIMIT 1";
    $res = $conn->query($sql);
    $lastNo = ($res && $row = $res->fetch_assoc()) ? (int)substr($row['NoRetur'], -6) + 1 : 1;
    return $prefix . str_pad($lastNo, 6, "0", STR_PAD_LEFT);
}

try {
    // 2. KONEKSI
    $pathDB = __DIR__ . '/../Database/DBConnection.php';
    if (!file_exists($pathDB)) throw new Exception("File Database tidak ditemukan.");
    include $pathDB;

    // 3. USER
    $kodeUser = $_SESSION['kodeuser'] ?? 1;

    // 4. BACA INPUT
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);
    if (!$data) throw new Exception("Data tidak valid.");
    
    $act = $data['act'] ?? '';

    // ==========================================================
    // AKSI: SIMPAN
    // ==========================================================
    if ($act == 'save') {
        
        $tanggal  = $data['tanggal'];
        $customer = $data['customer'];
        $noFaktur = $data['NoFaktur'] ?? '-'; 
        $catatan  = $data['catatan'] ?? '-';
        $detail   = $data['detail'] ?? [];

        if (empty($detail)) throw new Exception("Tidak ada barang dipilih.");

        $conn->begin_transaction();

        try {
            // --- LOGIKA STATUS (FIXED) ---
            // Default: Kasir = PENDING
            $statusFinal = 'PENDING';
            $approvedBy  = NULL;

            // Cek Session saja (Jangan cek Database biar gak error kolom hilang)
            // Jika user login sebagai Admin/Owner, session ini biasanya ada isinya 1
            if (isset($_SESSION['hak_akses']['Retur']['Special']) && $_SESSION['hak_akses']['Retur']['Special'] == 1) {
                $statusFinal = 'APPROVED';
                $approvedBy  = $kodeUser;
            } 
            
            // Generate No Retur
            $noRetur = generateNoRetur($conn);

            // Cek Kolom NoFaktur (Antisipasi database lama)
            $cekKolom = $conn->query("SHOW COLUMNS FROM tblreturpenjualan LIKE 'NoFaktur'");
            $hasNoFaktur = ($cekKolom && $cekKolom->num_rows > 0);

            if ($hasNoFaktur) {
                $sqlHead = "INSERT INTO tblreturpenjualan (NoRetur, Tanggal, idCustomer, NoFaktur, Catatan, Status, ApprovedBy, KodeUser, TotalRetur) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
                $stmt = $conn->prepare($sqlHead);
                $stmt->bind_param("ssssssii", $noRetur, $tanggal, $customer, $noFaktur, $catatan, $statusFinal, $approvedBy, $kodeUser);
            } else {
                // Fallback jika kolom NoFaktur belum dibuat
                $catatanFull = "Ref: $noFaktur. " . $catatan;
                $sqlHead = "INSERT INTO tblreturpenjualan (NoRetur, Tanggal, idCustomer, Catatan, Status, ApprovedBy, KodeUser, TotalRetur) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 0)";
                $stmt = $conn->prepare($sqlHead);
                $stmt->bind_param("sssssii", $noRetur, $tanggal, $customer, $catatanFull, $statusFinal, $approvedBy, $kodeUser);
            }
            
            if (!$stmt->execute()) throw new Exception("Gagal Header: " . $stmt->error);

            // Simpan Detail
            $stmtDet = $conn->prepare("INSERT INTO tblreturpenjualandetail (NoRetur, idBarang, Jumlah, Alasan) VALUES (?, ?, ?, ?)");
            $stmtKS = $conn->prepare("INSERT INTO tblkartustok (idBarang, Tanggal, Masuk, Keluar, JenisTransaksi, idTransaksi, idDetailTransaksi, CatatanKS, Fisik) VALUES (?, ?, ?, 0, 'RETUR', ?, ?, ?, ?)");

            $totalNilai = 0;

            foreach ($detail as $item) {
                $idBrg = $item['id'];
                $qty   = $item['qty'];
                $harga = $item['harga'];
                $totalNilai += ($qty * $harga);

                // Insert Detail
                $stmtDet->bind_param("ssis", $noRetur, $idBrg, $qty, $catatan);
                $stmtDet->execute();
                $idDetailAuto = $conn->insert_id;

                // Insert Kartu Stok (HANYA JIKA APPROVED/ADMIN)
                if ($statusFinal == 'APPROVED') {
                    $ketKS = "Retur Jual: $noFaktur";
                    $stmtKS->bind_param("ssiisis", $idBrg, $tanggal, $qty, $noRetur, $idDetailAuto, $ketKS, $qty);
                    $stmtKS->execute();

                    $conn->query("UPDATE tblbarang SET Jumlah = Jumlah + $qty WHERE KodeBarang = '$idBrg'");
                }
            }

            // Update Total
            $conn->query("UPDATE tblreturpenjualan SET TotalRetur = $totalNilai WHERE NoRetur = '$noRetur'");

            $conn->commit();
            
            $msg = ($statusFinal == 'APPROVED') 
                 ? "Retur BERHASIL Disimpan & Stok Bertambah." 
                 : "Retur BERHASIL Disimpan (Status PENDING). Menunggu Approval Admin.";
                 
            sendResponse(true, $msg);

        } catch (Exception $ex) {
            $conn->rollback();
            throw $ex;
        }

    // ==========================================================
    // AKSI: APPROVE / REJECT
    // ==========================================================
    } elseif ($act == 'approve' || $act == 'reject') {
        
        $noRetur = $data['no_retur'];
        $statusBaru = ($act == 'approve') ? 'APPROVED' : 'REJECTED';
        
        // Cek Status Lama
        $cek = $conn->query("SELECT Status FROM tblreturpenjualan WHERE NoRetur='$noRetur'");
        $row = $cek->fetch_assoc();
        
        if ($row['Status'] == 'APPROVED') {
            sendResponse(false, "Data ini sudah disetujui sebelumnya.");
        }

        $conn->begin_transaction();
        try {
            $conn->query("UPDATE tblreturpenjualan SET Status='$statusBaru', ApprovedBy='$kodeUser' WHERE NoRetur='$noRetur'");

            if ($statusBaru == 'APPROVED') {
                $qDet = $conn->query("SELECT * FROM tblreturpenjualandetail WHERE NoRetur='$noRetur'");
                while ($d = $qDet->fetch_assoc()) {
                    $idBrg = $d['idBarang'];
                    $qty   = $d['Jumlah'];
                    
                    $ketKS = "Retur Jual (Approved): $noRetur";
                    $conn->query("INSERT INTO tblkartustok (idBarang, Tanggal, Masuk, Keluar, JenisTransaksi, idTransaksi, CatatanKS, Fisik) 
                                  VALUES ('$idBrg', NOW(), $qty, 0, 'RETUR', '$noRetur', '$ketKS', $qty)");
                    
                    $conn->query("UPDATE tblbarang SET Jumlah = Jumlah + $qty WHERE KodeBarang = '$idBrg'");
                }
            }

            $conn->commit();
            sendResponse(true, "Status berhasil diubah menjadi $statusBaru");

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } else {
        throw new Exception("Aksi tidak dikenal.");
    }

} catch (Exception $e) {
    sendResponse(false, "System Error: " . $e->getMessage());
}
?>
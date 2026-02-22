<?php
// Sambungkan ke database
include __DIR__ . '/../Database/DBConnection.php';
include __DIR__ . '/../Database/UserLoginAuth.php';

header('Content-Type: application/json');

// Ambil data JSON
$data = json_decode(file_get_contents("php://input"), true);
if ($data === null) {
    echo json_encode(['success' => false, 'error' => 'Data JSON tidak valid']);
    exit;
}

// Tentukan Aksi: Jika tidak ada 'act', anggap 'save' (untuk kompatibilitas)
$act = $data['act'] ?? 'save'; 
$kodeso = $data['kodeso'] ?? '';

if ($conn === null) {
    echo json_encode(['success' => false, 'error' => 'Koneksi database gagal']);
    exit;
}

// ============================================================================
// 1. VALIDASI PENGUNCIAN (LOCKING) - Berlaku untuk Simpan & Hapus
// ============================================================================
if (!empty($kodeso)) {
    // Cek apakah SO sudah diproses jadi SJ
    $cekLock = $conn->query("SELECT FuncSOgetTotalSJ('$kodeso') AS TotalKirim");
    $rowLock = $cekLock->fetch_assoc();

    if ($rowLock['TotalKirim'] > 0) {
        echo json_encode(['success' => false, 'error' => 'AKSES DITOLAK: SO ini sudah diproses (Surat Jalan sudah terbit). Data tidak bisa diubah/dihapus.']);
        exit;
    }
}

// Mulai Transaksi
$conn->begin_transaction();

try {
    // ========================================================================
    // LOGIKA HAPUS (DELETE)
    // ========================================================================
    if ($act == 'delete') {
        if (empty($kodeso)) throw new Exception("Kode SO tidak boleh kosong untuk penghapusan.");

        // Hapus Detail (Trigger di DB akan mengembalikan stok barang secara otomatis)
        $sqlDelDet = "DELETE FROM TblSODetailBarang WHERE idSO = ?";
        $stmtDelDet = $conn->prepare($sqlDelDet);
        $stmtDelDet->bind_param("s", $kodeso);
        if (!$stmtDelDet->execute()) throw new Exception("Gagal hapus detail: " . $stmtDelDet->error);
        
        // Hapus Header (Soft Delete / Set Aktif=0 sesuai pola di POSave)
        // Atau Hard Delete: "DELETE FROM TblSO WHERE KodeSO = ?"
        // Disini saya pakai Soft Delete agar konsisten dengan POSave.php baris 111
        $sqlDelHead = "UPDATE TblSO SET Aktif='0' WHERE KodeSO = ?"; 
        $stmtDelHead = $conn->prepare($sqlDelHead);
        $stmtDelHead->bind_param("s", $kodeso);
        if (!$stmtDelHead->execute()) throw new Exception("Gagal hapus header: " . $stmtDelHead->error);

    } 
    // ========================================================================
    // LOGIKA SIMPAN (SAVE / UPDATE)
    // ========================================================================
    else {
        // Ambil variabel
        $kodecustomer = $data['kodecustomer'];
        $tanggal = $data['tanggal'];
        $catatan = $data['catatan'];
        $totalbarang = $data['totalbarang'];
        $detailBarang = $data['detailbarang'];
        
        // Bersihkan angka
        $subtotal = str_replace(',', '', $data['subtotal']);
        $ppn = str_replace(',', '', $data['ppn']);
        $grandtotal = str_replace(',', '', $data['grandtotal']);
        
        // Hitung PPN Rupiah
        $ppnrp = 0;
        if (is_numeric($grandtotal) && is_numeric($subtotal)) {
            $ppnrp = $grandtotal - $subtotal;
        }

        if ($kodeso == "") {
            // --- INSERT BARU ---
            $auth = new Auth($conn);
            $kodeso = $auth->GetNotaNo($tanggal, 'SO');   
            $sql = "INSERT INTO TblSO (KodeSO,Tanggal,idCustomer,JumlahBarang,SubTotal,PPN,PPNRp,Diskon,GrandTotal,Catatan,Aktif) VALUES (?,?,?,?,?,?,?,0,?,?,'1')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssss", $kodeso, $tanggal, $kodecustomer, $totalbarang, $subtotal, $ppn, $ppnrp, $grandtotal, $catatan);
        } else {
            // --- UPDATE ---
            $sql = "UPDATE TblSO SET Tanggal=?,idCustomer=?,JumlahBarang=?,SubTotal=?,PPN=?,PPNRp=?,GrandTotal=?,Catatan=? WHERE KodeSO=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssss", $tanggal, $kodecustomer, $totalbarang, $subtotal, $ppn, $ppnrp, $grandtotal, $catatan, $kodeso);
            
            // Hapus detail yang tidak ada di list (Smart Delete)
            $daftarDetSO = [];
            foreach ($detailBarang as $item) {
                if (!empty($item['iddetailso'])) $daftarDetSO[] = $item['iddetailso'];
            }

            if (!empty($daftarDetSO)) {   
                $quoted = array_map(function ($id) use ($conn) { return "'" . $conn->real_escape_string($id) . "'"; }, $daftarDetSO);
                $sql_delete = "DELETE FROM TblSODetailBarang WHERE idSO=? AND AutoNum NOT IN (".implode(',', $quoted).')';
                $stmt_del = $conn->prepare($sql_delete);
                $stmt_del->bind_param("s", $kodeso);
                $stmt_del->execute();
            } else {
                // Jika detail kosong semua, hapus semua detail lama
                $stmt_del = $conn->prepare("DELETE FROM TblSODetailBarang WHERE idSO=?");
                $stmt_del->bind_param("s", $kodeso);
                $stmt_del->execute();
            }
        }

        if (!$stmt->execute()) throw new Exception("Gagal simpan header: " . $stmt->error);

        // --- SIMPAN DETAIL ---
        $sql_insert = "INSERT INTO TblSODetailBarang (idBarang,idSO,JumlahDetSO,HargaJual,TotalHarga,JumlahSJ) VALUES (?,?,?,?,?,0)";
        $stmt2 = $conn->prepare($sql_insert);

        $sql_update = "UPDATE TblSODetailBarang SET idBarang=?,JumlahDetSO=?,HargaJual=?,TotalHarga=? WHERE AutoNum=?";
        $stmt3 = $conn->prepare($sql_update);

        foreach ($detailBarang as $item) {
            $jml = $item['jumlah'];
            $hrg = $item['harga'];
            $tot = $item['total'];

            if (!empty($item['iddetailso'])) { 
                $stmt3->bind_param("sssss", $item["kodebarang"], $jml, $hrg, $tot, $item["iddetailso"]);
                if (!$stmt3->execute()) throw new Exception("Gagal update detail: " . $stmt3->error);
            } else { 
                $stmt2->bind_param("sssss", $item['kodebarang'], $kodeso, $jml, $hrg, $tot);
                if (!$stmt2->execute()) throw new Exception("Gagal insert detail: " . $stmt2->error);
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
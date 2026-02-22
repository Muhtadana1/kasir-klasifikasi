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

$act = $data['act'] ?? 'save';
$idsj = $data['idsj'] ?? ''; // ID Surat Jalan

if ($conn === null) {
    echo json_encode(['success' => false, 'error' => 'Koneksi gagal']);
    exit;
}

$conn->begin_transaction();

try {
    // ========================================================================
    // LOGIKA HAPUS (DELETE)
    // ========================================================================
    if ($act == 'delete') {
        if (empty($idsj)) throw new Exception("Kode SJ tidak boleh kosong.");

        // 1. Hapus Detail
        // (Trigger 'trSJDetailAfterDelete' di database akan otomatis:
        //  - Mengurangi JumlahKirim di TblSODetailBarang
        //  - Menghapus kartu stok / Mengembalikan stok fisik)
        $sqlDelDet = "DELETE FROM TblSuratJalanDetailBarang WHERE idSJ = ?";
        $stmtDelDet = $conn->prepare($sqlDelDet);
        $stmtDelDet->bind_param("s", $idsj);
        if (!$stmtDelDet->execute()) throw new Exception("Gagal hapus detail: " . $stmtDelDet->error);

        // 2. Hapus Header (Soft Delete)
        $sqlDelHead = "UPDATE TblSuratJalan SET Aktif='0' WHERE KodeSJ = ?";
        $stmtDelHead = $conn->prepare($sqlDelHead);
        $stmtDelHead->bind_param("s", $idsj);
        if (!$stmtDelHead->execute()) throw new Exception("Gagal hapus header: " . $stmtDelHead->error);

    }
    // ========================================================================
    // LOGIKA SIMPAN (SAVE / UPDATE)
    // ========================================================================
    else {
        $kodecustomer = $data['kodecustomer'];
        $tanggal = $data['tanggal'];
        $kodeso = $data['kodeso'];
        $catatan = $data['catatan'];
        $totalbarang = $data['totalbarang'];
        $detailBarang = $data['detailbarang'];
        
        // Bersihkan angka dari koma
        $subtotal = str_replace(',', '', $data['subtotal']);
        $ppn = str_replace(',', '', $data['ppn']);
        $grandtotal = str_replace(',', '', $data['grandtotal']);
        $ppnrp = ($grandtotal && $subtotal) ? ($grandtotal - $subtotal) : 0;

        if ($idsj == "") {
            // INSERT BARU
            $auth = new Auth($conn);
            $idsj = $auth->GetNotaNo($tanggal, 'SJ');    
            $sql = "INSERT INTO TblSuratJalan (KodeSJ,Tanggal,idCustomer,JumlahBarang,SubTotal,PPN,PPNRp,Diskon,GrandTotal,Catatan,idSO,Aktif) VALUES (?,?,?,?,?,?,?,0,?,?,?,'1')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssss", $idsj, $tanggal, $kodecustomer, $totalbarang, $subtotal, $ppn, $ppnrp, $grandtotal, $catatan, $kodeso);
        } else {   
            // UPDATE
            $sql = "UPDATE TblSuratJalan SET Tanggal=?,idCustomer=?,JumlahBarang=?,SubTotal=?,PPN=?,PPNRp=?,GrandTotal=?,Catatan=? WHERE KodeSJ=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssss", $tanggal, $kodecustomer, $totalbarang, $subtotal, $ppn, $ppnrp, $grandtotal, $catatan, $idsj);
            
            // Hapus detail lama (Trigger akan handle stok reversal)
            $sqlDel = "DELETE FROM TblSuratJalanDetailBarang WHERE idSJ=?";
            $stmt2 = $conn->prepare($sqlDel);
            $stmt2->bind_param("s", $idsj);
            $stmt2->execute();   
            $stmt2->close();
        }

        if (!$stmt->execute()) throw new Exception("Gagal simpan header: " . $stmt->error);
        $stmt->close();

        // INSERT DETAIL BARU
        $sqlIns = "INSERT INTO TblSuratJalanDetailBarang (idSJ,idBarang,Jumlah,HargaJual,TotalHarga,idDetailSO) VALUES (?,?,?,?,?,?)";
        $stmt3 = $conn->prepare($sqlIns);

        foreach ($detailBarang as $item) {
            // Pastikan data bersih
            $jml = str_replace(',', '', $item['jumlah']);
            $hrg = str_replace(',', '', $item['harga']);
            $tot = str_replace(',', '', $item['total']);
            
            $stmt3->bind_param("ssssss", $idsj, $item['kodebarang'], $jml, $hrg, $tot, $item['iddetailso']);
            if (!$stmt3->execute()) throw new Exception("Gagal simpan detail: " . $stmt3->error);
        }
        $stmt3->close();
    }

    $conn->commit();
    echo json_encode(['success' => true]);    

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);    
}

$conn->close();
?>
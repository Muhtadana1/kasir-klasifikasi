<?php
// Sambungkan ke database
include __DIR__ . '/../Database/DBConnection.php';
include __DIR__ . '/../Database/UserLoginAuth.php';
if ($conn === null) {
    echo json_encode(['success' => false, 'error' => 'koneksi gagal']);
    exit;
}

// Ambil data JSON dari request
$data = json_decode(file_get_contents("php://input"), true);

if ($data === null) {
    echo json_encode(['success' => false, 'error' => 'Data PO tidak valid !']);
    exit;
}

$act = $data['act'];
$kodepo = $data['kodepo'];
if (!empty($kodepo)) {
    $cekLock = $conn->query("SELECT FuncPOGetTotalBL('$kodepo') AS TotalTerima");
    $rowLock = $cekLock->fetch_assoc();

    if ($rowLock['TotalTerima'] > 0) {
        throw new Exception("AKSES DITOLAK: PO ini sudah diproses. Data tidak bisa diubah atau dihapus.");
    }
}
if ($act == 'save') {
    // Ambil nilai dari data
    $kodesupplier = $data['kodesupplier'];
    $tanggal = $data['tanggal'];
    $subtotal = $data['subtotal'];
    $ppn = $data['ppn'];
    $grandtotal = $data['grandtotal'];
    $catatan = $data['catatan'];
    $totalbarang = $data['totalbarang'];
    $detailBarang = $data['detailbarang'];
    $ppnrp = $grandtotal - $subtotal;

    // Simpan data ke database
    if ($kodepo == "") {
        $auth = new Auth($conn);
        $kodepo = $auth->GetNotaNo($tanggal, 'PO');
        $sql = "INSERT INTO TblPO (KodePO,Tanggal,idSupplier,TotalBarang,SubTotal,PPN,PPNRp,Diskon,GrandTotal,Catatan,Aktif) VALUES (?,?,?,?,?,?,?,0,?,?,'1')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssss", $kodepo, $tanggal, $kodesupplier, $totalbarang, $subtotal, $ppn, $ppnrp, $grandtotal, $catatan);
    } else {

        $sql = "UPDATE TblPO SET Tanggal=?,idSupplier=?,TotalBarang=?,SubTotal=?,PPN=?,PPNRp=?,GrandTotal=?,Catatan=?  WHERE KodePO=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssss", $tanggal, $kodesupplier, $totalbarang, $subtotal, $ppn, $ppnrp, $grandtotal, $catatan, $kodepo);


        $daftarDetPO = [];
        foreach ($detailBarang as $item) {
            if (!empty($item['iddetailpo'])) {
                $daftarDetPO[] = $item['iddetailpo'];
            }
        }

        if (!empty($daftarDetPO)) {
            // Escape dan quote setiap id
            $quoted = array_map(function ($id) use ($conn) {
                return "'" . $conn->real_escape_string($id) . "'";
            }, $daftarDetPO);

            $sql = "DELETE FROM TblPODetailBarang WHERE idPO=? AND AutoNumDetPO NOT IN (" . implode(',', $quoted) . ")";
            $stmt2 = $conn->prepare($sql);
            $stmt2->bind_param("s", $kodepo);
            $stmt2->execute();
            $stmt2->close();
        }



    }

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
        $stmt->close();
        $conn->close();
        return;
    }

    $sql = "UPDATE TblPODetailBarang SET idBarang=?,Jumlahbeli=?,HargaBeliDetPO=?,TotalHarga=? WHERE AutoNumDetPO=?;";
    $stmt3 = $conn->prepare($sql);

    $sql = "INSERT INTO TblPODetailBarang (idBarang,idPO,JumlahBeli,HargaBeliDetPO,TotalHarga,JumlahDiterima) VALUES (?,?,?,?,?,0);";
    $stmt2 = $conn->prepare($sql);

    foreach ($detailBarang as $item) {
        if (!$item['iddetailpo'] == "") {
            $stmt3->bind_param("sssss", $item["kodebarang"], $item["jumlah"], $item["harga"], $item["total"], $item["iddetailpo"]);
            if (!$stmt3->execute()) {
                echo json_encode(['success' => false, 'error' => $stmt3->error]);
                return;
            }
        } else {
            $stmt2->bind_param("sssss", $item['kodebarang'], $kodepo, $item['jumlah'], $item['harga'], $item['total']);
            if (!$stmt2->execute()) {
                echo json_encode(['success' => false, 'error' => $stmt2->error]);
                return;
            }
        }
    }
    $stmt3->close();
    $stmt2->close();
    $stmt->close();
}
if ($act == 'delete') {
    $sql = "UPDATE TblPO SET Aktif='0'  WHERE KodePO=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $kodepo);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
        $stmt->close();
        $conn->close();
        return;
    }
}
$conn->close();
echo json_encode(['success' => true]);
?>

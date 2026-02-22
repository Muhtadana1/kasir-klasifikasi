<?php

include __DIR__ . '/../Database/DBConnection.php';
if ($conn === null) {
    echo json_encode(['success' => false, 'error' => 'koneksi gagal']);
    exit;
}

if (isset($_GET['abt'])) {

    if ($_GET['abt'] == "po") {
        $cari = isset($_GET['po']) ? $_GET['po'] : '';
        $sql = "SELECT * FROM viewDaftarPO WHERE Aktif='1' AND JumlahBL<TotalBarang AND KodePO LIKE ? ORDER BY KodePO ASC";
        $stmt = $conn->prepare($sql);
        $search = "%$cari%";
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $output = '';
            $output .= '<li style="background-color=blue;" class="list-group-item d-flex">';
            $output .= '<span class="col">KODE PO</span>';
            $output .= '<span class="col">TANGGAL</span>';
            $output .= '<span class="col">SUPPLIER</span>';
            $output .= '<span class="col">GRAND TOTAL</span>';
            $output .= '</li>';
            while ($row = $result->fetch_assoc()) {
                $output .= '<li style="background-color=blue;" class="list-group-item d-flex" ondblclick="PilihPO(' . '\'' . $row['idSupplier'] . '\',' . '\'' . $row['NamaSupplier'] . '\',' . '\'' . $row['KodePO'] . '\',' . '\'' . number_format($row['SubTotal'], 0, '.', ',') . '\',' . '\'' . number_format($row['PPN'], 0, '.', ',') . '\',' . '\'' . number_format($row['GrandTotal'], 0, '.', ',') . '\')">';
                $output .= '<span class="col">' . htmlspecialchars($row['KodePO']) . '</span>';
                $output .= '<span class="col">' . htmlspecialchars($row['Tanggal']) . '</span>';
                
                // =================================================================
                // TANDA LOKASI PERUBAHAN #1 (FIX ERROR TYPO)
                // =================================================================
                // Mengganti 'KodeSupplier' menjadi 'idSupplier' agar cocok dengan viewDaftarPO
                $output .= '<span class="col">' . htmlspecialchars($row['NamaSupplier']) . '<input type="hidden" value="' . htmlspecialchars($row['idSupplier']) . '"></span>';
                // =================================================================
                // AKHIR DARI LOKASI PERUBAHAN #1
                // =================================================================

                $output .= '<span class="col">' . number_format($row['GrandTotal'], 0, '.', ',') . '</span>';                
                $output .= '</li>';
            }
            echo $output;
        } else {
            $output .= '<li class="list-group-item d-flex">';
            $output .= '<span class="col">Data tidak ditemukan !</span>';
            $output .= '<span class="col"></span>';
            $output .= '<span class="col"></span>';
            $output .= '</li>';
            echo $output;
        }
    } else if ($_GET['abt'] == "brng") {
        $cari = isset($_GET['brng']) ? $_GET['brng'] : '';
        $id = isset($_GET['id']) ? $_GET['id'] : '';
        $sql = "SELECT KodeBarang,NamaBarang,NamaKategori,Satuan,HargaBeli FROM viewBarang WHERE (NamaBarang LIKE ? OR NamaKategori LIKE ? ) ORDER BY NamaBarang";
        $stmt = $conn->prepare($sql);
        $search = "%$cari%";
        $stmt->bind_param("ss", $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $output = '';
            while ($row = $result->fetch_assoc()) {
                $output .= '<li class="list-group-item d-flex" ondblclick="PilihBarang(' . '\'' . $row['KodeBarang'] . '\',' . '\'' . $row['NamaBarang'] . '\',' . '\'' . $row['Satuan'] . '\',' . '\'' . $row['NamaKategori'] . '\',' . '\'' . $id . '\',' . '\'' . $row['HargaBeli']. '\' )">';
                $output .= '<span class="col">' . htmlspecialchars($row['NamaBarang']) . '<input type="hidden" value="' . htmlspecialchars($row['KodeBarang']) . '"></span>';
                $output .= '<span class="col">' . htmlspecialchars($row['NamaKategori']) . '</span>';
                $output .= '<span class="col">' . htmlspecialchars($row['Satuan']) . '</span>';
                $output .= '</li>';
            }
            echo $output;
        } else {            
            echo 'EOF';
        }
    } else if ($_GET['abt'] == "bldt") {        
        $kodebl = isset($_GET['kodebl']) ? $_GET['kodebl'] : '';
        $sql = "SELECT * FROM viewDaftarBLDetailBarang WHERE idPenerimaan=? ORDER BY AutoNum";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $kodebl);
        $stmt->execute();
        $result = $stmt->get_result();
        $output = [];
        if ($result->num_rows > 0) {
            
            while ($row = $result->fetch_assoc()) {
                $output[] = $row;
            }
            $response = ['status' => 'success', 'data' => $output];
        } else{
            $response = ['status' => 'empty', 'data' => []];
        }
        header('Content-Type: application/json');
        echo json_encode($response);
    } else if ($_GET['abt'] == "cari") {
        
        // =================================================================
        // TANDA LOKASI PERUBAHAN #2 (FIX SQL INJECTION)
        // =================================================================
        $kodepo = isset($_GET['cari']) ? $_GET['cari'] : '';
        $searchTerm = "%" . $kodepo . "%";
        
        // Menggunakan prepared statement untuk keamanan
        $sql = "SELECT * FROM viewDaftarBL WHERE KodePenerimaan LIKE ? ORDER BY KodePenerimaan LIMIT 500";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        // =================================================================
        // AKHIR DARI LOKASI PERUBAHAN #2
        // =================================================================

        $output = '';
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $output .= '<tr style="height:30px" ondblclick="fillform('
                    . '\'' . $row['KodePenerimaan'] . '\','
                    . '\'' . $row['idPO'] . '\','
                    . '\'' . $row['Tanggal'] . '\','
                    . '\'' . $row['idSupplier'] . '\','
                    . '\'' . $row['NamaSupplier'] . '\','
                    . '\'' . $row['Catatan'] . '\','
                    . '\'' . number_format($row['SubTotal'], 0, '.', ',') . '\','
                    . '\'' . number_format($row['PPN'], 0, '.', ',') . '\','
                    . '\'' . number_format($row['GrandTotal'], 0, '.', ',') . '\')">';
                $output .= '<td style="width: 125px;" align="center">' . $row['KodePenerimaan'] . '</td>';
                $output .= '<td align="center">' . date('d F Y', strtotime($row['Tanggal'])) . '</td>';
                $output .= '<td style="width: 350px;">' . $row['NamaSupplier'] . '</td>';
                $output .= '<td align="right">' . number_format($row['TotalBarang'], 0, ',', '.') . '</td>';
                $output .= '<td align="right">' . number_format($row['SubTotal'], 0, ',', '.') . '</td>';
                $output .= '<td align="right">' . number_format($row['Diskon'], 0, '.', ',') . '</td>';
                $output .= '<td align="right">' . number_format($row['PPNRp'], 0, ',', ',') . '</td>';
                $output .= '<td align="right">' . number_format($row['GrandTotal'], 0, '.', ',') . '</td>';
                $output .= '<td align="center">' . $row['idPO'] . '</td>';
                $output .= '</tr>';                
            }
            echo $output;
        } else {
            echo '<tr><td colspan="9">Data tidak ditemukan !</td></tr>';
        }
       
    }
}
?>
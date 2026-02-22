
<?php

include __DIR__ . '/../Database/DBConnection.php';
if ($conn === null) {
    echo json_encode(['success' => false, 'error' => 'koneksi gagal']);
    exit;
}

if (isset($_GET['abt'])) {

    if ($_GET['abt'] == "so") {
        $cari = isset($_GET['so']) ? $_GET['so'] : '';
        $sql = "SELECT * FROM viewDaftarSO WHERE Aktif='1' AND JumlahKirim<JumlahBarang AND KodeSO LIKE ? ORDER BY KodeSO ASC";
        $stmt = $conn->prepare($sql);
        $search = "%$cari%";
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $output = '';
            $output .= '<li style="background-color=blue;" class="list-group-item d-flex">';
            $output .= '<span class="col">KODE SO</span>';
            $output .= '<span class="col">TANGGAL</span>';
            $output .= '<span class="col">CUSTOMER</span>';
            $output .= '<span class="col">GRAND TOTAL</span>';
            $output .= '</li>';
            while ($row = $result->fetch_assoc()) {
                $output .= '<li style="background-color=blue;" class="list-group-item d-flex" ondblclick="PilihSO(' . '\'' . $row['idCustomer'] . '\',' . '\'' . $row['NamaCustomer'] . '\',' . '\'' . $row['KodeSO'] . '\',' . '\'' . number_format($row['SubTotal'], 0, '.', ',') . '\',' . '\'' . number_format($row['PPN'], 0, '.', ',') . '\',' . '\'' . number_format($row['GrandTotal'], 0, '.', ',') . '\')">';
                $output .= '<span class="col">' . htmlspecialchars($row['KodeSO']) . '</span>';
                $output .= '<span class="col">' . htmlspecialchars($row['Tanggal']) . '</span>';
                $output .= '<span class="col">' . htmlspecialchars($row['NamaCustomer']) . '<input type="hidden" value="' . htmlspecialchars($row['idCustomer']) . '"></span>';
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
    } else if ($_GET['abt'] == "sjdt") {        
        $kodesj = isset($_GET['kodesj']) ? $_GET['kodesj'] : '';
        $sql = "SELECT * FROM viewdaftarsjdetailbarang WHERE idSJ=? ORDER BY AutoNum";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $kodesj);
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
        $kodesj = isset($_GET['cari']) ? $_GET['cari'] : '';
        $sql = "SELECT * FROM viewDaftarSJ WHERE KodeSJ LIKE '%$kodesj%' ORDER BY KodeSJ LIMIT 500";
        $result = $conn->query($sql);
        $output = '';
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $output .= '<tr style="height:30px" ondblclick="fillform('
                    . '\'' . $row['KodeSJ'] . '\','
                    . '\'' . $row['idSO'] . '\','
                    . '\'' . $row['Tanggal'] . '\','
                    . '\'' . $row['idCustoemr'] . '\','
                    . '\'' . $row['NamaCustomer'] . '\','
                    . '\'' . $row['Catatan'] . '\','
                    . '\'' . number_format($row['SubTotal'], 0, '.', ',') . '\','
                    . '\'' . number_format($row['PPN'], 0, '.', ',') . '\','
                    . '\'' . number_format($row['GrandTotal'], 0, '.', ',') . '\')">';
                $output .= '<td style="width: 125px;" align="center">' . $row['KodeSJ'] . '</td>';
                $output .= '<td align="center">' . date('d F Y', strtotime($row['Tanggal'])) . '</td>';
                $output .= '<td style="width: 350px;">' . $row['NamaCustomer'] . '</td>';
                $output .= '<td align="right">' . number_format($row['JumlahBarang'], 0, ',', '.') . '</td>';
                $output .= '<td align="right">' . number_format($row['SubTotal'], 0, '.', ',') . '</td>';
                $output .= '<td align="right">' . number_format($row['Diskon'], 0, '.', ',') . '</td>';
                $output .= '<td align="right">' . number_format($row['PPNRp'], 0, '.', ',') . '</td>';
                $output .= '<td align="right">' . number_format($row['GrandTotal'], 0, '.', ',') . '</td>';
                $output .= '<td align="center">' . $row['idSO'] . '</td>';
                $output .= '</tr>';                
            }
            echo $output;
           // echo 'ABC';
        } else {
            echo '<tr><td colspan="9">Data tidak ditemukan !</td></tr>';
        }
       
    }
}
?>
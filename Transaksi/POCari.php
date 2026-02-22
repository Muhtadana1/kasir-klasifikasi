
<?php

include __DIR__ . '/../Database/DBConnection.php';
if ($conn === null) {
    echo json_encode(['success' => false, 'error' => 'koneksi gagal']);
    exit;
}

if (isset($_GET['abt'])) {

    if ($_GET['abt'] == "supp") {
        $cari = isset($_GET['supp']) ? $_GET['supp'] : '';
        $sql = "SELECT KodeSupplier,NamaSupplier,Alamat,Telp FROM TblSupplier WHERE Aktif='1' AND NamaSupplier LIKE ? ORDER BY NamaSupplier";
        $stmt = $conn->prepare($sql);
        $search = "%$cari%";
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $output = '';
            while ($row = $result->fetch_assoc()) {
                $output .= '<li style="background-color=blue;" class="list-group-item d-flex" ondblclick="PilihSupplier(' . '\'' . $row['KodeSupplier'] . '\',' . '\'' . $row['NamaSupplier'] . '\')">';
                $output .= '<span class="col">' . htmlspecialchars($row['NamaSupplier']) . '<input type="hidden" value="' . htmlspecialchars($row['KodeSupplier']) . '"></span>';
                $output .= '<span class="col">' . htmlspecialchars($row['Alamat']) . '</span>';
                $output .= '<span class="col">' . htmlspecialchars($row['Telp']) . '</span>';
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
    } else if ($_GET['abt'] == "podt") {        
        $kodepo = isset($_GET['kodepo']) ? $_GET['kodepo'] : '';
        $sql = "SELECT * FROM viewdaftarpodetailbarang WHERE idPO=? ORDER BY NamaBarang";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $kodepo);
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
        
        // 1. Ambil role user yang sedang login dari Session untuk logika hak akses.
        $currentUserRole = strtolower($_SESSION['kategoriuser'] ?? 'guest');
        $isSuperAdmin = ($currentUserRole === 'superadmin' || $currentUserRole === 'administrator');
        
        $kodepo_cari = isset($_GET['cari']) ? $_GET['cari'] : '';
        $searchTerm = "%" . $kodepo_cari . "%";

        // Menggunakan prepared statement agar pencarian lebih aman
        $sql = "SELECT *,IF(JumlahBL=0,'BELUM',IF(JumlahBL<TotalBarang,'PROSES','SELESAI')) AS StatusBL FROM viewDaftarPO WHERE Aktif='1' AND KodePO LIKE ? ORDER BY KodePO LIMIT 500";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $output = '';
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                
                // 2. Siapkan aksi ondblclickk secara dinamis, sama sepaerti di PODaftar.php
                $doubleClickEvent = "alert('Maaf, Anda tidak memiliki otoritas untuk mengedit data ini.');";
                if ($isSuperAdmin) {
                    // Menggunakan htmlspecialchars untuk memasstikan data aman dimasukkan ke dalam atribnut HTML
                    $kodepo_js = htmlspecialchars($row['KodePO'], ENT_QUOTES);
                    $tanggal_js = htmlspecialchars($row['Tanggal'], ENT_QUOTES);
                    $idsupplier_js = htmlspecialchars($row['idSupplier'], ENT_QUOTES);
                    $namasupplier_js = htmlspecialchars($row['NamaSupplier'], ENT_QUOTES);
                    $catatan_js = htmlspecialchars($row['Catatan'], ENT_QUOTES);
                    $subtotal_js = htmlspecialchars($row['SubTotal'], ENT_QUOTES);
                    $ppn_js = htmlspecialchars($row['PPN'], ENT_QUOTES);
                    $grandtotal_js = htmlspecialchars($row['GrandTotal'], ENT_QUOTES);
                    $statusbl_js = htmlspecialchars($row['StatusBL'], ENT_QUOTES);
                    
                    $doubleClickEvent = "fillform('{$kodepo_js}','{$tanggal_js}','{$idsupplier_js}','{$namasupplier_js}','{$catatan_js}','{$subtotal_js}','{$ppn_js}','{$grandtotal_js}','{$statusbl_js}');";
                }

                // 3. Gunakan variabel dinamis untuk ondblcllick pada baris hasil pencarian.
                $output .= '<tr style="height:30px" ondblclick="' . $doubleClickEvent . '">';
                $output .= '<td style="width: 125px;" align="center">' . $row['KodePO'] . '</td>';
                $output .= '<td align="center">' . date('d F Y', strtotime($row['Tanggal'])) . '</td>';
                $output .= '<td style="width: 350px;">' . $row['NamaSupplier'] . '</td>';
                $output .= '<td align="right">' . number_format($row['TotalBarang'], 0, ',', '.') . '</td>';
                $output .= '<td align="right">' . number_format($row['SubTotal'], 0, ',', ',') . '</td>';
                $output .= '<td align="right">' . number_format($row['Diskon'], 0, '.', ',') . '</td>';
                $output .= '<td align="right">' . number_format($row['PPNRp'], 0, ',', ',') . '</td>';
                $output .= '<td align="right">' . number_format($row['GrandTotal'], 0, '.', ',') . '</td>';
                $output .= '<td align="center">' . $row['StatusBL'] . '</td>';
                $output .= '</tr>';                
            }
            echo $output;
        } else {
            echo '<tr><td colspan="9">Data tidak ditemukan !</td></tr>';
        }
    }
}
?>
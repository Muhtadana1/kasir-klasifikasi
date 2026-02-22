<?php

include __DIR__ . '/../Database/DBConnection.php';
if ($conn === null) {
    echo json_encode(['success' => false, 'error' => 'koneksi gagal']);
    exit;
}

if (isset($_GET['abt'])) {

    if ($_GET['abt'] == "cust") {
        $cari = isset($_GET['cust']) ? $_GET['cust'] : '';
        $sql = "SELECT KodeCustomer,NamaCustomer,Alamat,Telp FROM TblCustomer WHERE Aktif='1' AND NamaCustomer LIKE ? ORDER BY NamaCustomer";
        $stmt = $conn->prepare($sql);
        $search = "%$cari%";
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $output = '';
            while ($row = $result->fetch_assoc()) {
                $output .= '<li style="background-color=blue;" class="list-group-item d-flex" ondblclick="PilihCustomer(' . '\'' . $row['KodeCustomer'] . '\',' . '\'' . $row['NamaCustomer'] . '\')">';
                $output .= '<span class="col">' . htmlspecialchars($row['NamaCustomer']) . '<input type="hidden" value="' . htmlspecialchars($row['KodeCustomer']) . '"></span>';
                $output .= '<span class="col">' . htmlspecialchars($row['Alamat']) . '</span>';
                $output .= '<span class="col">' . htmlspecialchars($row['Telp']) . '</span>';
                $output .= '</li>';
            }
            echo $output;
        } else {
            $output = ''; // Definisikan $output bahkan jika tidak ada hasil
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
        $sql = "SELECT KodeBarang,NamaBarang,NamaKategori,Satuan,HargaJual FROM viewBarang WHERE (NamaBarang LIKE ? OR NamaKategori LIKE ? ) ORDER BY NamaBarang";
        $stmt = $conn->prepare($sql);
        $search = "%$cari%";
        $stmt->bind_param("ss", $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $output = '';
            while ($row = $result->fetch_assoc()) {
                $output .= '<li class="list-group-item d-flex" ondblclick="PilihBarang(' . '\'' . $row['KodeBarang'] . '\',' . '\'' . $row['NamaBarang'] . '\',' . '\'' . $row['Satuan'] . '\',' . '\'' . $row['NamaKategori'] . '\',' . '\'' . $id . '\',' . '\'' . $row['HargaJual']. '\' )">';
                $output .= '<span class="col">' . htmlspecialchars($row['NamaBarang']) . '<input type="hidden" value="' . htmlspecialchars($row['KodeBarang']) . '"></span>';
                $output .= '<span class="col">' . htmlspecialchars($row['NamaKategori']) . '</span>';
                $output .= '<span class="col">' . htmlspecialchars($row['Satuan']) . '</span>';
                $output .= '</li>';
            }
            echo $output;
        } else {            
            echo 'EOF';
        }
    } else if ($_GET['abt'] == "sodt") {        
        $kodeso = isset($_GET['kodeso']) ? $_GET['kodeso'] : '';
        $sql = "SELECT * FROM viewdaftarSODetailBarang WHERE idSO=? ORDER BY AutoNum";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $kodeso);
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
        
        // --- PERBAIKAN: Menambahkan Pengecekan Role ---
        session_start(); // Wajib ada untuk mengambil data session
        $currentUserRole = strtolower($_SESSION['kategoriuser'] ?? 'guest');
        $isSuperAdmin = ($currentUserRole === 'superadmin' || $currentUserRole === 'administrator');
        // --- AKHIR PERBAIKAN ---

        $kodeso_cari = isset($_GET['cari']) ? $_GET['cari'] : '';
        $searchTerm = "%" . $kodeso_cari . "%";
        
        // --- PERBAIKAN: Menggunakan prepared statement dan WHERE KodeSO ---
        $sql = "SELECT *,IF(JumlahKirim=0,'BELUM',IF(JumlahKirim<JumlahBarang,'PROSES','SELESAI')) AS StatusSJ FROM viewDaftarSO WHERE (KodeSO LIKE ? OR NamaCustomer LIKE ?) AND Aktif='1' ORDER BY KodeSO LIMIT 500";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $output = '';
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                
                // --- PERBAIKAN: Pengecekan Aksi Double-click ---
                $doubleClickEvent = "alert('Maaf, Anda tidak memiliki otoritas untuk mengedit data ini.');"; // Aksi default

                // Amankan string untuk JavaScript
                $kodeso_js = htmlspecialchars(addslashes($row['KodeSO']), ENT_QUOTES);
                $tanggal_js = htmlspecialchars(addslashes($row['Tanggal']), ENT_QUOTES);
                $idcustomer_js = htmlspecialchars(addslashes($row['idCustomer']), ENT_QUOTES);
                $namacustomer_js = htmlspecialchars(addslashes($row['NamaCustomer']), ENT_QUOTES);
                $catatan_js = htmlspecialchars(str_replace(["\r", "\n"], "\\n", addslashes($row['Catatan'])), ENT_QUOTES);
                $subtotal_js = number_format($row['SubTotal'], 2, '.', ',');
                $ppn_js = number_format($row['PPN'], 2, '.', ',');
                $grandtotal_js = number_format($row['GrandTotal'], 2, '.', ',');


                if ($isSuperAdmin) {
                    // 3. Jika SuperAdmin, izinkan edit
                    $doubleClickEvent = "fillform('{$kodeso_js}','{$tanggal_js}','{$idcustomer_js}','{$namacustomer_js}','{$catatan_js}','{$subtotal_js}','{$ppn_js}','{$grandtotal_js}');";
                }
                // --- AKHIR PERBAIKAN ---

                // PERBAIKAN: Terapkan aksi double-click dinamis
                $output .= '<tr style="height:30px" ondblclick="' . $doubleClickEvent . '">';
                
                $output .= '<td style="width: 125px;" align="center">' . $row['KodeSO'] . '</td>';
                $output .= '<td align="center">' . date('d F Y', strtotime($row['Tanggal'])) . '</td>';
                $output .= '<td style="width: 350px;">' . $row['NamaCustomer'] . '</td>';
                $output .= '<td align="right">' . number_format($row['JumlahBarang'], 0, ',', '.') . '</td>'; // Menggunakan JumlahBarang
                $output .= '<td align="right">' . number_format($row['SubTotal'], 2, '.', ',') . '</td>';
                $output .= '<td align="right">' . number_format($row['Diskon'], 0, '.', ',') . '</td>';
                $output .= '<td align="right">' . number_format($row['PPNRp'], 2, '.', ',') . '</td>';
                $output .= '<td align="right">' . number_format($row['GrandTotal'], 2, '.', ',') . '</td>';
                $output .= '<td align="center">' . $row['StatusSJ'] . '</td>';
                $output .= '</tr>';                
            }
            echo $output;
        } else {
            echo '<tr><td colspan="9">Data tidak ditemukan !</td></tr>';
        }
       
    }
}
?>
<?php
session_start();
include __DIR__ . '/../Database/DBConnection.php';

if ($conn === null) exit;

// 1. Ambil Hak Akses dari Session
$akses = $_SESSION['hak_akses']['User'] ?? ['Edit' => 0, 'Delete' => 0];

$cari = isset($_GET['cari']) ? $_GET['cari'] : '';
$search = "%" . $cari . "%";

// 2. Query dengan JOIN (Agar muncul nama jabatan, bukan angka)
$sql = "SELECT a.KodeUser, a.UserLogin, a.KodeKategori, b.NamaKategoriUser 
        FROM tbllogin a 
        LEFT JOIN tblkategoriuser b ON a.KodeKategori = b.KodeKategori
        WHERE a.UserLogin LIKE ? 
        ORDER BY a.UserLogin LIMIT 100";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $search);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Amankan data untuk JS
        $kode = $row['KodeUser'];
        $user = htmlspecialchars($row['UserLogin'], ENT_QUOTES);
        $idKat = $row['KodeKategori'];
        $namaKat = htmlspecialchars($row['NamaKategoriUser'] ?? '-', ENT_QUOTES);

        echo '<tr style="height:30px">';
        echo '<td>' . $user . '</td>';
        echo '<td>' . $namaKat . '</td>';
        
        // 3. Kolom Aksi (Hanya jika punya akses)
        if ($akses['Edit'] || $akses['Delete']) {
            echo '<td class="text-center">';
            
            if ($akses['Edit']) {
                echo "<button class='btn btn-sm btn-primary me-1' onclick=\"EditUser('$kode','$user','$idKat')\">
                        <i class='fa-solid fa-pen'></i>
                      </button>";
            }
            
            if ($akses['Delete']) {
                echo "<button class='btn btn-sm btn-danger' onclick=\"DeleteUser('$kode','$user')\">
                        <i class='fa-solid fa-trash'></i>
                      </button>";
            }
            
            echo '</td>';
        }
        echo '</tr>';
    }
} else {
    // Hitung colspan biar rapi
    $cols = 2 + (($akses['Edit'] || $akses['Delete']) ? 1 : 0);
    echo "<tr><td colspan='$cols' class='text-center p-3'>Data tidak ditemukan.</td></tr>";
}
?>
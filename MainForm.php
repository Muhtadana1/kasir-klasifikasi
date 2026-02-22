<?php
session_start();
include 'Database/DBConnection.php';

$timeout_duration = 3600; // 10 menit

// Cek apakah ada aktivitas sebelumnya
if (isset($_SESSION['last_activity'])) {
    $elapsed_time = time() - $_SESSION['last_activity'];
    if ($elapsed_time > $timeout_duration) {
        // Jika melebihi durasi timeout, hapus semua session
        session_unset();
        session_destroy();
        header("Location: index.php?sessout=true"); // Redirect ke login
        exit;
    }
}

// Perbarui waktu aktivitas terakhir
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="InventoryIcon.ico">
    <title>Inventory Management System</title>
    <link href="bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="bootstrap/css.css">    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script src="bootstrap/bootstrap.bundle.min.js"></script>
    <script src="bootstrap/jquery-3.6.0.min.js"></script>
    <script src="JS/JSFunction.js?v=1.1"></script>

</head>
<body>
    <?php //echo($_SESSION['kategoriuser']); ?>
    <nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top shadow-sm">
        <div class="container-fluid d-flex">
            <a class="navbar-brand me-3" href="MainForm.php">IMS</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="datamaster" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Data Master
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="datamaster">
                            <li><a class="dropdown-item" href="MainForm.php?page=dftsupp">Supplier</a></li>
                            <li><a class="dropdown-item" href="MainForm.php?page=dftcust">Customer</a></li>
                            <li><a class="dropdown-item" href="MainForm.php?page=dftbrg">Inventory</a></li>
                            <?php
                                // Periksa kategori user dari session
                                if (isset($_SESSION['kategoriuser']) && 
                                    (strtolower($_SESSION['kategoriuser']) === 'superadmin' || strtolower($_SESSION['kategoriuser']) === 'administrator')) {
                                    // Jika SuperAdmin atau Administrator, tampilkan menu User
                                    echo '<li><a class="dropdown-item" href="MainForm.php?page=dftuser">User</a></li>';
                                }
                            ?>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                         <a class="nav-link dropdown-toggle" href="#" id="pembelian" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Pembelian
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="pembelian">
                            <li><a class="dropdown-item" href="MainForm.php?page=dftpo">Purchase Order</a></li>
                            <li><a class="dropdown-item" href="MainForm.php?page=dftbl">Penerimaan PO</a></li>                            
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                         <a class="nav-link dropdown-toggle" href="#" id="penjualan" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Penjualan
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="penjualan">
                            <li><a class="dropdown-item" href="MainForm.php?page=dftso">Sales Order</a></li>
                            <li><a class="dropdown-item" href="MainForm.php?page=dftsj">Surat Jalan SO</a></li>
                            <li><a class="dropdown-item" href="MainForm.php?page=returjual">Retur Penjualan</a></li>                            
                        </ul>
                    </li>
                    <li class="nav-item">
                         <a class="nav-link fw-bold text-primary" href="MainForm.php?page=kasir">
                             <i class="fa-solid fa-cash-register"></i> KASIR (POS)
                         </a>
                    </li>
                     <li class="nav-item dropdown">
                         <a class="nav-link dropdown-toggle" href="#" id="laporan" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Laporan
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="laporan">
                            <li class="dropdown-item">                               
                            </li>

                            <li><a class="dropdown-item" href="MainForm.php?page=lappenjualan">Laporan Penjualan</a></li>
                             <li><hr class="dropdown-divider"></li> <!-- Separator -->
                            <li><a class="dropdown-item" href="MainForm.php?page=laporaninventori">Inventory</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                         <a class="nav-link aria-labelledby" href="MainForm.php?page=help">
                             <i class=""></i> help
                         </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?logout=true"><?= $_SESSION['username'];?></a>
                    </li>
                    
                </ul>
            </div>
        </div>
    </nav>
    <?php 
    if (isset($_GET['page'])) 
    {
        if ($_GET['page'] === "dftsupp") {
            include("DataMaster/SupplierDaftar.php");
        } else if ($_GET['page'] === "dftcust") {
            include("DataMaster/CustomerDaftar.php");
        } else if ($_GET['page'] === "dftbrg") {
            include("DataMaster/BarangDaftar.php");
        } else if ($_GET['page'] === "dftuser") {
            include("DataMaster/UserDaftar.php");
        } else if ($_GET['page'] === "dftpo") {
            include("Transaksi/PODaftar.php");
        } else if ($_GET['page'] === "dftbl") {
            include("Transaksi/BLDaftar.php");
        } else if ($_GET['page'] === "dftso") {
            include("Transaksi/SODaftar.php");
        } else if ($_GET['page'] === "dftsj") {
            include("Transaksi/SJDaftar.php");
        } else if ($_GET['page'] === "kasir") { 
            include("Transaksi/KasirDaftar.php");
        } else if ($_GET['page'] === "KasirRiwayat") { 
            include("Transaksi/KasirRiwayat.php");
        } else if ($_GET['page'] === "laporaninventori") {
            include("Laporan/LaporanInventori.php");
        } else if ($_GET['page'] === "lappenjualan") {
            include("Laporan/LaporanPenjualan.php");
        } else if ($_GET['page'] === "returjual") {
            include("Transaksi/ReturPenjualanDaftar.php");
        }else if ($_GET['page'] === "help") { 
            include("Help.php");
        }
    } else {
        // --- TAMBAHAN: JIKA TIDAK ADA PAGE, BUKA DASHBOARD ---
        include("Dashboard.php"); 
    }
    ?>
</body>
</html>

<script>
    document.getElementById("supplierLink").addEventListener("click", function(e) {
        e.preventDefault(); // Mencegah aksi default
        const submenu = document.getElementById("supplierSubmenu");
        submenu.style.display = (submenu.style.display === "none" || submenu.style.display === "") ? "block" : "none";
    });
</script>

<?php
// Pastikan koneksi database sudah ada
?>

<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fa-solid fa-gauge-high text-primary"></i> Dashboard Utama</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                <i class="fa-solid fa-sync"></i> Refresh Data
            </button>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-start border-4 border-primary h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                        <i class="fa-solid fa-boxes-stacked fa-lg"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Total Item Barang</h6>
                        <?php
                            $sqlBarang = "SELECT COUNT(*) as Jml FROM tblbarang WHERE Aktif='1'";
                            $resBarang = $conn->query($sqlBarang);
                            $jmlBarang = ($resBarang) ? $resBarang->fetch_assoc()['Jml'] : 0;
                            echo "<h4 class='fw-bold mb-0'>".number_format($jmlBarang)." <small class='text-muted fs-6'>Item</small></h4>";
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-start border-4 border-success h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                        <i class="fa-solid fa-cart-shopping fa-lg"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Terjual Bulan Ini</h6>
                        <?php
                            $bln = date('m'); $thn = date('Y');
                            $sqlJual = "SELECT COALESCE(SUM(Keluar),0) as Jml FROM tblkartustok 
                                        WHERE (JenisTransaksi='SJ' OR JenisTransaksi='KASIR') 
                                        AND MONTH(Tanggal)='$bln' AND YEAR(Tanggal)='$thn'";
                            $resJual = $conn->query($sqlJual);
                            $jmlJual = ($resJual) ? $resJual->fetch_assoc()['Jml'] : 0;
                            echo "<h4 class='fw-bold mb-0'>".number_format($jmlJual)." <small class='text-muted fs-6'>Unit</small></h4>";
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-start border-4 border-warning h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                        <i class="fa-solid fa-sack-dollar fa-lg"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Estimasi Aset Stok</h6>
                        <?php
                            $sqlAset = "SELECT SUM(Jumlah * HargaBeli) as Aset FROM tblbarang WHERE Aktif='1'";
                            $resAset = $conn->query($sqlAset);
                            $aset = ($resAset) ? $resAset->fetch_assoc()['Aset'] : 0;
                            echo "<h4 class='fw-bold mb-0'>Rp ".number_format($aset, 0, ',', '.')."</h4>";
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm bg-white border-primary">
                <div class="card-body p-4 d-flex flex-column flex-md-row align-items-center justify-content-between gap-4">
                    <div>
                        <h4 class="text-primary fw-bold mb-2"><i class="fa-solid fa-circle-info"></i> Tentang Aplikasi IMS</h4>
                        <p class="text-muted mb-0" style="max-width: 700px;">
                            Selamat datang di <b>Inventory Management System</b>. Aplikasi ini dirancang untuk mempermudah operasional toko.Gunakan tombol panduan di sebelah kiri untuk penjelasan lebih lanjut.
                        </p>
                    </div>
                    <div>
                        <a href="?page=help" class="btn btn-outline-primary btn-lg">
                            <i class="fa-solid fa-book-open me-2"></i> Buka Panduan Lengkap
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mb-3">
            <h5 class="border-start border-4 border-dark ps-2">🔥 Panduan Fitur Monitoring</h5>
            <small class="text-muted ms-2">Pahami arti warna dan indikator di menu Laporan Stok.</small>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-danger text-white d-flex align-items-center">
                    <i class="fa-solid fa-traffic-light me-2 fa-lg"></i> 
                    <span class="fw-bold">1. Diagnosa Stok (Critical & Warning)</span>
                </div>
                <div class="card-body">
                    <p class="card-text text-muted">
                        Fitur ini bekerja otomatis sebagai "Alarm" untuk mencegah kehabisan barang.
                    </p>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-danger p-2" style="width: 80px;">CRITICAL</span>
                                <small>Sisa stok <b>&lt; 20%</b>. Wajib segera belanja (Restock)!</small>
                            </div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-dark p-2" style="width: 80px;">WARNING</span>
                                <small>Sisa stok <b>20% - 40%</b>. Mulai siapkan PO.</small>
                            </div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success p-2" style="width: 80px;">AMAN</span>
                                <small>Stok melimpah <b>&gt; 40%</b>. Tidak perlu tindakan.</small>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-success text-white d-flex align-items-center">
                    <i class="fa-solid fa-gauge-high me-2 fa-lg"></i> 
                    <span class="fw-bold">2. Analisis Fast & Slow Moving</span>
                </div>
                <div class="card-body">
                    <p class="card-text text-muted">
                        Membantu Anda membedakan barang yang "Uang Cepat" dan "Uang Mandek".
                    </p>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0">
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-bolt text-warning me-3 fa-lg"></i>
                                <div>
                                    <h6 class="mb-0 fw-bold">Fast Moving (Laris)</h6>
                                    <small class="text-muted">Rasio Jual > 30%. Barang ini harus selalu tersedia.</small>
                                </div>
                            </div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-snail text-secondary me-3 fa-lg"></i>
                                <div>
                                    <h6 class="mb-0 fw-bold">Slow Moving (Lambat)</h6>
                                    <small class="text-muted">Barang susah laku. Hati-hati menumpuk stok ini.</small>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
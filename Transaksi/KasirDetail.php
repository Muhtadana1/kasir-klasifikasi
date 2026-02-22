<?php
// File: Transaksi/KasirDetail.php

// PENTING: Karena file ini dipanggil lewat AJAX (Fetch), dia BERDIRI SENDIRI.
// Jadi DI SINI WAJIB INCLUDE DATABASE.
include '../Database/DBConnection.php';

if (!isset($_GET['nonota'])) {
    echo "No Nota tidak ditemukan.";
    exit;
}

$noNota = $_GET['nonota'];

// 1. AMBIL HEADER (tblpenjualankasir)
$sqlHead = "SELECT * FROM tblpenjualankasir WHERE NoNota = '$noNota'";
$resHead = $conn->query($sqlHead);
$head = $resHead->fetch_assoc();

if (!$head) {
    echo "<div class='alert alert-danger'>Data Header Transaksi tidak ditemukan.</div>";
    exit;
}

// 2. AMBIL DETAIL (tblpenjualankasirdetail JOIN tblbarang)
// Join ke tblbarang untuk ambil NamaBarang, karena di detail cuma ada ID.
$sqlDet = "SELECT d.*, b.NamaBarang 
           FROM tblpenjualankasirdetail d
           LEFT JOIN tblbarang b ON d.idBarang = b.KodeBarang
           WHERE d.NoNota = '$noNota'";
$resDet = $conn->query($sqlDet);
?>

<div class="row mb-3">
    <div class="col-6">
        <h4 class="mb-0 fw-bold"><?= $head['NoNota']; ?></h4>
        <small class="text-muted">Kasir: <?= $head['KodeUser']; ?></small>
    </div>
    <div class="col-6 text-end">
        <div class="fw-bold"><?= date('d F Y', strtotime($head['Tanggal'])); ?></div>
        <div class="text-muted"><?= $head['Jam']; ?> WIB</div>
    </div>
</div>

<div class="table-responsive border rounded">
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th>Nama Barang</th>
                <th class="text-end">Harga</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($resDet && $resDet->num_rows > 0) {
                while ($d = $resDet->fetch_assoc()) {
            ?>
                <tr>
                    <td>
                        <?= $d['NamaBarang'] ? $d['NamaBarang'] : $d['idBarang']; ?><br>
                        <small class="text-muted code-font"><?= $d['idBarang']; ?></small>
                    </td>
                    <td class="text-end">Rp <?= number_format($d['HargaSatuan'], 0, ',', '.'); ?></td>
                    <td class="text-center"><?= $d['Jumlah']; ?></td>
                    <td class="text-end fw-bold">Rp <?= number_format($d['TotalHarga'], 0, ',', '.'); ?></td>
                </tr>
            <?php
                }
            } else {
                echo "<tr><td colspan='4' class='text-center'>Detail barang tidak ditemukan.</td></tr>";
            }
            ?>
        </tbody>
        <tfoot class="bg-light" style="border-top: 2px solid #ccc;">
            <tr>
                <td colspan="3" class="text-end fw-bold">Total Belanja</td>
                <td class="text-end fw-bold fs-5">Rp <?= number_format($head['GrandTotal'], 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="text-end text-muted">Tunai (Bayar)</td>
                <td class="text-end">Rp <?= number_format($head['Bayar'], 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="text-end text-success fw-bold">Kembali</td>
                <td class="text-end text-success fw-bold">Rp <?= number_format($head['Kembali'], 0, ',', '.'); ?></td>
            </tr>
        </tfoot>
    </table>
</div>
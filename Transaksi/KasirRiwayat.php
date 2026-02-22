<?php
// File: Transaksi/KasirRiwayat.php

// PENTING: Jangan ada include '../Database...' di sini karena file ini 'numpang' di MainForm.
// Koneksi $conn sudah tersedia otomatis dari MainForm.php

// Filter Tanggal
$tglMulai = isset($_GET['tglMulai']) ? $_GET['tglMulai'] : date('Y-m-d');
$tglSampai = isset($_GET['tglSampai']) ? $_GET['tglSampai'] : date('Y-m-d');
?>

<div class="container-fluid p-3">
    
    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Transaksi Kasir</h3>
            <small class="text-muted">Data Penjualan Tunai (POS)</small>
        </div>
        
        <div class="col-md-6 text-end">
            <form method="GET" action="MainForm.php" class="d-inline-flex gap-2 p-2 bg-white border rounded shadow-sm align-items-center">
                <input type="hidden" name="page" value="KasirRiwayat">
                
                <span class="fw-bold small text-muted">Periode:</span>
                <input type="date" name="tglMulai" value="<?= $tglMulai; ?>" class="form-control form-control-sm">
                <span class="small">-</span>
                <input type="date" name="tglSampai" value="<?= $tglSampai; ?>" class="form-control form-control-sm">
                
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa fa-filter"></i> Filter
                </button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" width="5%">No</th>
                            <th width="15%">No Nota</th>
                            <th width="20%">Waktu</th>
                            <th width="15%">Kasir</th>
                            <th width="15%" class="text-end">Total</th>
                            <th width="15%" class="text-end">Bayar</th>
                            <th width="10%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT * FROM tblpenjualankasir 
                                WHERE Tanggal BETWEEN '$tglMulai' AND '$tglSampai' 
                                ORDER BY NoNota DESC";
                        
                        $result = $conn->query($sql);
                        
                        if (!$result) {
                            echo "<tr><td colspan='7' class='text-center text-danger'>Error Query: ".$conn->error."</td></tr>";
                        } else {
                            $no = 1;
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $total = $row['GrandTotal']; 
                                    $bayar = $row['Bayar'];
                        ?>
                                    <tr>
                                        <td class="text-center"><?= $no++; ?></td>
                                        <td class="fw-bold text-primary"><?= $row['NoNota']; ?></td>
                                        <td>
                                            <i class="fa fa-calendar-alt text-muted me-1"></i> <?= date('d/m/Y', strtotime($row['Tanggal'])); ?>
                                            <small class="text-muted ms-2"><i class="fa fa-clock me-1"></i> <?= $row['Jam']; ?></small>
                                        </td>
                                        <td><i class="fa fa-user-circle me-1"></i> <?= $row['KodeUser']; ?></td>
                                        
                                        <td class="text-end fw-bold">Rp <?= number_format($total, 0, ',', '.'); ?></td>
                                        <td class="text-end text-success">Rp <?= number_format($bayar, 0, ',', '.'); ?></td>
                                        
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-info text-white" onclick="lihatDetail('<?= $row['NoNota']; ?>')">
                                                <i class="fa-solid fa-list-check"></i> Detail
                                            </button>
                                        </td>
                                    </tr>
                        <?php
                                }
                            } else {
                                echo "<tr><td colspan='7' class='text-center py-5 text-muted'>Tidak ada data transaksi pada tanggal ini.</td></tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa fa-receipt me-2"></i>Rincian Transaksi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="isiModal">
                <div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function lihatDetail(noNota) {
    var myModal = new bootstrap.Modal(document.getElementById('modalDetail'));
    myModal.show();

    // Loading State
    document.getElementById('isiModal').innerHTML = '<div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-3x text-primary"></i><p class="mt-2">Sedang mengambil data...</p></div>';

    // Fetch ke file Detail
    fetch('Transaksi/KasirDetail.php?nonota=' + noNota)
        .then(response => response.text())
        .then(html => {
            document.getElementById('isiModal').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('isiModal').innerHTML = '<div class="alert alert-danger">Gagal memuat data detail.</div>';
        });
}
</script>
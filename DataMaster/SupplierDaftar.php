<?php
// Pastikan session aktif
if (session_status() === PHP_SESSION_NONE) session_start();

// Ambil Izin Akses Supplier
$akses = $_SESSION['hak_akses']['Supplier'] ?? ['View' => 0, 'Add' => 0, 'Edit' => 0, 'Delete' => 0];

// Jika tidak punya izin View, stop
if (!$akses['View']) {
    echo "<div class='alert alert-danger'>Maaf, Anda tidak memiliki akses melihat data Supplier.</div>";
    exit;
}
?>

<div style="padding: 10px">
    <div class="d-flex justify-content-between mb-2">
        <form name="carisupp" method="post" action="" class="d-flex gap-2">
            <input type="text" id="cari" name="cari" class="form-control" placeholder="Cari Supplier" style="width: 250px;" value="<?= isset($_POST['cari']) ? htmlspecialchars($_POST['cari']) : '' ?>" />
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i> Cari</button>
        </form>

        <?php if ($akses['Add']): ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="clearObj();">
                <i class="fa-solid fa-plus"></i> Tambah Supplier
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive" style="max-height:80vh; overflow-y: auto; border: 1px solid #dee2e6;">
        <table class="table table-bordered table-hover table-striped mb-0">
            <thead class="table-primary text-center" style="position: sticky; top: 0; z-index: 1;">
                <tr>
                    <th>SUPPLIER</th>
                    <th>ALAMAT</th>
                    <th>TELP</th>
                    <th>CONTACT</th>
                    <th>TELP. CONTACT</th>
                    <?php if ($akses['Edit'] || $akses['Delete']): ?>
                        <th style="width: 120px;">AKSI</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody style="font-size:12px">
                <?php
                $cari = $_POST['cari'] ?? '';
                $search = "%$cari%";
                
                // Gunakan Prepared Statement
                $sql = "SELECT * FROM TblSupplier WHERE NamaSupplier LIKE ? ORDER BY NamaSupplier LIMIT 100";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $search);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                   while($row = $result->fetch_assoc()){
                       // Amankan data untuk JS
                       $kode = $row['KodeSupplier'];
                       $nama = htmlspecialchars($row['NamaSupplier'], ENT_QUOTES);
                       $alamat = htmlspecialchars($row['Alamat'], ENT_QUOTES);
                       $telp = htmlspecialchars($row['Telp'], ENT_QUOTES);
                       $sales = htmlspecialchars($row['NamaSales'], ENT_QUOTES);
                       $telpsales = htmlspecialchars($row['TelpSales'], ENT_QUOTES);
                       $catatan = htmlspecialchars($row['Catatan'], ENT_QUOTES);
                   ?>
                    <tr style="height:30px">
                        <td><?= $row['NamaSupplier']; ?></td>
                        <td><?= $row['Alamat']; ?></td>
                        <td><?= $row['Telp']; ?></td>
                        <td><?= $row['NamaSales']; ?></td>
                        <td><?= $row['TelpSales']; ?></td>
                        
                        <?php if ($akses['Edit'] || $akses['Delete']): ?>
                        <td class="text-center">
                            <?php if ($akses['Edit']): ?>
                                <button class="btn btn-sm btn-primary me-1" onclick="EditSupplier('<?=$kode?>','<?=$nama?>','<?=$alamat?>','<?=$telp?>','<?=$sales?>','<?=$telpsales?>','<?=$catatan?>')">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($akses['Delete']): ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteSupplier('<?=$kode?>', '<?=$nama?>');">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php
                   }
                } else {
                    $cols = 5 + (($akses['Edit'] || $akses['Delete']) ? 1 : 0);
                    echo "<tr><td colspan='$cols' class='text-center'>Data tidak ditemukan.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="supplierModalLabel">Form Data Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="supplierForm">
                    <div class="mb-3">
                        <label class="form-label">Nama Supplier</label>
                        <input type="text" class="form-control" id="supplierName" required>
                        <input type="hidden" id="idsupplier" value="0" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" id="supplierAddress" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telepon</label>
                        <input type="text" class="form-control" id="supplierPhone">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="contactperson">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">HP Contact</label>
                            <input type="text" class="form-control" id="telppemasaran">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea class="form-control" id="catatan" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveSupplier()">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script>
     function clearObj() {
         document.getElementById('idsupplier').value = '0';
         document.getElementById('supplierName').value = '';
         document.getElementById('supplierAddress').value = '';
         document.getElementById('supplierPhone').value = '';
         document.getElementById('contactperson').value = '';
         document.getElementById('telppemasaran').value = '';
         document.getElementById('catatan').value = '';
         document.getElementById('supplierModalLabel').innerText = "Tambah Supplier Baru";
     }

     function EditSupplier(kodesupplier,namasupplier,alamat,telp,pemasaran,telppemasaran,catatan) {
         document.getElementById('idsupplier').value = kodesupplier;
         document.getElementById('supplierName').value = namasupplier;
         document.getElementById('supplierAddress').value = alamat;
         document.getElementById('supplierPhone').value = telp;
         document.getElementById('contactperson').value = pemasaran;
         document.getElementById('telppemasaran').value = telppemasaran;
         document.getElementById('catatan').value = catatan;
         
         document.getElementById('supplierModalLabel').innerText = "Edit Data Supplier";
         new bootstrap.Modal(document.getElementById('supplierModal')).show();
     }

     function deleteSupplier(kodesupplier, namasupplier) {
         if (!confirm("Hapus supplier " + namasupplier +" ?")) return;
         
        fetch('DataMaster/SupplierDel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ kodesupplier: kodesupplier })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Berhasil dihapus!');
                location.reload(); 
            } else {
                alert('Gagal: ' + data.error);
            }
        });
    }

    function saveSupplier() {
        const payload = {
            kodesupplier: document.getElementById('idsupplier').value,
            namasupplier: document.getElementById('supplierName').value,
            alamat: document.getElementById('supplierAddress').value,
            telp: document.getElementById('supplierPhone').value,
            pemasaran: document.getElementById('contactperson').value,
            telppemasaran: document.getElementById('telppemasaran').value,
            catatan: document.getElementById('catatan').value
        };

        if (!payload.namasupplier || !payload.alamat) {
            alert('Nama dan Alamat harus diisi!'); return;
        }
            
        fetch('DataMaster/SupplierSave.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Berhasil disimpan!');
                location.reload();
            } else {
                alert('Gagal: ' + data.error);
            }
        });
    }
</script>
<?php
// Pastikan session dimulai
if (session_status() === PHP_SESSION_NONE) session_start();

// Ambil Hak Akses Khusus Fitur 'Barang' dari Session yang sudah Login
$akses = $_SESSION['hak_akses']['Barang'] ?? ['View' => 0, 'Add' => 0, 'Edit' => 0, 'Delete' => 0];

// Cek Izin View (Jika tidak boleh lihat, stop)
if (!$akses['View']) {
    echo "<div class='alert alert-danger'>Maaf, Anda tidak memiliki akses untuk melihat data Barang.</div>";
    exit;
}
?>

<div style="padding: 10px">
    <div class="d-flex justify-content-between mb-2">
        <div class="d-flex gap-2">
            <input type="text" id="caritxt" class="form-control" placeholder="Cari Barang..." style="width: 250px;" onkeyup="if(event.key === 'Enter') refreshTableBody()" />
            <button type="button" id="caribtn" class="btn btn-primary" onclick="refreshTableBody()"><i class="fa-solid fa-search"></i> Cari</button>
        </div>
        
        <?php if ($akses['Add']): ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="clearObj();">
                <i class="fa-solid fa-plus"></i> Tambah Barang
            </button>
        <?php endif; ?>
    </div>
   
    <div class="table-responsive" style="max-height: 80vh; overflow-y: auto; border: 1px solid #dee2e6; padding: 0px;">
        <table class="table table-bordered table-hover table-striped mb-0" cellpadding="0">
            <thead class="table-primary" style="background-color:burlywood; font-size:12px; text-align:center; position: sticky; top: 0; z-index: 1;">
                <tr>
                    <th>NAMA BARANG</th>
                    <th>KATEGORI</th>                   
                    <th>HARGA BELI</th>
                    <th>HARGA JUAL</th>
                    <th>SALDO</th>
                    <th>SATUAN</th>
                    <th>LAMA ORDER</th>
                    <th>HARUS BELI</th>
                    <?php if ($akses['Edit'] || $akses['Delete']): ?>
                        <th style="width: 100px;">AKSI</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="daftarbarang" style="font-size:12px">
                </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="supplierModalLabel">Form Data Barang</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="supplierForm">
                    <div class="container">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="namabarang" class="form-label">Nama Barang</label>
                                    <input type="text" class="form-control" id="namabarang" placeholder="Masukkan nama barang">
                                    <input type="hidden" value="0" id="kodebarang"/>
                                </div>
                                <div class="mb-3">
                                    <label for="kategori" class="form-label">Kategori</label>
                                    <select class="form-select" id="kategori">
                                        <option value="">Pilih Kategori</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="satuan" class="form-label">Satuan</label>
                                    <select class="form-select" id="satuan">
                                        <option value="">Pilih Satuan</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hargabeli" class="form-label">Harga Beli</label>
                                    <input type="text" class="form-control text-end" id="hargabeli" value="0" onblur="this.value=FormatUSNumeric(this.value)">
                                </div>
                                <div class="mb-3">
                                    <label for="hargajual" class="form-label">Harga Jual</label>
                                    <input type="text" class="form-control text-end" id="hargajual" value="0" onblur="this.value=FormatUSNumeric(this.value)">
                                </div>
                                <div class="mb-3">
                                    <label for="lamaorder" class="form-label">Lama Order (Hari)</label>
                                    <input type="number" class="form-control text-end" id="lamaorder" value="0">                                            
                                </div>
                                <div class="mb-3">
                                    <label for="catatan" class="form-label">Catatan</label>
                                    <textarea class="form-control" id="catatan" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                     </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveBarang()">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script>    
    // Load Dropdown saat halaman dibuka
    document.addEventListener("DOMContentLoaded", () => {
        loadDropdown('kategoribarang', 'kategori', 'KodeKategori', 'NamaKategori');
        loadDropdown('satuan', 'satuan', 'NamaSatuan', 'NamaSatuan'); 
        refreshTableBody(); // Load data pertama kali
    });

    function loadDropdown(funcName, elemId, valField, textField) {
        fetch('Database/FunctionDatabase.php?func=' + funcName)
         .then(response => response.json())         
         .then(data => {
            const dropdown = document.getElementById(elemId);
            dropdown.innerHTML = '<option value="">Pilih...</option>';
            data.forEach(item => {
                let val = item[valField]; // Ambil ID (Kode)
                let text = item[textField]; // Ambil Nama
                dropdown.innerHTML += `<option value="${val}">${text}</option>`;
            });
         });
    }

    function clearObj() {
        document.getElementById('kodebarang').value = '0';
        document.getElementById('namabarang').value = '';
        document.getElementById('kategori').value = '';
        document.getElementById('satuan').value = '';
        document.getElementById('catatan').value='';
        document.getElementById('hargabeli').value = '0.00';
        document.getElementById('hargajual').value = '0.00';
        document.getElementById('lamaorder').value = '0';
        
        document.getElementById('supplierModalLabel').innerText = "Tambah Barang Baru";
    }

    // Fungsi Edit: Mengisi form dan menampilkan modal
    function EditBarang(kode, nama, idkat, kat, sat, beli, jual, stok, lama, cat) {
        document.getElementById('kodebarang').value = kode;
        document.getElementById('namabarang').value = nama;
        document.getElementById('kategori').value = idkat; 
        document.getElementById('satuan').value = sat;
        document.getElementById('hargabeli').value = FormatUSNumeric(beli);
        document.getElementById('hargajual').value = FormatUSNumeric(jual);
        document.getElementById('lamaorder').value = lama;
        document.getElementById('catatan').value = cat;
        
        document.getElementById('supplierModalLabel').innerText = "Edit Data Barang";
        new bootstrap.Modal(document.getElementById('supplierModal')).show();
    }

    function DeleteBarang(kode, nama) {
        if (!confirm("Hapus barang " + nama + " ?")) return;
        
        fetch('DataMaster/BarangDel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ kodebarang: kode })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Terhapus!');
                refreshTableBody();
            } else {
                alert('Gagal: ' + data.error);
            }
        });
    }

    function saveBarang() {
        const payload = {
            kodebarang: document.getElementById('kodebarang').value,
            namabarang: document.getElementById('namabarang').value,
            idkategori: document.getElementById('kategori').value,
            satuan: document.getElementById('satuan').value,
            // Bersihkan koma sebelum kirim ke server
            hargabeli: document.getElementById('hargabeli').value.replace(/,/g, ''),
            hargajual: document.getElementById('hargajual').value.replace(/,/g, ''),
            lamaorder: document.getElementById('lamaorder').value,
            catatan: document.getElementById('catatan').value
        };

        if(!payload.namabarang || !payload.idkategori || !payload.satuan) {
            alert("Mohon lengkapi data (Nama, Kategori, Satuan)!");
            return;
        }
        
        fetch('DataMaster/BarangSave.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Berhasil disimpan!');
                // Tutup modal secara manual
                const modalEl = document.getElementById('supplierModal');
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();
                
                refreshTableBody();
            } else {
                alert('Gagal: ' + data.error);
            }
        });
    }

    function refreshTableBody() {
        const cari = document.getElementById('caritxt').value;
        // Load konten tabel dari BarangCari.php via AJAX
        fetch('DataMaster/BarangCari.php?cari=' + encodeURIComponent(cari))
            .then(response => response.text())
            .then(html => {
                document.getElementById('daftarbarang').innerHTML = html;
            });
    }       
 </script>
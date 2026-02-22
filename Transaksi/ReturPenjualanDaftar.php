<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$akses = $_SESSION['hak_akses']['Retur'] ?? ['View' => 0, 'Add' => 0, 'Special' => 0];

if (!$akses['View']) {
    echo "<div class='alert alert-danger'>Akses Ditolak.</div>";
    exit;
}
$isAdmin = $akses['Special']; // Variabel untuk JS (1=Admin, 0=Kasir)
?>

<div style="padding: 10px">
    <div class="d-flex justify-content-between mb-2">
        <div class="d-flex gap-2">
            <input type="text" id="caritxt" class="form-control" placeholder="Cari No Retur / Customer..." style="width: 250px;" onkeyup="loadReturData()">
            <select id="filterStatus" class="form-control" style="width: 150px;" onchange="loadReturData()">
                <option value="">Semua Status</option>
                <option value="PENDING">PENDING</option>
                <option value="APPROVED">APPROVED</option>
            </select>
            <button class="btn btn-primary" onclick="loadReturData()">Cari</button>
        </div>
        
        <?php if ($akses['Add']): ?>
            <button class="btn btn-success" onclick="bukaModalRetur()">
                <i class="fa-solid fa-plus"></i> Retur Baru
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive shadow-sm" style="max-height: 80vh; overflow-y: auto; border: 1px solid #dee2e6;">
        <table class="table table-bordered table-hover table-striped mb-0">
            <thead class="table-dark text-center">
                <tr>
                    <th>NO RETUR</th>
                    <th>TANGGAL</th>
                    <th>CUSTOMER</th>
                    <th>REF. DOKUMEN</th>
                    <th>TOTAL NILAI</th>
                    <th>STATUS</th>
                    <th style="width: 120px;">AKSI</th>
                </tr>
            </thead>
            <tbody id="tabelReturBody" style="font-size:13px">
                <tr><td colspan="7" class="text-center">Memuat data...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalLihat" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold"><i class="fa fa-eye"></i> Detail Barang Retur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="small text-muted">No Retur</label>
                        <h5 class="fw-bold" id="viewNoRetur">-</h5>
                    </div>
                    <div class="col-6 text-end">
                        <label class="small text-muted">Customer</label>
                        <h5 class="fw-bold" id="viewCustomer">-</h5>
                    </div>
                </div>
                <table class="table table-bordered table-striped">
                    <thead class="table-secondary">
                        <tr>
                            <th>Nama Barang</th>
                            <th class="text-center">Jumlah</th>
                            <th>Alasan</th>
                        </tr>
                    </thead>
                    <tbody id="viewTbody"></tbody>
                </table>
                <div class="alert alert-warning py-1 small" id="viewCatatan">Catatan: -</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRetur" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold">Input Retur Penjualan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6 offset-md-3">
                        <div class="input-group">
                            <span class="input-group-text fw-bold">SUMBER DOKUMEN</span>
                            <input type="text" id="inputSumberKode" class="form-control" placeholder="Scan No SJ / Nota Kasir disini..." autocomplete="off">
                            <button class="btn btn-primary" onclick="cekSumberData()">CEK DATA</button>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row mb-2">
                    <div class="col-md-4">
                        <label>Customer :</label>
                        <input type="text" id="txtNamaCustomer" class="form-control" readonly>
                        <input type="hidden" id="txtKodeCustomer">
                    </div>
                    <div class="col-md-4">
                        <label>Tanggal Retur :</label>
                        <input type="date" id="txtTanggal" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-4">
                        <label>Total Retur (Estimasi) :</label>
                        <input type="text" id="txtTotalRetur" class="form-control text-end fw-bold" value="0" readonly>
                    </div>
                </div>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm">
                        <thead class="table-secondary text-center">
                            <tr>
                                <th>Nama Barang</th>
                                <th width="15%">Qty Beli</th>
                                <th width="15%">Qty Retur</th>
                                <th width="20%">Harga Jual</th>
                                <th width="10%">Hapus</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyDetail" style="background-color: #fff;">
                            <tr><td colspan="5" class="text-center text-muted p-4">Silakan Cek Data Sumber Dahulu.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <label>Alasan / Catatan :</label>
                        <textarea id="txtCatatan" class="form-control" rows="2" placeholder="Contoh: Barang Rusak..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary fw-bold" onclick="simpanRetur()">SIMPAN RETUR</button>
            </div>
        </div>
    </div>
</div>

<script>
    const IS_ADMIN = <?= $isAdmin ?>;
    let detailBarang = [];

    document.addEventListener("DOMContentLoaded", function() {
        loadReturData();
    });

    // 1. LOAD DATA TABEL (DIPERBAIKI)
    function loadReturData() {
        const q = document.getElementById('caritxt').value;
        const s = document.getElementById('filterStatus').value;
        
        fetch(`Transaksi/ReturPenjualanCari.php?aksi=list&q=${encodeURIComponent(q)}&status=${s}`)
        .then(res => res.json())
        .then(data => {
            let html = '';
            if(data.length === 0) {
                html = '<tr><td colspan="7" class="text-center">Data tidak ditemukan.</td></tr>';
            } else {
                data.forEach(row => {
                    let badge = row.Status === 'APPROVED' ? '<span class="badge bg-success">APPROVED</span>' : 
                               (row.Status === 'PENDING' ? '<span class="badge bg-warning text-dark">PENDING</span>' : '<span class="badge bg-danger">REJECTED</span>');
                    
                    // --- PERBAIKAN LOGIC REF DOKUMEN ---
                    // Cek 'NoFaktur' dulu, kalau kosong baru coba ambil dari 'Catatan'
                    let docAsal = row.NoFaktur; 
                    if (!docAsal || docAsal === '') {
                         // Fallback ke logika lama (ambil dari catatan)
                         docAsal = row.Catatan ? row.Catatan.split('(')[0] : '-';
                    }

                    // --- TOMBOL AKSI (MATA + APPROVE) ---
                    let btnLihat = `<button class="btn btn-sm btn-info text-white me-1" onclick="lihatDetail('${row.NoRetur}')" title="Lihat Detail"><i class="fa fa-eye"></i></button>`;
                    let btnApprove = '';

                    if(row.Status === 'PENDING') {
                        if(IS_ADMIN == 1) {
                            btnApprove = `
                                <button class="btn btn-sm btn-success" onclick="updateStatus('${row.NoRetur}','approve')"><i class="fa fa-check"></i></button>
                                <button class="btn btn-sm btn-danger ms-1" onclick="updateStatus('${row.NoRetur}','reject')"><i class="fa fa-times"></i></button>
                            `;
                        } else {
                            btnApprove = `<i class="fa fa-lock text-muted ms-1" title="Menunggu Persetujuan"></i>`;
                        }
                    } else {
                        btnApprove = row.Status === 'APPROVED' ? '<i class="fa fa-check-circle text-success ms-1"></i>' : '<i class="fa fa-times-circle text-danger ms-1"></i>';
                    }

                    html += `
                        <tr style="height:35px">
                            <td class="text-center fw-bold text-primary">${row.NoRetur}</td>
                            <td class="text-center">${row.Tanggal}</td>
                            <td>${row.NamaCustomer || 'Umum'}</td>
                            <td class="text-center"><span class="badge bg-light text-dark border">${docAsal}</span></td>
                            <td class="text-end fw-bold">${FormatUSNumeric(row.TotalRetur)}</td>
                            <td class="text-center">${badge}</td>
                            <td class="text-center">${btnLihat}${btnApprove}</td>
                        </tr>
                    `;
                });
            }
            document.getElementById('tabelReturBody').innerHTML = html;
        });
    }

    // 2. LIHAT DETAIL (POPUP)
    function lihatDetail(no) {
        fetch(`Transaksi/ReturPenjualanCari.php?aksi=get_detail_saved&no=${no}`)
        .then(res => res.json())
        .then(data => {
            if(!data.success) { alert("Error / Data belum didukung: " + (data.error || '')); return; }
            
            document.getElementById('viewNoRetur').innerText = data.header.NoRetur;
            document.getElementById('viewCustomer').innerText = data.header.NamaCustomer || 'Umum';
            document.getElementById('viewCatatan').innerText = "Catatan: " + (data.header.Catatan || '-');
            
            let html = '';
            if(data.detail) {
                data.detail.forEach(d => {
                    html += `<tr>
                        <td>${d.NamaBarang} <br><small class="text-muted">${d.idBarang}</small></td>
                        <td class="text-center fw-bold">${d.Jumlah}</td>
                        <td>${d.Alasan || '-'}</td>
                    </tr>`;
                });
            }
            document.getElementById('viewTbody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalLihat')).show();
        });
    }

    // 3. CEK SUMBER DATA
    function cekSumberData() {
        const kode = document.getElementById('inputSumberKode').value.trim();
        if(!kode) { alert("Masukkan Kode Dokumen!"); return; }

        fetch(`Transaksi/ReturPenjualanCari.php?aksi=get_sumber&kode=${encodeURIComponent(kode)}`)
        .then(res => res.json())
        .then(data => {
            if(!data.success) {
                alert(data.error);
                return;
            }
            document.getElementById('txtNamaCustomer').value = data.header.NamaCustomer;
            document.getElementById('txtKodeCustomer').value = data.header.KodeCustomer;
            detailBarang = data.detail.map(item => ({
                id: item.idBarang,
                nama: item.NamaBarang,
                jmlBeli: parseFloat(item.Jumlah),
                harga: parseFloat(item.HargaJual),
                qtyRetur: 0
            }));
            renderDetailTable();
        })
        .catch(err => alert("Gagal koneksi: " + err));
    }

    // 4. RENDER INPUT & HITUNG
    function renderDetailTable() {
        const tbody = document.getElementById('tbodyDetail');
        let html = '';
        detailBarang.forEach((item, idx) => {
            html += `<tr>
                <td>${item.nama} <br><small>${item.id}</small></td>
                <td class="text-end">${item.jmlBeli}</td>
                <td><input type="number" class="form-control form-control-sm text-end fw-bold" value="${item.qtyRetur}" min="0" max="${item.jmlBeli}" onchange="updateQty(${idx}, this.value)"></td>
                <td class="text-end">${FormatUSNumeric(item.harga)}</td>
                <td class="text-center"><button class="btn btn-danger btn-sm py-0" onclick="hapusItem(${idx})">X</button></td>
            </tr>`;
        });
        if(detailBarang.length === 0) html = '<tr><td colspan="5" class="text-center p-3">Tidak ada barang dipilih.</td></tr>';
        tbody.innerHTML = html;
        hitungTotalHeader();
    }

    function updateQty(idx, val) {
        let qty = parseFloat(val);
        if(qty < 0) qty = 0;
        if(qty > detailBarang[idx].jmlBeli) { alert("Melebihi jumlah beli!"); qty = detailBarang[idx].jmlBeli; }
        detailBarang[idx].qtyRetur = qty;
        renderDetailTable();
    }

    function hapusItem(idx) {
        detailBarang.splice(idx, 1);
        renderDetailTable();
    }

    function hitungTotalHeader() {
        let total = 0;
        detailBarang.forEach(item => { total += (item.qtyRetur * item.harga); });
        document.getElementById('txtTotalRetur').value = FormatUSNumeric(total);
    }

    function simpanRetur() {
        const itemsToSave = detailBarang.filter(i => i.qtyRetur > 0);
        if(itemsToSave.length === 0) { alert("Harap isi Qty Retur."); return; }

        // PERHATIKAN: NoFaktur dikirim dari input sumber
        const payload = {
            act: 'save',
            tanggal: document.getElementById('txtTanggal').value,
            customer: document.getElementById('txtKodeCustomer').value,
            NoFaktur: document.getElementById('inputSumberKode').value, 
            catatan: document.getElementById('txtCatatan').value,
            detail: itemsToSave.map(i => ({ id: i.id, qty: i.qtyRetur, harga: i.harga }))
        };

        fetch('Transaksi/ReturPenjualanSave.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert("SUKSES: " + data.error);
                bootstrap.Modal.getInstance(document.getElementById('modalRetur')).hide();
                loadReturData();
            } else {
                alert("GAGAL: " + data.error);
            }
        })
        .catch(err => alert("Error: " + err));
    }

    function updateStatus(no, status) {
        if(!confirm("Anda yakin mengubah status?")) return;
        fetch('Transaksi/ReturPenjualanSave.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ act: status, no_retur: no })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) { loadReturData(); } 
            else { alert("Gagal: " + data.error); }
        });
    }

    function bukaModalRetur() {
        document.getElementById('inputSumberKode').value = '';
        document.getElementById('txtNamaCustomer').value = '';
        document.getElementById('txtTotalRetur').value = '0';
        document.getElementById('txtCatatan').value = '';
        detailBarang = [];
        renderDetailTable();
        new bootstrap.Modal(document.getElementById('modalRetur')).show();
    }

    function FormatUSNumeric(num) {
        return new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(num);
    }
</script>
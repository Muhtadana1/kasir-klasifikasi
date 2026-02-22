<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$akses = $_SESSION['hak_akses']['SO'] ?? ['View' => 0, 'Add' => 0, 'Edit' => 0, 'Delete' => 0];

if (!$akses['View']) {
    echo "<div class='alert alert-danger'>Akses Ditolak.</div>";
    exit;
}
?>

<div style="padding: 10px">
    <div class="d-flex justify-content-between mb-2">
        <div class="d-flex gap-2">
            <input type="text" id="caritxt" class="form-control" placeholder="Cari SO..." style="width: 250px;" onkeyup="refreshTableBody()" />
            <button type="button" id="caribtn" class="btn btn-primary" onclick="refreshTableBody()">Cari</button>
        </div>
        
        <?php if ($akses['Add']): ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#soModal" onclick="clearObj();">
                <i class="fa-solid fa-plus"></i> SO Baru
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive" style="max-height: 80vh; overflow-y: auto; border: 1px solid #dee2e6;">
        <table class="table table-bordered table-hover table-striped mb-0">
            <thead class="table-primary text-center">
                <tr>
                    <th>KODE SO</th>
                    <th>TANGGAL</th>
                    <th>CUSTOMER</th>
                    <th>TOTAL BARANG</th>
                    <th>GRAND TOTAL</th>
                    <th>STATUS SJ</th>
                    <?php if ($akses['Edit'] || $akses['Delete']): ?>
                        <th style="width: 120px;">AKSI</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="daftarpo" style="font-size:12px">
                <?php
                $sql = "SELECT *,IF(JumlahKirim=0,'BELUM',IF(JumlahKirim<JumlahBarang,'PROSES','SELESAI')) AS StatusSJ 
                        FROM viewDaftarSO WHERE Aktif='1' ORDER BY KodeSO DESC LIMIT 100";
                
                $result = $conn->query($sql);
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $kodeso = $row['KodeSO'];
                        $tanggal = $row['Tanggal'];
                        $idcust = $row['idCustomer'];
                        $nmcust = htmlspecialchars($row['NamaCustomer'], ENT_QUOTES);
                        $catatan = htmlspecialchars($row['Catatan'] ?? '', ENT_QUOTES);
                        $sub = $row['SubTotal'];
                        $ppn = $row['PPN'];
                        $grand = $row['GrandTotal'];
                        $stat = $row['StatusSJ']; 

                        echo "<tr style='height:30px'>";
                        echo "<td class='text-center'>$kodeso</td>";
                        echo "<td class='text-center'>".date('d M Y', strtotime($tanggal))."</td>";
                        echo "<td>$nmcust</td>";
                        echo "<td class='text-end'>".number_format($row['JumlahBarang'])."</td>";
                        echo "<td class='text-end'>".number_format($grand, 0, ',', '.')."</td>";
                        echo "<td class='text-center'>$stat</td>";

                        if ($akses['Edit'] || $akses['Delete']) {
                            echo "<td class='text-center'>";
                            
                            // LOGIKA PENGUNCIAN: Jika Status != BELUM, Kunci.
                            if ($stat !== 'BELUM') {
                                echo "<span class='badge bg-secondary'><i class='fa-solid fa-lock'></i> Terkunci</span>";
                            } else {
                                if ($akses['Edit']) {
                                    echo "<button class='btn btn-sm btn-primary me-1' onclick=\"fillform('$kodeso','$tanggal','$idcust','$nmcust','$catatan','$sub','$ppn','$grand','$stat')\"><i class='fa-solid fa-pen'></i></button>";
                                }
                                if ($akses['Delete']) {
                                    echo "<button class='btn btn-sm btn-danger' onclick=\"deleteSO('$kodeso')\"><i class='fa-solid fa-trash'></i></button>";
                                }
                            }
                            echo "</td>";
                        }
                        echo "</tr>";
                    }
                } else {
                    $cols = 6 + (($akses['Edit'] || $akses['Delete']) ? 1 : 0);
                    echo "<tr><td colspan='$cols' class='text-center'>Data tidak ditemukan !</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="soModal" tabindex="-1" aria-labelledby="soModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="height: 40px; background-color :lightblue;">
                <h5 class="modal-title" id="soModalLabel">Sales Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="container">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label text-end" style="font-size:12px;">Customer :</label>
                                <div class="col-sm-8 position-relative">
                                    <input type="text" id="customer" class="form-control form-control-sm" placeholder="Cari Customer..." autocomplete="off">
                                    <ul id="customerlist" class="list-group"></ul>
                                </div>
                            </div>
                            <div class="form-group row mt-2">
                                <label class="col-sm-4 col-form-label text-end" style="font-size:12px;">Tanggal :</label>
                                <div class="col-sm-8">
                                    <input type="date" id="tanggal" class="form-control form-control-sm">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group row" id="groupStatusSJ" style="display:none;">
                                <label class="col-sm-4 col-form-label text-end" style="font-size:12px;">Status SJ :</label>
                                <div class="col-sm-8">
                                    <input type="text" id="statussj" class="form-control form-control-sm text-center fw-bold" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label text-end" style="font-size:12px;">Nomor SO:</label>
                                <div class="col-sm-8">
                                    <input type="text" id="nomorSO" class="form-control form-control-sm text-center fw-bold" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <table class="table table-bordered table-sm" id="detailBarang">
                                <thead style="background-color:burlywood; font-size:12px; text-align:center">
                                    <tr>
                                        <th width="35%">Nama Barang</th>
                                        <th width="10%">Qty</th>
                                        <th width="20%">Harga</th>
                                        <th width="25%">Total</th>
                                        <th width="10%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="detailpo" style="background-color:lightblue;"></tbody>
                            </table>
                            <button type="button" id="btnAddItem" class="btn btn-primary btn-sm float-end" onclick="tambahBaris()">+ Tambah Barang</button>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label style="font-size:12px;">Catatan :</label>
                            <textarea class="form-control" id="catatan" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group row mb-1">
                                <label class="col-sm-4 col-form-label text-end" style="font-size:12px;">Sub Total :</label>
                                <div class="col-sm-8">
                                    <input type="text" id="subtotal" class="form-control form-control-sm text-end" readonly value="0">
                                </div>
                            </div>
                            <div class="form-group row mb-1">
                                <label class="col-sm-4 col-form-label text-end" style="font-size:12px;">PPN (%) :</label>
                                <div class="col-sm-8">
                                    <input type="text" id="ppn" class="form-control form-control-sm text-end" value="11" oninput="hitungGrandTotal()">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label text-end" style="font-size:12px;">Grand Total :</label>
                                <div class="col-sm-8">
                                    <input type="text" id="grandtotal" class="form-control form-control-sm text-end fw-bold" readonly value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnDeleteSO" class="btn btn-danger me-auto" onclick="deleteSO()">Hapus Data</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" id="btnSaveSO" class="btn btn-primary" onclick="saveSO()">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script>
    // --- FUNGSI UTAMA: Membuka Form Edit ---
    function openEditForm(row) {
        // Ambil data dari atribut HTML (Data Attribute) - Sangat Aman
        const d = row.dataset;
        fillform(d.kodeso, d.tanggal, d.idcust, d.nmcust, d.catatan, d.subtotal, d.ppn, d.grandtotal, d.status);
    }

    function fillform(kodeso, tanggal, idcustomer, nmcustomer, Catatan, SubTotal, PPN, GrandTotal, statussj) {
        // Reset UI
        document.querySelector("#detailBarang tbody").innerHTML = '';
        document.getElementById('customerlist').style.display = 'none';

        // Isi Header
        document.getElementById('nomorSO').value = kodeso;
        document.getElementById('nomorSO').dataset.kodeso = kodeso;
        
        const inpCust = document.getElementById('customer');
        inpCust.value = nmcustomer;
        inpCust.dataset.idcustomer = idcustomer;
        inpCust.dataset.namacustomer = nmcustomer;

        document.getElementById('tanggal').value = tanggal;
        document.getElementById('catatan').value = Catatan;
        document.getElementById('subtotal').value = FormatUSNumeric(SubTotal);
        document.getElementById('ppn').value = FormatUSNumeric(PPN);
        document.getElementById('grandtotal').value = FormatUSNumeric(GrandTotal);

        // Tampilkan Status
        const elStatus = document.getElementById('statussj');
        elStatus.value = statussj;
        document.getElementById('groupStatusSJ').style.display = 'flex';

        // --- LOGIKA PENGUNCIAN (LOCKING) ---
        const isLocked = (statussj !== 'BELUM');
        
        // Sembunyikan/Tampilkan Tombol
        document.getElementById('btnSaveSO').style.display = isLocked ? 'none' : 'block';
        document.getElementById('btnDeleteSO').style.display = isLocked ? 'none' : 'block';
        document.getElementById('btnAddItem').style.display = isLocked ? 'none' : 'block';
        
        // Matikan/Hidupkan Input
        ['customer', 'tanggal', 'catatan', 'ppn'].forEach(id => {
            document.getElementById(id).disabled = isLocked;
        });

        // Ubah Judul Modal
        const lbl = document.getElementById('soModalLabel');
        lbl.innerText = isLocked ? "Sales Order (TERKUNCI - SUDAH DIPROSES)" : "Edit Sales Order";
        lbl.style.color = isLocked ? "red" : "black";

        // Load Detail Barang via AJAX
        fetch('Transaksi/SOCari.php?abt=sodt&kodeso=' + kodeso)
            .then(res => res.json())
            .then(response => {
                if (response.status === 'success') {
                    response.data.forEach(item => {
                        tambahBarisHTML(item, isLocked);
                    });
                }
            });

        // Tampilkan Modal
        new bootstrap.Modal(document.getElementById('soModal')).show();
    }

    // --- FUNGSI PEMBUAT BARIS TABEL (Digunakan oleh Add New & Edit) ---
    function tambahBarisHTML(item = null, isLocked = false) {
        const table = document.querySelector("#detailBarang tbody");
        const uniqueId = Date.now() + Math.random().toString(36).substr(2, 5);

        // Tentukan nilai awal (kosong jika baru, terisi jika edit)
        const namaVal = item ? item.NamaBarang : '';
        const kodeVal = item ? item.idBarang : '';
        const idDetail = item ? item.AutoNum : '';
        const qtyVal = item ? item.JumlahDetSO : 0;
        const hargaVal = item ? FormatUSNumeric(item.HargaJual) : '0.00';
        const totalVal = item ? FormatUSNumeric(item.TotalHarga) : '0.00';
        
        const disabledAttr = isLocked ? 'disabled' : '';
        const deleteBtn = isLocked ? '' : `<button class="btn btn-danger btn-sm py-0" onclick="hapusBaris(this)">X</button>`;

        const row = document.createElement("tr");
        row.innerHTML = `
            <td>
                <div style="position:relative">
                    <input type="text" class="form-control form-control-sm input-barang" id="namabarang${uniqueId}" value="${namaVal}" autocomplete="off" ${disabledAttr}>
                    <input type="hidden" name="kodebarang[]" id="kodebarang${uniqueId}" value="${kodeVal}" data-iddetailso="${idDetail}">
                    <ul id="barangList${uniqueId}" class="list-group" style="position:absolute; z-index:1000; display:none; width:100%; max-height:200px; overflow-y:auto;"></ul>
                </div>
            </td>
            <td><input type="text" name="kuantitas[]" class="form-control form-control-sm text-end" value="${qtyVal}" oninput="hitungTotal(this)" ${disabledAttr}></td>
            <td><input type="text" name="harga_satuan[]" class="form-control form-control-sm text-end" value="${hargaVal}" oninput="hitungTotal(this)" ${disabledAttr}></td>
            <td><input type="text" name="total[]" class="form-control form-control-sm text-end" value="${totalVal}" readonly></td>
            <td class="text-center">${deleteBtn}</td>
        `;
        table.appendChild(row);

        if (!isLocked) {
            setupRowEvents(row, uniqueId);
        }
    }

    function tambahBaris() {
        tambahBarisHTML();
    }

    function setupRowEvents(row, uniqueId) {
        const inputName = row.querySelector(`#namabarang${uniqueId}`);
        const list = row.querySelector(`#barangList${uniqueId}`);
        const inputHarga = row.querySelector('input[name="harga_satuan[]"]');

        // Pencarian Barang
        inputName.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                fetch('Transaksi/SOCari.php?abt=brng&id=' + uniqueId + '&brng=' + encodeURIComponent(this.value))
                    .then(res => res.text())
                    .then(html => {
                        if (html.trim() === 'EOF') { alert('Barang tidak ditemukan'); return; }
                        list.innerHTML = html;
                        list.style.display = 'block';
                    });
            }
        });

        // Format Harga saat blur
        inputHarga.addEventListener('blur', function() {
            this.value = FormatUSNumeric(this.value);
            hitungTotal(this);
        });
    }

    // Fungsi Pilih Barang (Dipanggil dari hasil pencarian AJAX)
    function PilihBarang(kode, nama, satuan, kat, id, harga) {
        document.getElementById('namabarang' + id).value = nama;
        document.getElementById('kodebarang' + id).value = kode;
        
        // Set harga default
        const row = document.getElementById('namabarang' + id).closest('tr');
        const inputHarga = row.querySelector('input[name="harga_satuan[]"]');
        inputHarga.value = FormatUSNumeric(harga);
        
        document.getElementById('barangList' + id).style.display = 'none';
        hitungTotal(inputHarga);
    }

    // --- KALKULASI ---
    function hitungTotal(el) {
        const row = el.closest("tr");
        const qty = parseFloat(row.querySelector('input[name="kuantitas[]"]').value.replace(/,/g, '')) || 0;
        const harga = parseFloat(row.querySelector('input[name="harga_satuan[]"]').value.replace(/,/g, '')) || 0;
        row.querySelector('input[name="total[]"]').value = FormatUSNumeric(qty * harga);
        hitungGrandTotal();
    }

    function hitungGrandTotal() {
        let sub = 0;
        document.querySelectorAll('input[name="total[]"]').forEach(inp => {
            sub += parseFloat(inp.value.replace(/,/g, '')) || 0;
        });
        
        const ppnVal = parseFloat(document.getElementById('ppn').value.replace(/,/g, '')) || 0;
        const grand = sub + (sub * ppnVal / 100);

        document.getElementById('subtotal').value = FormatUSNumeric(sub);
        document.getElementById('grandtotal').value = FormatUSNumeric(grand);
    }

    function hapusBaris(btn) {
        btn.closest('tr').remove();
        hitungGrandTotal();
    }

    // --- CUSTOMER SEARCH ---
    document.getElementById('customer').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            fetch('Transaksi/SOCari.php?abt=cust&cust=' + encodeURIComponent(this.value))
                .then(res => res.text())
                .then(html => {
                    const list = document.getElementById('customerlist');
                    list.innerHTML = html;
                    list.style.display = 'block';
                });
        }
    });

    function PilihCustomer(kode, nama) {
        const el = document.getElementById('customer');
        el.value = nama;
        el.dataset.idcustomer = kode;
        el.dataset.namacustomer = nama;
        CloseCustomerList();
    }

    function CloseCustomerList() {
        document.getElementById('customerlist').style.display = 'none';
    }

    // --- FUNGSI RESET / NEW ---
    function clearObj() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('tanggal').value = today;
        document.getElementById('customer').value = '';
        document.getElementById('customer').dataset.idcustomer = '';
        document.getElementById('catatan').value = '';
        
        // Kosongkan Detail
        document.querySelector("#detailBarang tbody").innerHTML = '';

        // Ambil No Nota Baru
        fetch('Database/FunctionDatabase.php?func=getnotano&tanggal=' + today + '&jenisnota=SO')
            .then(res => res.text())
            .then(no => {
                document.getElementById('nomorSO').value = no;
                document.getElementById('nomorSO').dataset.kodeso = ''; // Kosong = Baru
            });

        // Reset UI ke Mode Edit
        document.getElementById('groupStatusSJ').style.display = 'none';
        document.getElementById('btnSaveSO').style.display = 'block';
        document.getElementById('btnDeleteSO').style.display = 'none';
        document.getElementById('btnAddItem').style.display = 'block';
        
        ['customer', 'tanggal', 'catatan', 'ppn'].forEach(id => document.getElementById(id).disabled = false);
        
        const lbl = document.getElementById('soModalLabel');
        lbl.innerText = "Sales Order Baru";
        lbl.style.color = "black";

        document.getElementById('subtotal').value = '0.00';
        document.getElementById('ppn').value = '11.00';
        document.getElementById('grandtotal').value = '0.00';
    }

    // --- FUNGSI SIMPAN ---
    function saveSO() {
        const idcust = document.getElementById('customer').dataset.idcustomer;
        if(!idcust) { alert('Pilih Customer dulu!'); return; }
        
        const detail = [];
        let valid = true;
        let totalBarang = 0;

        document.querySelectorAll("#detailBarang tbody tr").forEach(row => {
            const kode = row.querySelector('input[name="kodebarang[]"]').value;
            const iddetail = row.querySelector('input[name="kodebarang[]"]').dataset.iddetailso || '';
            const nama = row.querySelector('input.input-barang').value;
            const qty = parseFloat(row.querySelector('input[name="kuantitas[]"]').value.replace(/,/g,'')) || 0;
            const harga = parseFloat(row.querySelector('input[name="harga_satuan[]"]').value.replace(/,/g,'')) || 0;
            const total = parseFloat(row.querySelector('input[name="total[]"]').value.replace(/,/g,'')) || 0;

            if(!kode || qty <= 0) valid = false;
            
            detail.push({ iddetailso: iddetail, kodebarang: kode, namaBarang: nama, jumlah: qty, harga: harga, total: total });
            totalBarang += qty;
        });

        if(!valid || detail.length === 0) { alert('Data barang tidak lengkap/kosong!'); return; }

        const payload = {
            act: 'save',
            kodecustomer: idcust,
            tanggal: document.getElementById('tanggal').value,
            kodeso: document.getElementById('nomorSO').dataset.kodeso || '',
            totalbarang: totalBarang,
            subtotal: document.getElementById('subtotal').value.replace(/,/g,''),
            ppn: document.getElementById('ppn').value.replace(/,/g,''),
            grandtotal: document.getElementById('grandtotal').value.replace(/,/g,''),
            catatan: document.getElementById('catatan').value,
            detailbarang: detail
        };

        fetch('Transaksi/SOSave.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert('Berhasil Disimpan!');
                location.reload();
            } else {
                alert('Gagal: ' + data.error);
            }
        });
    }

    // --- FUNGSI HAPUS ---
    function deleteSO(kodeso = null) {
        const kode = kodeso || document.getElementById('nomorSO').dataset.kodeso;
        if(!kode) return;
        if(!confirm('Hapus Data SO '+kode+'?')) return;

        fetch('Transaksi/SOSave.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ act: 'delete', kodeso: kode })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert('Data terhapus!');
                location.reload();
            } else {
                alert('Gagal: ' + data.error);
            }
        });
    }

    function refreshTableBody() {
        const cari = document.getElementById('caritxt').value;
        $.ajax({
            url: 'Transaksi/SOCari.php?abt=cari&cari=' + encodeURIComponent(cari),
            type: 'GET',
            success: function(res) { $('#daftarpo').html(res); }
        });
    }
</script>
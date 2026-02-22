<<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$akses = $_SESSION['hak_akses']['SJ'] ?? ['View' => 0, 'Add' => 0, 'Edit' => 0, 'Delete' => 0];

if (!$akses['View']) { echo "<div class='alert alert-danger'>Akses Ditolak.</div>"; exit; }
?>

<div style="padding: 10px">    
    <div class="d-flex justify-content-between mb-2">
        <div class="d-flex gap-2">
            <input type="text" id="caritxt" class="form-control" placeholder="Cari SJ..." style="width: 250px;" onkeyup="refreshTableBody()">
            <button type="button" id="caribtn" class="btn btn-primary" onclick="refreshTableBody()">Cari</button>
        </div>
        <?php if ($akses['Add']): ?>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="clearObj()">
                <i class="fa-solid fa-plus"></i> Surat Jalan Baru
            </button>
        <?php endif; ?>
    </div>
   
    <div class="table-responsive" style="max-height: 80vh; overflow-y: auto; border: 1px solid #dee2e6;">
        <table class="table table-bordered table-hover table-striped mb-0">
            <thead class="table-primary text-center">
                <tr>
                    <th>KODE SJ</th>
                    <th>TANGGAL</th>                   
                    <th>CUSTOMER</th>
                    <th>KODE SO</th>
                    <th>TOTAL BARANG</th>
                    <?php if ($akses['Edit'] || $akses['Delete']): ?>
                        <th style="width: 120px;">AKSI</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="daftarsj" style="font-size:12px">
                <?php
                $sql = "SELECT * FROM viewDaftarSJ WHERE Aktif='1' ORDER BY KodeSJ DESC LIMIT 100";
                $result = $conn->query($sql);
                
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $kodesj = $row['KodeSJ'];
                        $kodeso = $row['idSO'];
                        $tanggal = $row['Tanggal'];
                        $idcust = $row['idCustomer'];
                        $nmcust = htmlspecialchars($row['NamaCustomer'], ENT_QUOTES);
                        $catatan = htmlspecialchars($row['Catatan'] ?? '', ENT_QUOTES);
                        $sub = $row['SubTotal'];
                        $ppn = $row['PPN'];
                        $grand = $row['GrandTotal'];

                        echo "<tr style='height:30px'>";
                        echo "<td class='text-center'>$kodesj</td>";
                        echo "<td class='text-center'>".date('d M Y', strtotime($tanggal))."</td>";
                        echo "<td>$nmcust</td>";
                        echo "<td class='text-center'>$kodeso</td>";
                        echo "<td class='text-end'>".number_format($row['JumlahBarang'])."</td>";

                        if ($akses['Edit'] || $akses['Delete']) {
                            echo "<td class='text-center'>";
                            if ($akses['Edit']) {
                                echo "<button class='btn btn-sm btn-primary me-1' onclick=\"fillform('$kodesj','$kodeso','$tanggal','$idcust','$nmcust','$catatan','$sub','$ppn','$grand')\"><i class='fa-solid fa-pen'></i></button>";
                            }
                            if ($akses['Delete']) {
                                echo "<button class='btn btn-sm btn-danger' onclick=\"deleteSJ('$kodesj')\"><i class='fa-solid fa-trash'></i></button>";
                            }
                            echo "</td>";
                        }
                        echo "</tr>";
                    }
                } else {
                    $cols = 5 + (($akses['Edit'] || $akses['Delete']) ? 1 : 0);
                    echo "<tr><td colspan='$cols' class='text-center'>Data tidak ditemukan !</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="sjModalLabel">Surat Jalan (Delivery)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="container">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>Pilih Kode SO :</label>
                            <div style="position: relative;">
                                <input type="text" id="so" class="form-control" placeholder="Cari SO..." autocomplete="off">
                                <ul id="customerList" class="list-group"></ul>
                            </div>
                            
                            <label class="mt-2">Nama Customer :</label>
                            <input type="text" id="customer" class="form-control" readonly>
                        </div>
                        <div class="col-md-4">
                            <label>Tanggal :</label>
                            <input type="date" id="tanggal" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label>Nomor SJ :</label>
                            <input type="text" id="nomorSJ" class="form-control text-center fw-bold" readonly>
                        </div>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered" id="detailBarang">
                            <thead class="table-secondary text-center">
                                <tr>
                                    <th>Nama Barang</th>
                                    <th width="15%">Qty Kirim</th>
                                    <th width="20%">Harga Jual</th>
                                    <th width="20%">Total</th>
                                </tr>
                            </thead>
                            <tbody style="background-color:lightblue;"></tbody>
                        </table>
                    </div>
                                                     
                    <div class="row">
                        <div class="col-md-6">
                            <label>Catatan :</label>
                            <textarea id="catatan" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label>Sub Total</label>
                            <input type="text" id="subtotal" class="form-control text-end" readonly value="0">
                            
                            <label>PPN (%)</label>
                            <input type="text" id="ppn" class="form-control text-end" readonly value="0">
                            
                            <label>Grand Total</label>
                            <input type="text" id="grandtotal" class="form-control text-end fw-bold" readonly value="0">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger me-auto" onclick="deleteSJ()">Hapus Data</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveSJ()">Save</button>
            </div>
        </div>
    </div>
</div>

<script>    
    function openEditForm(row) {
        const d = row.dataset;
        fillform(d.kodesj, d.kodeso, d.tanggal, d.idcust, d.nmcust, d.catatan, d.subtotal, d.ppn, d.grandtotal);
    }

    function fillform(kodesj, kodeso, tanggal, idcustomer, nmcustomer, Catatan, SubTotal, PPN, GrandTotal) {
        const table = document.querySelector("#detailBarang tbody");
        table.innerHTML = ''; 
        document.getElementById('customerList').style.display = 'none';
      
        document.getElementById('nomorSJ').value = kodesj;  
        document.getElementById('nomorSJ').dataset.idsj = kodesj;  
        
        document.getElementById('customer').value = nmcustomer;
        document.getElementById('customer').dataset.idso = kodeso;
        document.getElementById('customer').dataset.idcustomer = idcustomer;
        document.getElementById('customer').dataset.namacustomer = nmcustomer;
        
        document.getElementById('tanggal').value = tanggal;                        
        document.getElementById('catatan').value = Catatan;     
        document.getElementById('subtotal').value = FormatUSNumeric(SubTotal);     
        document.getElementById('grandtotal').value = FormatUSNumeric(GrandTotal);     
        document.getElementById('ppn').value = FormatUSNumeric(PPN);     
        
        document.getElementById('so').value = kodeso;  
        document.getElementById('so').disabled = true; // Mode Edit tidak boleh ganti SO

        document.getElementById('sjModalLabel').innerText = "Edit Surat Jalan";
        
        $.ajax({
            url: 'Transaksi/SJCari.php?abt=sjdt&kodesj='+kodesj, 
            type: 'GET',
            dataType:'json',
            success: function (response) {
                if (response.status === 'success') {
                    response.data.forEach(item => {                       
                        const rowNumber = table.rows.length + 1;
                        const uniqueId = `${Date.now()}-${rowNumber}`;

                        const row = document.createElement("tr");
                        row.innerHTML = `
                             <td>
                                <input type="text" class="form-control form-control-sm" readonly value="${item.NamaBarang}">
                                <input type="hidden" name="kodebarang[]" value="${item.idBarang}" data-iddetailso="${item.idDetailSO}" data-idbarang="${item.idBarang}" data-namabarang="${item.NamaBarang}">
                            </td>
                            <td><input type="text" name="kuantitas[]" class="form-control form-control-sm text-end" value="${item.Jumlah}" oninput="hitungTotal(this)"></td>
                            <td><input type="text" name="harga_satuan[]" class="form-control form-control-sm text-end" value="${FormatUSNumeric(item.HargaJual)}" readonly></td>
                            <td><input type="text" name="total[]" class="form-control form-control-sm text-end" value="${FormatUSNumeric(item.TotalHarga)}" readonly></td>                            
                        `;
                        table.appendChild(row);                                

                    });
                }
            },
            error: function() {
                console.error("Terjadi kesalahan saat memuat data.");
            }
        });
        
        var modal = new bootstrap.Modal(document.getElementById('supplierModal'), {});             
        modal.show();
    }

    function hitungTotal(el) {
        const row = el.closest("tr");
        const qty = parseFloat(el.value.replace(/,/g, '')) || 0;
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

    // Cari SO
    document.getElementById('so').addEventListener('keyup', function(event) {
        if (event.key === 'Enter') {
            fetch('Transaksi/SJCari.php?abt=so&so=' + encodeURIComponent(this.value))
                .then(response => response.text())
                .then(data => {                   
                    const list = document.getElementById('customerList');
                    list.innerHTML = data;
                    list.style.display = 'block';
                });
        }
    });

    function CloseCustomerList() {
        document.getElementById('customerList').style.display = 'none';
    }

    function PilihSO(kode, nama, kodeso, subtotal, ppn, grandtotal) {
        document.getElementById('customer').value = nama;
        document.getElementById('so').value = kodeso;
        document.getElementById('customer').dataset.idso = kodeso;
        document.getElementById('customer').dataset.idcustomer = kode;
        document.getElementById('customer').dataset.namacustomer = nama;
        
        document.getElementById('customerList').style.display = 'none';
        document.getElementById('subtotal').value = FormatUSNumeric(subtotal);    
        document.getElementById('grandtotal').value = FormatUSNumeric(grandtotal);
        document.getElementById('ppn').value = FormatUSNumeric(ppn);                
        
        const table = document.querySelector("#detailBarang tbody");
        table.innerHTML = '';
        
        fetch('Transaksi/SOCari.php?abt=sodt&kodeso=' + kodeso)
            .then(res => res.json())
            .then(response => {
                if (response.status === 'success') {
                    response.data.forEach(item => {   
                        // Hitung Sisa Kirim (JmlSO - JmlSJ yg sudah ada)
                        const sisa = item.JumlahDetSo - item.JumlahSJ;
                        if (sisa <= 0) return; // Jangan tampilkan jika sudah kirim semua

                        const row = document.createElement("tr");
                        row.innerHTML = `
                            <td>
                                <input type="text" class="form-control form-control-sm" readonly value="${item.NamaBarang}">
                                <input type="hidden" name="kodebarang[]" value="${item.idBarang}" data-iddetailso="${item.AutoNum}" data-idbarang="${item.idBarang}" data-namabarang="${item.NamaBarang}">
                            </td>
                            <td><input type="text" name="kuantitas[]" class="form-control form-control-sm text-end" value="${sisa}" oninput="hitungTotal(this)"></td>
                            <td><input type="text" name="harga_satuan[]" class="form-control form-control-sm text-end" value="${FormatUSNumeric(item.HargaJual)}" readonly></td>
                            <td><input type="text" name="total[]" class="form-control form-control-sm text-end" value="${FormatUSNumeric(item.TotalHarga)}" readonly></td>                            
                        `;
                        table.appendChild(row);
                    });
                }
            });
    }

    function GetNotaNo() {
         const today = document.getElementById('tanggal').value;
         fetch('Database/FunctionDatabase.php?func=getnotano&tanggal=' + today + '&jenisnota=SJ')
             .then(res => res.text())
             .then(data => document.getElementById('nomorSJ').value = data);
    }

    function clearObj() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('tanggal').value = today;
        document.getElementById('so').value = '';
        document.getElementById('so').disabled = false;
        document.getElementById('customer').value = '';
        document.getElementById('customer').dataset.idso = '';
        document.getElementById('customer').dataset.idcustomer= '';
        document.getElementById('nomorSJ').dataset.idsj= '';
        
        document.querySelector("#detailBarang tbody").innerHTML = '';
        document.getElementById('customerList').style.display = 'none';
        
        document.getElementById('catatan').value = '';
        document.getElementById('subtotal').value = '0';
        document.getElementById('ppn').value = '11';
        document.getElementById('grandtotal').value = '0';
        
        document.getElementById('sjModalLabel').innerText = "Surat Jalan Baru";
        GetNotaNo();
    }

    function saveSJ() {
        const rows = document.getElementsByName('kodebarang[]');
        if (rows.length === 0) { alert('Tidak ada barang!'); return; }

        const detail = [];
        let totalBarang = 0;
        const namabarang = document.getElementsByName('kodebarang[]'); // Note: ini ambil hidden input
        const jumlah = document.getElementsByName('kuantitas[]');
        const harga = document.getElementsByName('harga_satuan[]');
        const total = document.getElementsByName('total[]');
        
        for (let i = 0; i < rows.length; i++) {
            let qty = parseInt(jumlah[i].value.replace(/,/g, '')) || 0;
            let hrg = parseFloat(harga[i].value.replace(/,/g, '')) || 0;
            let tot = parseFloat(total[i].value.replace(/,/g, '')) || 0;
            
            if (qty <= 0) { alert('Qty tidak boleh 0'); return; }

            detail.push({
                iddetailso: rows[i].dataset.iddetailso,
                kodebarang: rows[i].dataset.idbarang,
                namaBarang: rows[i].dataset.namabarang,
                jumlah: qty,
                harga: hrg,
                total: tot
            });
            totalBarang += qty;
        }
        
        const payload = {
            act: 'save',
            idsj: document.getElementById('nomorSJ').dataset.idsj || '',
            kodecustomer: document.getElementById('customer').dataset.idcustomer,
            tanggal: document.getElementById('tanggal').value,
            kodeso: document.getElementById('customer').dataset.idso,
            totalbarang: totalBarang,
            subtotal: document.getElementById('subtotal').value.replace(/,/g, ''),
            ppn: document.getElementById('ppn').value.replace(/,/g, ''),
            grandtotal: document.getElementById('grandtotal').value.replace(/,/g, ''),
            catatan: document.getElementById('catatan').value,
            detailbarang: detail
        };

        fetch('Transaksi/SJSave.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Surat Jalan berhasil disimpan!');
                location.reload();
            } else {
                alert('Gagal: ' + data.error);
            }
        });
    }

    function deleteSJ(kode = null) {
        const idsj = kode || document.getElementById('nomorSJ').dataset.idsj;
        if(!idsj) return;
        if(!confirm('Hapus Surat Jalan '+idsj+'?')) return;

        fetch('Transaksi/SJSave.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ act: 'delete', idsj: idsj })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
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
            url: 'Transaksi/SJCari.php?abt=cari&cari='+ encodeURIComponent(cari),
            type: 'GET',
            success: function (response) {                              
                $('#daftarsj').html(response);
            }
        });
    }       
</script>
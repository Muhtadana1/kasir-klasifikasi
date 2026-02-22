<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$akses = $_SESSION['hak_akses']['PO'] ?? ['View' => 0, 'Add' => 0, 'Edit' => 0, 'Delete' => 0];

if (!$akses['View']) {
    echo "<div class='alert alert-danger'>Akses Ditolak.</div>";
    exit;
}
?>

<div style="padding: 10px">
    <div class="d-flex justify-content-between mb-2">
        <div class="d-flex gap-2">
            <input type="text" id="caritxt" class="form-control" placeholder="Cari PO..." style="width: 250px;" onkeyup="refreshTableBody()" />
            <button type="button" id="caribtn" class="btn btn-primary" onclick="refreshTableBody()">Cari</button>
        </div>
        
        <?php if ($akses['Add']): ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="clearObj();">
                <i class="fa-solid fa-plus"></i> PO Baru
            </button>
        <?php endif; ?>
    </div>
   
    <div class="table-responsive" style="max-height: 80vh; overflow-y: auto; border: 1px solid #dee2e6;">
        <table class="table table-bordered table-hover table-striped mb-0">
            <thead class="table-primary text-center">
                <tr>
                    <th>KODE PO</th>
                    <th>TANGGAL</th>                   
                    <th>SUPPLIER</th>
                    <th>TOTAL BARANG</th>
                    <th>GRAND TOTAL</th>
                    <th>STATUS BL</th>
                    <?php if ($akses['Edit'] || $akses['Delete']): ?>
                        <th style="width: 120px;">AKSI</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="daftarpo" style="font-size:12px">
                <?php
                $cari = $_GET['cari'] ?? ''; // Jika dipanggil via AJAX, bisa pakai $_GET
                // Logic query dipindah ke sini agar langsung render (atau via AJAX call)
                // Disini saya tulis render langsung agar file ini mandiri
                $sql = "SELECT *,IF(JumlahBL=0,'BELUM',IF(JumlahBL<TotalBarang,'PROSES','SELESAI')) AS StatusBL 
                        FROM viewDaftarPO WHERE Aktif='1' ORDER BY KodePO DESC LIMIT 100"; 
                
                $result = $conn->query($sql);
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        // Persiapan Data untuk JS
                        $kodepo = $row['KodePO'];
                        $tanggal = $row['Tanggal'];
                        $idsupp = $row['idSupplier'];
                        $nmsub = htmlspecialchars($row['NamaSupplier'], ENT_QUOTES);
                        $catatan = htmlspecialchars($row['Catatan'] ?? '', ENT_QUOTES);
                        $sub = $row['SubTotal'];
                        $ppn = $row['PPN'];
                        $grand = $row['GrandTotal'];
                        $stat = $row['StatusBL']; // BELUM, PROSES, SELESAI

                        echo "<tr style='height:30px'>";
                        echo "<td class='text-center'>$kodepo</td>";
                        echo "<td class='text-center'>".date('d M Y', strtotime($tanggal))."</td>";
                        echo "<td>$nmsub</td>";
                        echo "<td class='text-end'>".number_format($row['TotalBarang'])."</td>";
                        echo "<td class='text-end'>".number_format($grand, 0, ',', '.')."</td>";
                        echo "<td class='text-center'>$stat</td>";

                        if ($akses['Edit'] || $akses['Delete']) {
                            echo "<td class='text-center'>";
                            
                            // LOGIKA PENGUNCIAN: Jika Status != BELUM, Kunci.
                            if ($stat !== 'BELUM') {
                                echo "<span class='badge bg-secondary'><i class='fa-solid fa-lock'></i> Terkunci</span>";
                            } else {
                                if ($akses['Edit']) {
                                    // Panggil fungsi fillform (yg sudah ada di JS bawah)
                                    echo "<button class='btn btn-sm btn-primary me-1' onclick=\"fillform('$kodepo','$tanggal','$idsupp','$nmsub','$catatan','$sub','$ppn','$grand','$stat')\"><i class='fa-solid fa-pen'></i></button>";
                                }
                                if ($akses['Delete']) {
                                    echo "<button class='btn btn-sm btn-danger' onclick=\"deletePO('$kodepo')\"><i class='fa-solid fa-trash'></i></button>";
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



<div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="height: 40px; background-color :lightblue;">
                <h5 class="modal-title" id="supplierModalLabel" style="font-size: 19px; font-weight: bold;">Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="container">
                    <div class="row">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-group row" style="padding:2px">
                                    <label for="supplier" class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">Nama Supplier :</label>
                                    <div class="col-sm-8">
                                        <div style="position: relative; display: inline-block; width: 100%;">
                                            <input type="text" id="supplier" class="form-control" placeholder="Masukkan nama supplier" style="font-size:12px;" onclick="CloseSupplierList();" autocomplete="off">
                                            <span style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;">
                                                <i class="fa-solid fa-chevron-down"></i>
                                            </span>
                                        </div>
                                        <ul id="supplierList" class="list-group" style="position: absolute; z-index: 1000; display: none; width: 70%; font-size: 12px;"></ul>
                                    </div>
                                </div>

                                <div class="form-group row" style="padding:2px">
                                    <label for="tanggal" class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">Tanggal :</label>
                                    <div class="col-sm-8">
                                        <input type="date" id="tanggal" class="form-control" style="width: 150px; font-size: 12px;">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group row" style="padding:2px">
                                    <label id="statusbllbl" for="statusbl" class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right; display: none;">Status BL :</label>
                                    <div class="col-sm-8">
                                        <input type="text" id="statusbl" class="form-control" style="font-size: 12px; display: none; width:74px; text-align:center" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group row">
                                    <label for="nomorPO" class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">Nomor PO :</label>
                                    <div class="col-sm-6">
                                        <input type="text" id="nomorPO" class="form-control" readonly style="font-size: 14px; text-align:center">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <table class="table table-bordered" id="detailBarang">
                                <thead style="background-color:burlywood; font-size:12px; text-align:center">
                                    <tr>
                                        <th>Nama Barang</th>
                                        <th>Kuantitas</th>
                                        <th>Harga Satuan</th>
                                        <th>Total</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="detailpo" style="background-color :lightblue" ;>
                                    </tbody>
                            </table>
                            <div class="d-flex justify-content-end">
                                <button type="button" id="btnAddItem" class="btn btn-primary col-sm-2" onclick="tambahBaris()" style="float: right;">Tambah Barang</button>
                            </div>
                        </div>

                        <div class="row ">
                            <div class="col-md-4">
                                <div class="form-group row" style="padding:2px">
                                    <label for="catatan" class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">Catatan :</label>
                                    <div class="col-sm-8">
                                        <textarea class="form-control" id="catatan" placeholder="Keterangan" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">

                            </div>
                            <div class="col-md-4">
                                <div class="form-group row" style="padding:2px">
                                    <label for="subtotal" class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">Sub Total :</label>
                                    <div class="col-sm-5">
                                        <input type="text" id="subtotal" class="form-control" readonly style="font-size: 12px; text-align:right;" value="0">
                                    </div>
                                </div>
                                <div class="form-group row" style="padding:2px">
                                    <label for="ppn" class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">PPN (%) :</label>
                                    <div class="col-sm-5">
                                        <input type="text" id="ppn" class="form-control" style="font-size: 12px; text-align: right;" oninput="hitungGrandTotal()" value="11" autocomplete="off">
                                    </div>
                                </div>
                                <div class="form-group row" style="padding:2px">
                                    <label for="grantotal" class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">Grand Total :</label>
                                    <div class="col-sm-5">
                                        <input type="text" id="grandtotal" class="form-control" style="font-size: 12px; text-align: right;" value="0" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnDeletePO" class="btn btn-secondary" style="margin-right: auto;" onclick="deletePO()">Delete</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" id="btnSavePO" class="btn btn-primary" onclick="savePO()">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Fungsi Fillform dengan Logika Penguncian (Locking)
    function fillform(kodepo, tanggal, idsupplier, nmsupplier, Catatan, SubTotal, PPN, GrandTotal, statusbl) {

        const table = document.querySelector("#detailBarang tbody");
        table.innerHTML = '';
        document.getElementById('supplierList').style.display = 'none';

        // Isi form dengan data
        document.getElementById('nomorPO').value = kodepo;
        document.getElementById('statusbl').value = statusbl;
        document.getElementById("statusbl").style.display = "block";
        document.getElementById("statusbllbl").style.display = "block";
        document.getElementById('nomorPO').dataset.kodepo = kodepo;
        document.getElementById('supplier').value = nmsupplier;
        document.getElementById('supplier').dataset.idsupplier = idsupplier;
        document.getElementById('supplier').dataset.namasupplier = nmsupplier;
        document.getElementById('tanggal').value = tanggal;
        document.getElementById('catatan').value = Catatan;
        document.getElementById('subtotal').value = FormatUSNumeric(SubTotal);
        document.getElementById('grandtotal').value = FormatUSNumeric(GrandTotal);
        document.getElementById('ppn').value = FormatUSNumeric(PPN);

        // --- LOGIKA PENGUNCIAN UI (LOCKING) ---
        const btnSave = document.getElementById('btnSavePO');
        const btnDel = document.getElementById('btnDeletePO');
        const btnAdd = document.getElementById('btnAddItem');
        const inputs = ['supplier', 'tanggal', 'catatan', 'ppn'];

        if (statusbl !== 'BELUM') {
            // JIKA SUDAH DIPROSES: KUNCI SEMUA
            btnSave.style.display = 'none';
            btnDel.style.display = 'none';
            btnAdd.style.display = 'none';

            inputs.forEach(id => document.getElementById(id).disabled = true);
            document.getElementById('supplierModalLabel').innerText = "Purchase Order (READ ONLY - SUDAH DIPROSES)";
            document.getElementById('supplierModalLabel').style.color = "red";
        } else {
            // JIKA MASIH BELUM: BUKA AKSES
            btnSave.style.display = 'block';
            btnDel.style.display = 'block';
            btnAdd.style.display = 'block';

            inputs.forEach(id => document.getElementById(id).disabled = false);
            document.getElementById('supplierModalLabel').innerText = "Purchase Order";
            document.getElementById('supplierModalLabel').style.color = "black";
        }
        // -----------------------------

        $.ajax({
            url: 'Transaksi/POCari.php?abt=podt&kodepo=' + kodepo,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    response.data.forEach(item => {
                        const table = document.querySelector("#detailBarang tbody");
                        const rowNumber = table.rows.length + 1;
                        const uniqueId = `${Date.now()}-${rowNumber}`;

                        // Jika terkunci, tombol hapus per baris juga harus hilang
                        let btnHapusBaris = `<button type="button" class="btn btn-danger" onclick="hapusBaris(this)" style="font-size: 10px; padding: 5px 10px; width: auto;">Hapus</button>`;
                        if (statusbl !== 'BELUM') {
                            btnHapusBaris = '';
                        }
                        
                        // Jika terkunci, input menjadi disabled
                        const isDisabled = (statusbl !== 'BELUM') ? 'disabled' : '';

                        const row = document.createElement("tr");
                        row.innerHTML = `
                            <td><input type="text" name="namabarang[]" class="form-control" style="font-size: 10px; text-align: left;" id="namabarang${uniqueId}" value="${item.NamaBarang}" autocomplete="off" ${isDisabled}>
                                <input type="hidden" name="kodebarang[]" id="kodebarang${uniqueId}" value="${item.idBarang}" data-idbarang="${item.idBarang}" data-iddetailpo="${item.AutoNumDetPO}" data-namabarang="${item.NamaBarang}"/>
                                <ul id="barangList"  name="barangList[]" class="list-group" style="position: absolute; z-index: 1000; display: none; width: 60%; font-size: 12px; background-color = lightblue;"></ul>
                            </td>
                            <td><input type="number" name="kuantitas[]" id="jumlah${uniqueId}"class="form-control" style="font-size: 10px; text-align: right;" oninput="hitungTotal(this)" value="${item.JumlahBeli}" autocomplete="off" ${isDisabled}></td>
                            <td><input type="text" name="harga_satuan[]" id="hargabeli${uniqueId}" style="font-size: 10px; text-align: right;" class="form-control" oninput="hitungTotal(this)" value="${FormatUSNumeric(item.HargaBeliDetPO)}" autocomplete="off" ${isDisabled}></td>
                            <td><input type="text" name="total[]" class="form-control" style="font-size: 10px; text-align: right;" value="${FormatUSNumeric(item.TotalHarga)}" readonly></td>
                            <td style="text-align: center; vertical-align: middle;">${btnHapusBaris}</td>
                        `;
                        table.appendChild(row);

                        // Hanya pasang event listener edit barang jika TIDAK terkunci
                        if (statusbl === 'BELUM') {
                            const inputNamaBarang = row.querySelector('input[name="namabarang[]"]');
                            const barangList = row.querySelector('ul[name="barangList[]"]');
                            const hargabeli = row.querySelector('input[name="harga_satuan[]"]');

                            inputNamaBarang.addEventListener('keydown', function(event) {
                                if (event.key === "Enter") {
                                    query = inputNamaBarang.value;
                                    event.preventDefault();
                                    fetch('Transaksi/POCari.php?abt=brng&id=' + encodeURIComponent(uniqueId) + '&brng=' + encodeURIComponent(query))
                                        .then(response => response.text())
                                        .then(data => {
                                            const processedData = data.trim().toLowerCase();
                                            if (processedData === 'eof') {
                                                alert('Barang yang Anda cari tidak ditemukan !');
                                                return;
                                            }
                                            barangList.innerHTML = data;
                                            barangList.style.display = 'block';
                                        })
                                        .catch(error => {
                                            console.error('Error fetching data:', error);
                                        });
                                }
                            });

                            hargabeli.addEventListener('input', function(e) {
                                let raw = e.target.value.replace(/[^0-9.]/g, '');
                                const parts = raw.split('.');
                                if (parts.length > 2) {
                                    raw = parts[0] + '.' + parts[1];
                                }
                                e.target.value = raw;
                            });
                            hargabeli.addEventListener('blur', function(e) {
                                e.target.value = FormatUSNumeric(e.target.value);
                            });
                        }
                    });
                }
            },
            error: function() {
                console.error("Terjadi kesalahan saat memuat data.");
            }
        });

        var supplierModal = new bootstrap.Modal(document.getElementById('supplierModal'), {});
        supplierModal.show();
    }


    function filterAngka(input) {
        input.value = input.value.replace(/[^0-9.]/g, '');
        const parts = input.value.split('.');
        if (parts.length > 2) {
            input.value = parts[0] + '.' + parts[1];
        }
    }

    function tambahBaris() {
        const table = document.querySelector("#detailBarang tbody");
        const rowNumber = table.rows.length + 1;
        const uniqueId = `${Date.now()}-${rowNumber}`;

        const row = document.createElement("tr");
        row.innerHTML = `
            <td><input type="text" name="namabarang[]" class="form-control" style="font-size: 10px; text-align: left;" id="namabarang${uniqueId}" autocomplete="off">
                <input type="hidden" name="kodebarang[]" id="kodebarang${uniqueId}" data-iddetailpo=""/>
                <ul id="barangList"  name="barangList[]" class="list-group" style="position: absolute; z-index: 1000; display: none; width: 60%; font-size: 12px; background-color = lightblue;"></ul>
            </td>
            <td><input type="number" name="kuantitas[]" id="jumlah${uniqueId}"class="form-control" style="font-size: 10px; text-align: right;" value="0" oninput="hitungTotal(this)" autocomplete="off"></td>
            <td><input type="text" name="harga_satuan[]" id="hargabeli${uniqueId}" style="font-size: 10px; text-align: right;" class="form-control" value="0" oninput="hitungTotal(this)" autocomplete="off"></td>
            <td><input type="text" name="total[]" class="form-control" style="font-size: 10px; text-align: right;" value="0" readonly></td>
            <td style="text-align: center; vertical-align: middle;"><button type="button" class="btn btn-danger" onclick="hapusBaris(this)" style="font-size: 10px; padding: 5px 10px; width: auto;">Hapus</button></td>
        `;
        table.appendChild(row);

        const inputNamaBarang = row.querySelector('input[name="namabarang[]"]');
        const barangList = row.querySelector('ul[name="barangList[]"]');

        inputNamaBarang.addEventListener('keydown', function(event) {
            if (event.key === "Enter") {
                query = inputNamaBarang.value;
                event.preventDefault();
                fetch('Transaksi/POCari.php?abt=brng&id=' + encodeURIComponent(uniqueId) + '&brng=' + encodeURIComponent(query))
                    .then(response => response.text())
                    .then(data => {
                        const processedData = data.trim().toLowerCase();
                        if (processedData === 'eof') {
                            alert('Barang yang Anda cari tidak ditemukan !');
                            return;
                        }
                        barangList.innerHTML = data;
                        barangList.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error fetching data:', error);
                    });
            }
        });

        const hargabeli = row.querySelector('input[name="harga_satuan[]"]');
        hargabeli.addEventListener('input', function(e) {
            let raw = e.target.value.replace(/[^0-9.]/g, '');
            const parts = raw.split('.');
            if (parts.length > 2) {
                raw = parts[0] + '.' + parts[1];
            }
            e.target.value = raw;
        });

        hargabeli.addEventListener('blur', function(e) {
            e.target.value = FormatUSNumeric(e.target.value);
        });
    }

    function hitungTotal(el) {
        const row = el.closest("tr");
        let kuantitas = parseFloat(row.querySelector('input[name="kuantitas[]"]').value.replace(/,/g, '')) || 0;
        let hargaSatuan = parseFloat(row.querySelector('input[name="harga_satuan[]"]').value.replace(/,/g, '')) || 0;
        const total = row.querySelector('input[name="total[]"]');
        total.value = FormatUSNumeric(kuantitas * hargaSatuan);
        hitungSubtotal();
    }

    function hitungSubtotal() {
        let subtotal = 0;
        document.querySelectorAll('input[name="total[]"]').forEach(input => {
            const nilai = parseFloat(input.value.replace(/,/g, '')) || 0;
            subtotal += nilai;
        });
        document.getElementById('subtotal').value = FormatUSNumeric(subtotal);
        hitungGrandTotal();
    }

    function hitungGrandTotal() {
        const subtotal = parseFloat(document.getElementById('subtotal').value.replace(/,/g, '')) || 0;
        const ppn = parseFloat(document.getElementById('ppn').value.replace(/,/g, '')) || 0;
        const grandtotal = subtotal + (subtotal * ppn / 100);
        document.getElementById('grandtotal').value = FormatUSNumeric(grandtotal);
    }

    function hapusBaris(el) {
        el.closest('tr').remove();
        hitungSubtotal();
    }


    document.getElementById('supplier').addEventListener('keyup', function(event) {
        const query = event.target.value;
        if (event.key === 'Enter') {
            fetch('Transaksi/POCari.php?abt=supp&supp=' + encodeURIComponent(query))
                .then(response => response.text())
                .then(data => {
                    const supplierList = document.getElementById('supplierList');
                    supplierList.innerHTML = data;
                    supplierList.style.display = 'block';
                });
        }
    });

    function CloseSupplierList() {
        document.getElementById('supplierList').style.display = 'none';
    }

    function GetNotaNo() {
        const tanggal = document.getElementById('tanggal').value;
        const jenisNota = 'PO';
        fetch('Database/FunctionDatabase.php?func=getnotano&tanggal=' + encodeURIComponent(tanggal) + '&jenisnota=' + encodeURIComponent(jenisNota))
            .then(response => response.text())
            .then(data => {
                document.getElementById('nomorPO').value = data;
            });
    }

    function PilihSupplier(kode, nama) {
        document.getElementById('supplier').value = nama;
        document.getElementById('supplier').dataset.idsupplier = kode;
        document.getElementById('supplier').dataset.namasupplier = nama;
        document.getElementById('supplierList').style.display = 'none';
    }

    function PilihBarang(kodebarang, namabarang, satuan, kategori, id, hargabeli) {
        document.getElementById('namabarang' + id).value = namabarang;
        document.getElementById('namabarang' + id).dataset.idbarang = kodebarang;
        document.getElementById('kodebarang' + id).value = kodebarang;
        document.getElementById('kodebarang' + id).dataset.idbarang = kodebarang;
        document.getElementById('kodebarang' + id).dataset.iddetailpo = '';
        document.getElementById('hargabeli' + id).value = FormatUSNumeric(hargabeli);
        document.querySelectorAll('ul[name="barangList[]"]').forEach(ul => {
            ul.style.display = 'none';
        });
        hitungTotal(document.getElementById('hargabeli' + id));
    }

    // Fungsi Reset Form (Untuk PO Baru)
    function clearObj() {
        let today = new Date();
        let tanggalHariIni = today.toISOString().split('T')[0];

        document.getElementById('tanggal').value = tanggalHariIni;
        document.getElementById('supplier').value = '';
        document.getElementById("statusbl").style.display = "none";
        document.getElementById("statusbllbl").style.display = "none";
        document.getElementById("statusbl").value = 'BELUM'; // Default
        document.getElementById('supplier').dataset.idsupplier = '';
        document.getElementById('supplier').dataset.namasupplier = '';
        GetNotaNo();
        document.getElementById('nomorPO').dataset.kodepo = '';
        const table = document.querySelector("#detailBarang tbody");
        table.innerHTML = '';
        document.getElementById('supplierList').style.display = 'none';
        document.getElementById('subtotal').value = '0';
        document.getElementById('ppn').value = '11';
        document.getElementById('grandtotal').value = '0';
        document.getElementById('catatan').value = '';

        // --- RESET UI KE MODE EDIT (BUKA KUNCI) ---
        document.getElementById('btnSavePO').style.display = 'block';
        document.getElementById('btnDeletePO').style.display = 'none'; // Tombol delete sembunyi utk PO baru
        document.getElementById('btnAddItem').style.display = 'block';
        
        const inputs = ['supplier', 'tanggal', 'catatan', 'ppn'];
        inputs.forEach(id => document.getElementById(id).disabled = false);
        document.getElementById('supplierModalLabel').innerText = "Purchase Order";
        document.getElementById('supplierModalLabel').style.color = "black";
    }

    function savePO() {
        const table = document.querySelector("#detailBarang tbody");
        const rowNumber = table.rows.length;
        const namasupplier = document.getElementById('supplier').value;
        const tanggal = document.getElementById('tanggal').value;

        let subtotal = document.getElementById('subtotal').value.replace(/,/g, '');
        let grandtotal = document.getElementById('grandtotal').value.replace(/,/g, '');
        let ppn = document.getElementById('ppn').value.trim().replace(/,/g, '');

        const idsupplier = document.getElementById('supplier').dataset.idsupplier;
        const namasupplierinv = document.getElementById('supplier').dataset.namasupplier;
        const kodepo = document.getElementById('nomorPO').dataset.kodepo;
        const catatan = document.getElementById('catatan').value.trim();

        if (namasupplier.trim().toLowerCase() == '' || idsupplier == '' || !namasupplier.trim().toLowerCase() == namasupplierinv.trim().toLowerCase()) {
            alert('Tentukan supplier dengan benar !');
            document.getElementById('supplier').focus();
            return;
        } else if (rowNumber == 0) {
            alert('Tidak ada item barang yang akan disimpan !');
            return;
        } else if (ppn == '0') {
            alert('Tentukan nilai PPN(%) dengan benar !')
            document.getElementById('ppn').focus();
            return;
        }

        const namabarang = document.getElementsByName('namabarang[]');
        const kodeBarang = document.getElementsByName('kodebarang[]');
        let jumlah = document.getElementsByName('kuantitas[]');
        let harga = document.getElementsByName('harga_satuan[]');
        let total = document.getElementsByName('total[]');

        const detailbarang = [];
        let totalbarang = 0;

        for (let i = 0; i < kodeBarang.length; i++) {
            detailbarang.push({
                iddetailpo: kodeBarang[i].dataset.iddetailpo,
                kodebarang: kodeBarang[i].dataset.idbarang,
                namaBarang: namabarang[i].value,
                jumlah: parseInt(jumlah[i].value.replace(/,/g, '')),
                harga: parseFloat(harga[i].value.replace(/,/g, '')),
                total: parseFloat(total[i].value.replace(/,/g, ''))
            });
            totalbarang += parseInt(jumlah[i].value.replace(/,/g, ''));
        }

        fetch('Transaksi/POSave.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    act: 'save',
                    kodesupplier: idsupplier,
                    tanggal: tanggal,
                    kodepo: kodepo,
                    totalbarang: totalbarang,
                    subtotal: subtotal,
                    ppn: ppn,
                    grandtotal: grandtotal,
                    catatan: catatan,
                    detailbarang: detailbarang
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Data PO berhasil disimpan!');
                    location.reload();
                } else {
                    alert('Gagal menyimpan data: ' + data.error);
                }
            })
            .catch(error => console.error('Error:', error));

        const modal = bootstrap.Modal.getInstance(document.getElementById('supplierModal'));
        modal.hide();
    }

    function deletePO(kodepoParam) {
        // Jika parameter tidak diberikan (dari modal), ambil dari dataset
        const kodepo = kodepoParam || document.getElementById('nomorPO').dataset.kodepo;

        if (!kodepo || kodepo.trim() === "") {
            alert('Tidak ada PO yang dipilih untuk dihapus.');
            return;
        }

        if (!confirm("Apakah Anda yakin akan menghapus PO " + kodepo + " ?\nData yang sudah dihapus tidak dapat dikembalikan.")) {
            return;
        }

        fetch('Transaksi/POSave.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    act: 'delete',
                    kodepo: kodepo
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('PO ' + kodepo + ' berhasil dihapus.');
                    location.reload();
                } else {
                    alert('Gagal menghapus data: ' + data.error);
                }
            })
            .catch(error => console.error('Error:', error));

        // Cek apakah modal terbuka sebelum mencoba menutupnya
        const modalEl = document.getElementById('supplierModal');
        if (modalEl.classList.contains('show')) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();
        }
    }

    function refreshTableBody() {
        const cari = document.getElementById('caritxt').value;
        $.ajax({
            url: 'Transaksi/POCari.php?abt=cari&cari=' + encodeURIComponent(cari),
            type: 'GET',
            success: function(response) {
                $('#daftarpo').html(response);
            },
            error: function() {
                console.error("Terjadi kesalahan saat memuat data.");
            }
        });
    }
</script>
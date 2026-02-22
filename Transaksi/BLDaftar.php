<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$akses = $_SESSION['hak_akses']['BL'] ?? ['View' => 0, 'Add' => 0, 'Edit' => 0, 'Delete' => 0];

if (!$akses['View']) { echo "<div class='alert alert-danger'>Akses Ditolak.</div>"; exit; }
?>

<div style="padding: 10px">    
    <div class="d-flex justify-content-between mb-2">
        <div class="d-flex gap-2">
            <input type="text" id="caritxt" class="form-control" placeholder="Cari BL..." style="width: 250px;" onkeyup="refreshTableBody()">
            <button type="button" id="caribtn" class="btn btn-primary" onclick="refreshTableBody()">Cari</button>
        </div>
        <?php if ($akses['Add']): ?>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#blModal" onclick="clearObj()">
             <i class="fa-solid fa-plus"></i> Terima Barang (BL)
            </button>
        <?php endif; ?>
    </div>
   
    <div class="table-responsive" style="max-height: 80vh; overflow-y: auto; border: 1px solid #dee2e6;">
        <table class="table table-bordered table-hover table-striped mb-0">
            <thead class="table-primary text-center">
                <tr>
                    <th>KODE BL</th>
                    <th>TANGGAL</th>                   
                    <th>SUPPLIER</th>
                    <th>KODE PO</th>
                    <th>TOTAL BARANG</th>
                    <?php if ($akses['Edit'] || $akses['Delete']): ?>
                        <th style="width: 120px;">AKSI</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="daftarpo" style="font-size:12px">
                <?php
                $sql = "SELECT * FROM viewDaftarBL ORDER BY KodePenerimaan DESC LIMIT 100";              
                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $kodebl = $row['KodePenerimaan'];
                        $kodepo = $row['idPO'];
                        $tanggal = $row['Tanggal'];
                        $idsupp = $row['idSupplier'];
                        $nmsupp = htmlspecialchars($row['NamaSupplier'], ENT_QUOTES);
                        $catatan = htmlspecialchars($row['Catatan'] ?? '', ENT_QUOTES);
                        $sub = $row['SubTotal'];
                        $ppn = $row['PPN'];
                        $grand = $row['GrandTotal'];

                        echo "<tr style='height:30px'>";
                        echo "<td class='text-center'>$kodebl</td>";
                        echo "<td class='text-center'>".date('d M Y', strtotime($tanggal))."</td>";
                        echo "<td>$nmsupp</td>";
                        echo "<td class='text-center'>$kodepo</td>";
                        echo "<td class='text-end'>".number_format($row['TotalBarang'])."</td>";

                        if ($akses['Edit'] || $akses['Delete']) {
                            echo "<td class='text-center'>";
                            if ($akses['Edit']) {
                                // Panggil fungsi Edit (fillform)
                                echo "<button class='btn btn-sm btn-primary me-1' onclick=\"fillform('$kodebl','$kodepo','$tanggal','$idsupp','$nmsupp','$catatan','$sub','$ppn','$grand')\"><i class='fa-solid fa-pen'></i></button>";
                            }
                            if ($akses['Delete']) {
                                echo "<button class='btn btn-sm btn-danger' onclick=\"deleteBL('$kodebl')\"><i class='fa-solid fa-trash'></i></button>";
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
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="clearObj();">
            BL Baru
        </button>
        <div class="modal fade" id="blModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header" style="height: 40px; background-color :lightblue;">
                        <h5 class="modal-title" id="supplierModalLabel" style="font-size: 19px; font-weight: bold;">Penerimaan PO</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="container">
                            <div class="row">
                               <div class="row mb-3">
                                    <div class="col-md-4">
                                       <div class="form-group row" style="padding:2px">
                                            <label for="supplier" class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">Pilih Kode PO :</label>
                                            <div class="col-sm-8">
                                                <input type="text" id="po" class="form-control" placeholder="Masukkan Kode PO" style="font-size:12px;" onclick="CloseSupplierList();" autocomplete="off">
                                                 <ul id="supplierList" class="list-group" style="position: absolute; z-index: 1000; display: none; width: 70%; font-size: 12px;"></ul>
                                            </div>
                                        </div>

                                        <div class="form-group row" style="padding:2px">
                                            <label for="supplier" class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">Nama Supplier :</label>
                                            <div class="col-sm-8">
                                                <input type="text" id="supplier" class="form-control" readonly style="font-size:12px;"  autocomplete="off">                                                 
                                            </div>
                                        </div>
                                                                    
                                       
                                    </div>
                                    <div class="col-md-4">
                                      
                                    </div>
                                   <div class="col-md-4">
                                        <div class="form-group row">
                                            <label for="nomorBL"  class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">Nomor BL :</label>
                                            <div class="col-sm-6">
                                                <input type="text" id="nomorBL" class="form-control" readonly style="font-size: 14px; text-align:center">
                                            </div>
                                        
                                        </div>
                                        <div class="form-group row">
                                            <label for="tanggal" class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">Tanggal :</label>
                                            <div class="col-sm-6">
                                                    <input type="date" id="tanggal" class="form-control" style="width: 150px; font-size: 12px;">                                                 
                                            </div>
                                        </div>
                                    </div>
                                   
                                       
                                      
                               </div>

                               <div class="row mb-3">
                                    <table class="table table-bordered" id="detailBarang">
                                        <thead  style="background-color:burlywood; font-size:12px; text-align:center" >
                                            <tr>
                                                <th>Nama Barang</th>
                                                <th>Kuantitas</th>
                                                <th>Harga Satuan</th>
                                                <th>Total</th>
                                                
                                            </tr>
                                        </thead>
                                        <tbody id="detailpo" style="background-color :lightblue";>
                                            </tbody>
                                    </table>
                                   
                               </div>
                                                             
                               <div class="row ">
                                   <div class="col-md-4">
                                      <div class="form-group row" style="padding:2px">
                                            <label for="catatan"  class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">Catatan :</label>
                                            <div class="col-sm-8">
                                                <textarea class="form-control" id="catatan" placeholder="Keterangan" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                      
                                    </div>                                                                       
                                    <div class="col-md-4">
                                        <div class="form-group row" style="padding:2px">
                                            <label for="subtotal"  class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">Sub Total :</label>
                                            <div class="col-sm-5">
                                                <input type="text" id="subtotal" class="form-control" readonly style="font-size: 12px; text-align:right;" value="0">
                                            </div>
                                        </div>
                                        <div class="form-group row" style="padding:2px">
                                            <label for="ppn"  class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">PPN (%) :</label>
                                            <div class="col-sm-5">
                                                <input type="text" id="ppn" class="form-control" readonly style="font-size: 12px; text-align:right;" value="0">
                                            </div>
                                        </div>
                                         <div class="form-group row" style="padding:2px">
                                            <label for="grantotal"  class="col-sm-4 col-form-label" style="font-size: 12px; text-align: right;">Grand Total :</label>
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
                        <button type="button" class="btn btn-secondary" style="margin-right: auto;">Delete</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="saveBL()">Save</button>
                    </div>
                </div>
            </div>
        </div>

 <script>    


     function fillform(kodebl, kodepo, tanggal, idsupplier, nmsupplier, Catatan, SubTotal, PPN, GrandTotal) {
                
        const table = document.querySelector("#detailBarang tbody");
        table.innerHTML = ''; // Menghapus semua isi elemen tbody
        document.getElementById('supplierList').style.display = 'none'; // Sembunyikan daftar
      
         document.getElementById('nomorBL').value = kodebl;  
        document.getElementById('nomorBL').dataset.idbl = kodebl;  
        document.getElementById('supplier').value = nmsupplier;
         document.getElementById('supplier').dataset.idpo = kodepo;
        document.getElementById('supplier').dataset.idsupplier = idsupplier;
        document.getElementById('supplier').dataset.namasupplier = nmsupplier;
         document.getElementById('tanggal').value = tanggal;                        
         document.getElementById('catatan').value = Catatan;     
         document.getElementById('subtotal').value = SubTotal;     
        document.getElementById('grandtotal').value = GrandTotal;     
         document.getElementById('ppn').value = PPN;     
         document.getElementById('po').value = kodepo;  
        document.getElementById('po').disabled = true;
        $.ajax({
            url: 'Transaksi/BLCari.php?abt=bldt&kodebl='+kodebl, // URL untuk mengambil data baru
            type: 'GET', // Metode HTTP
            
            // =================================================================
            // TANDA LOKASI PERUBAHAN (FIX TYPO)
            // =================================================================
            dataType:'json', // <-- 'datatype' diubah menjadi 'dataType' (T besar)
            // =================================================================
            // AKHIR DARI LOKASI PERUBAHAN
            // =================================================================

            success: function (response) {
                if (response.status === 'success') {
                    response.data.forEach(item => {                       
                        const table = document.querySelector("#detailBarang tbody");
                        const rowNumber = table.rows.length + 1; // Menentukan nomor baris (jumlah baris + 1)
                        const uniqueId = `${Date.now()}-${rowNumber}`; // Membuat kode unik berdasarkan waktu dan nomor baris



                        const row = document.createElement("tr");
                        row.innerHTML = `
                             <td><input type="text" name="namabarang[]" class="form-control" readonly style="font-size: 10px; text-align: left;" id="namabarang${uniqueId}" value="${item.NamaBarang}" autocomplete="off">
                                <input type="hidden" name="kodebarang[]" id="kodebarang${uniqueId}" value="${item.idBarang}" data-iddetailpo="${item.idDetailPO}" data-idbarang="${item.idBarang}" data-namabarang="${item.NamaBarang}"/>
                                <ul id="barangList"  name="barangList[]" class="list-group" style="position: absolute; z-index: 1000; display: none; width: 60%; font-size: 12px; background-color = lightblue;"></ul>
                
                            </td>
                            <td><input type="number" name="kuantitas[]" id="jumlah${uniqueId}"class="form-control" style="font-size: 10px; text-align: right;"  value="${item.Jumlah}" autocomplete="off" readonly></td>
                            <td><input type="text" name="harga_satuan[]" id="hargabeli${uniqueId}" style="font-size: 10px; text-align: right;" class="form-control"  value="${new Intl.NumberFormat('en-US', { minimumFractionDigits: 0 }).format(item.HargaBeli)}" autocomplete="off" readonly></td>
                            <td><input type="text" name="total[]" class="form-control" style="font-size: 10px; text-align: right;" value="${new Intl.NumberFormat('en-US', { minimumFractionDigits: 0 }).format(item.TotalHarga)}" readonly></td>                            
                        `;
                        table.appendChild(row);                                

                    });
                } else {
                    console.warn("Data tidak ditemukan.");
                }
            },
            error: function() {
                console.error("Terjadi kesalahan saat memuat data.");
            }
        });
        
         var supplierModal = new bootstrap.Modal(document.getElementById('supplierModal'), {});             
    supplierModal.show();
    }


    //event untuk mencari kode PO setelah tekan enter
    document.getElementById('po').addEventListener('keyup', function(event) {
        const query = event.target.value;       
        if (event.key === 'Enter') {
           
            fetch('Transaksi/BLCari.php?abt=po&po=' + encodeURIComponent(query))
                .then(response => response.text())
                .then(data => {                   
                    const supplierList = document.getElementById('supplierList');
                    supplierList.innerHTML = data; // Masukkan data dari PHP
                    supplierList.style.display = 'block'; // Tampilkan daftar
                });
        }
    });

    function CloseSupplierList() {
        document.getElementById('supplierList').style.display = 'none'; // Sembunyikan daftar
    }

     function GetNotaNo() {
         const tanggal = document.getElementById('tanggal').value;
         const jenisNota = 'BL';
         fetch('Database/FunctionDatabase.php?func=getnotano&tanggal=' + encodeURIComponent(tanggal) + '&jenisnota=' + encodeURIComponent(jenisNota))
             .then(response => response.text())
             .then(data => {
                 document.getElementById('nomorBL').value = data; // Set nilai input
             });
     }

    function PilihPO(kode, nama, kodepo,subtotal,ppn,grandtotal) {
        document.getElementById('supplier').value = nama; // Set nilai input
        document.getElementById('po').value = kodepo;
        document.getElementById('supplier').dataset.idpo= kodepo;
        document.getElementById('supplier').dataset.idsupplier = kode;
        document.getElementById('supplier').dataset.namasupplier = nama;
        document.getElementById('supplierList').style.display = 'none'; // Sembunyikan daftar
        document.getElementById('subtotal').value = subtotal;    
        document.getElementById('grandtotal').value = grandtotal;
        document.getElementById('ppn').value = ppn;                
        const table1 = document.querySelector("#detailBarang tbody");
        table1.innerHTML = '';
        $.ajax({
            url: 'Transaksi/POCari.php?abt=podt&kodepo='+kodepo, // URL untuk mengambil data baru
            type: 'GET', // Metode HTTP
            dataType:'json', 

            success: function (response) {
                if (response.status === 'success') {
                    response.data.forEach(item => {                       
                        const table = document.querySelector("#detailBarang tbody");
                        const rowNumber = table.rows.length + 1; // Menentukan nomor baris (jumlah baris + 1)
                        const uniqueId = `${Date.now()}-${rowNumber}`; // Membuat kode unik berdasarkan waktu dan nomor baris                        

                        const row = document.createElement("tr");
                        row.innerHTML = `
                            <td><input type="text" name="namabarang[]" class="form-control" readonly style="font-size: 10px; text-align: left;" id="namabarang${uniqueId}" value="${item.NamaBarang}" autocomplete="off">
                                <input type="hidden" name="kodebarang[]" id="kodebarang${uniqueId}" value="${item.idBarang}" data-iddetailpo="${item.AutoNumDetPO}" data-idbarang="${item.idBarang}" data-namabarang="${item.NamaBarang}"/>
                                <ul id="barangList"  name="barangList[]" class="list-group" style="position: absolute; z-index: 1000; display: none; width: 60%; font-size: 12px; background-color = lightblue;"></ul>
                
                            </td>
                            <td><input type="number" name="kuantitas[]" id="jumlah${uniqueId}"class="form-control" style="font-size: 10px; text-align: right;"  value="${item.JumlahBeli}" autocomplete="off" readonly></td>
                            <td><input type="text" name="harga_satuan[]" id="hargabeli${uniqueId}" style="font-size: 10px; text-align: right;" class="form-control"  value="${new Intl.NumberFormat('en-US', { minimumFractionDigits: 0 }).format(item.HargaBeliDetPO)}" autocomplete="off" readonly></td>
                            <td><input type="text" name="total[]" class="form-control" style="font-size: 10px; text-align: right;" value="${new Intl.NumberFormat('en-US', { minimumFractionDigits: 0 }).format(item.TotalHarga)}" readonly></td>                            
                        `;
                        table.appendChild(row);    
                      
                    });
                } else {
                    console.warn("Data tidak ditemukan.");
                }
            },
            error: function() {
                console.error("Terjadi kesalahan saat memuat data.");
            }
        });

    }

     function PilihBarang(kodebarang, namabarang, satuan, kategori, id, hargabeli) {                 
         //document.querySelectorAll('input[name="namabarang[]"]')[0].value = namabarang;
         document.getElementById('namabarang' + id).value = namabarang;
         document.getElementById('namabarang' + id).dataset.idbarang = kodebarang;
         document.getElementById('kodebarang' + id).value = kodebarang;
         document.getElementById('kodebarang' + id).dataset.idbarang = kodebarang;
         document.getElementById('kodebarang' + id).dataset.iddetailpo = '';
         document.getElementById('hargabeli' + id).value = hargabeli;
         document.querySelectorAll('ul[name="barangList[]"]').forEach(ul => {
                ul.style.display = 'none';
            });
     }

    function clearObj() {
        let today = new Date();
        let tanggalHariIni = today.toISOString().split('T')[0]; // Format tanggal menjadi YYYY-MM-DD

        // Mengisi default nilai pada elemen
        document.getElementById('tanggal').value = tanggalHariIni;
        document.getElementById('po').value = '';
        document.getElementById('po').disabled = false ;
        document.getElementById('supplier').value = ''; // Set nilai input
        document.getElementById('supplier').dataset.idpo = '';
        document.getElementById('supplier').dataset.idsupplier = '';
        document.getElementById('supplier').dataset.namasupplier = '';
        GetNotaNo();
        document.getElementById('nomorBL').dataset.idbl= '';
        const table = document.querySelector("#detailBarang tbody");
        table.innerHTML = ''; // Menghapus semua isi elemen tbody
        document.getElementById('supplierList').style.display = 'none'; // Sembunyikan daftar      
        document.getElementById('catatan').value = '';
        document.getElementById('subtotal').value = '0';
        document.getElementById('ppn').value = '11';
        document.getElementById('grandtotal').value = '0';
    }


     function saveBL() {
         // Ambil data dari form
         const table = document.querySelector("#detailBarang tbody");
         const rowNumber = table.rows.length;
         const namasupplier = document.getElementById('supplier').value;
         const tanggal = document.getElementById('tanggal').value;
         const subtotal = document.getElementById('subtotal').value.replace(/,/g, '');       
         const grandtotal = document.getElementById('grandtotal').value.replace(/,/g, '');
         const idpo = document.getElementById('supplier').dataset.idpo;
         const idsupplier = document.getElementById('supplier').dataset.idsupplier;
         const namasupplierinv = document.getElementById('supplier').dataset.namasupplier;
         const idbl = document.getElementById('nomorBL').dataset.idbl;
         const ppn = document.getElementById('ppn').value.trim().replace(/,/g, '');      
         const catatan = document.getElementById('catatan').value.trim();
         
         if (namasupplier.trim().toLowerCase() == '' || idsupplier == '') {
             alert('Tentukan PO dengan benar !');
             document.getElementById('po').focus();
             return;
         } else if (rowNumber == 0) {
             alert('Tidak ada item barang yang akan disimpan !');
             return;
         } else if (ppn == '0') {
             alert('Tentukan nilai PPN(%) dengan benar !');
             document.getElementById('ppn').focus();
             return;
         }

         const kodeBarang = document.getElementsByName('kodebarang[]');
        const jumlah = document.getElementsByName('kuantitas[]');
         const harga = document.getElementsByName('harga_satuan[]');
        const total = document.getElementsByName('total[]');

        // Buat array untuk menyimpan data barang
        const detailbarang = [];
         let totalbarang = 0;
        // Loop melalui data dan masukkan ke dalam array
        for (let i = 0; i < kodeBarang.length; i++) {
            detailbarang.push({
                iddetailpo: kodeBarang[i].dataset.iddetailpo,
                kodebarang: kodeBarang[i].dataset.idbarang,
                namaBarang: kodeBarang[i].dataset.namabarang,
                jumlah: jumlah[i].value.replace(/,/g, ''),
                harga: harga[i].value.replace(/,/g, ''),
                total: total[i].value.replace(/,/g, '')                
            });
            totalbarang += parseInt(jumlah[i].value);
        }
        
        fetch('Transaksi/BLSave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                kodesupplier: idsupplier,
                idbl : idbl,
                tanggal: tanggal,
                kodepo: idpo,
                totalbarang: totalbarang,
                subtotal: subtotal,
                ppn: ppn,
                grandtotal:grandtotal,
                catatan: catatan,
                detailbarang: detailbarang
            })
        })
        .then(response => response.json())
            .then(data => {
            if (data.success) {
                alert('Data Barang berhasil disimpan!');
                location.reload(); // Refresh halaman
            } else {
                alert('Gagal menyimpan data: ' + data.error);
            }
                })
         
        .catch(error => console.error('Error:', error));

        // Tutup modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('supplierModal'));
        modal.hide();

        // Pesan sukses
        //alert('Data supplier berhasil disimpan!');       
     }

     function refreshTableBody() {
        const cari = document.getElementById('caritxt').value;
        if (cari == "") {
            alert("Silahkan masukkan pencarian terlebih dulu !");
            return;
        }
        $.ajax({
            url: 'Transaksi/POCari.php?abt=podt&kodepo='+kodepo, // URL untuk mengambil data baru
            type: 'GET', // Metode HTTP
            datatype:'json', // 
            success: function (response) {                             
                $('#daftarpo').html(response); // Ganti konten <tbody> dengan respon dari backend
                //console.log(response);
            },
            error: function() {
                console.error("Terjadi kesalahan saat memuat data.");
            }
        });
    }       
 </script>
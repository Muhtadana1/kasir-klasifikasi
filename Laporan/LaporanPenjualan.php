<div class="container-fluid p-3">
    
    <div class="row mb-3 align-items-center">
        <div class="col-md-3">
            <h3><i class="fa-solid fa-chart-pie"></i> Analisis Fast & Slow</h3>
            <small class="text-muted">Metode FSN (Threshold > 30% = Fast)</small>
        </div>
        
        <div class="col-md-9 text-end" style="position: relative; z-index: 10;">
            
            <div class="d-inline-flex align-items-center gap-2 p-2 bg-white border rounded shadow-sm" style="overflow: visible;">
                
                <span class="fw-bold text-muted small me-1"><i class="fa fa-filter"></i> Filter:</span>

                <div class="input-group input-group-sm" style="width: auto;">
                    <select id="filterBulan" class="form-select" style="width: 100px;">
                        <?php
                        $bulanIni = date('m');
                        $namaBulan = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
                        for ($i = 1; $i <= 12; $i++) {
                            $val = str_pad($i, 2, '0', STR_PAD_LEFT);
                            $sel = ($val == $bulanIni) ? 'selected' : '';
                            echo "<option value='$val' $sel>{$namaBulan[$i-1]}</option>";
                        }
                        ?>
                    </select>
                    <select id="filterTahun" class="form-select border-start-0" style="width: 100px;">
                        <?php
                        $tahunIni = date('Y');
                        for ($i = $tahunIni; $i >= $tahunIni - 5; $i--) {
                            echo "<option value='$i'>$i</option>";
                        }
                        ?>
                    </select>
                </div>

                <select id="filterKategori" class="form-select form-select-sm" style="width: 160px;" onchange="renderTable()">
                    <option value=""> Semua Kategori</option>
                    <?php
                    if (isset($conn)) {
                        $sqlKategori = "SELECT NamaKategori FROM tblkategoribarang ORDER BY NamaKategori";
                        $resKategori = $conn->query($sqlKategori);
                        
                        if ($resKategori) {
                            while ($rowK = $resKategori->fetch_assoc()) {
                                $namaKat = htmlspecialchars($rowK['NamaKategori']);
                                echo "<option value='$namaKat'>$namaKat</option>";
                            }
                        }
                    }
                    ?>
                </select>

                <select id="filterStatus" class="form-select form-select-sm" style="width: 140px;" onchange="renderTable()">
                    <option value="">Semua Status</option>
                    <option value="FAST">FAST MOVING</option>
                    <option value="SLOW">SLOW MOVING</option>
                </select>

                <input type="text" id="searchBox" class="form-control form-control-sm" style="width: 150px;" placeholder="Cari Barang..." onkeyup="renderTable()">

                <button class="btn btn-sm btn-primary" onclick="loadData()" title="Muat Data"><i class="fa fa-sync"></i></button>

            </div> </div>
    </div>

    <div class="card shadow-sm" style="position: relative; z-index: 0;">
        <div class="card-body p-0">
            <div class="table-responsive" style="height: 70vh; overflow-y: auto;">
                <table class="table table-bordered table-striped table-hover mb-0" id="mainTable">
                    <thead class="table-dark text-center sticky-top" style="top: 0; z-index: 1;">
                        <tr>
                            <th width="50">No</th>
                            <th>Nama Barang</th>
                            <th width="150">Kategori</th>
                            <th width="100">Terjual</th>
                            <th width="100">Sisa Stok</th>
                            <th width="120">Rasio (%)</th>
                            <th width="120">Status</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr><td colspan="7" class="text-center p-3">Klik tombol <i class="fa fa-sync"></i> untuk memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-muted small">
            <span id="totalDataInfo">Menampilkan 0 data</span>
            <span id="errorMsg" class="text-danger ms-3 fw-bold"></span>
        </div>
    </div>
</div>

<script>
    // Variabel Global
    let allData = [];

    document.addEventListener("DOMContentLoaded", function() {
        loadData();
    });

    function loadData() {
        const bln = document.getElementById('filterBulan').value;
        const thn = document.getElementById('filterTahun').value;
        const tbody = document.getElementById('tableBody');
        const errorMsg = document.getElementById('errorMsg');

        errorMsg.innerText = "";
        tbody.innerHTML = '<tr><td colspan="7" class="text-center"><div class="spinner-border text-primary"></div> Memuat Data Penjualan...</td></tr>';

        fetch(`Laporan/LaporanPenjualanData.php?bulan=${bln}&tahun=${thn}`)
            .then(response => {
                if (!response.ok) throw new Error("Gagal akses LaporanPenjualanData.php");
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                allData = data;
                renderTable();
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger"><i class="fa fa-exclamation-triangle"></i> Gagal mengambil data penjualan.</td></tr>';
                errorMsg.innerText = "Error: " + err.message;
            });
    }

    function renderTable() {
        const catFilter = document.getElementById('filterKategori').value;
        const statusFilter = document.getElementById('filterStatus').value;
        const searchText = document.getElementById('searchBox').value.toLowerCase();
        
        const tbody = document.getElementById('tableBody');
        let html = "";
        let count = 0;

        if (!allData || allData.length === 0) {
             tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Belum ada data transaksi bulan ini.</td></tr>';
             document.getElementById('totalDataInfo').innerText = "Menampilkan 0 data";
             return;
        }

        allData.forEach((item, index) => {
            let itemKat = item.kategori ? item.kategori : ""; 
            let itemNama = item.nama ? item.nama.toLowerCase() : "";
            
            const matchCat = catFilter === "" || itemKat === catFilter;
            const matchStatus = statusFilter === "" || item.status === statusFilter;
            const matchSearch = itemNama.includes(searchText);

            if (matchCat && matchStatus && matchSearch) {
                count++;
                let badgeClass = (item.status === "FAST") ? "bg-success" : "bg-danger";
                let statusText = (item.status === "FAST") ? "FAST MOVING" : "SLOW MOVING";

                let qtyFmt = new Intl.NumberFormat('id-ID').format(item.qty);
                let stokFmt = new Intl.NumberFormat('id-ID').format(item.stok);

                html += `
                    <tr>
                        <td class="text-center">${count}</td>
                        <td>
                            <b>${item.nama}</b>
                            <div class="small text-muted">${item.kode}</div>
                        </td>
                        <td class="text-center">${item.kategori}</td>
                        <td class="text-center fw-bold">${qtyFmt} ${item.satuan}</td>
                        <td class="text-center">${stokFmt} ${item.satuan}</td>
                        <td class="text-center">${item.persen}%</td>
                        <td class="text-center">
                            <span class="badge ${badgeClass}" style="font-size: 0.8em;">${statusText}</span>
                        </td>
                    </tr>
                `;
            }
        });

        if (count === 0) {
            html = '<tr><td colspan="7" class="text-center text-muted">Data tidak ditemukan dengan filter ini.</td></tr>';
        }

        tbody.innerHTML = html;
        document.getElementById('totalDataInfo').innerText = `Menampilkan ${count} dari total ${allData.length} barang`;
    }
</script>
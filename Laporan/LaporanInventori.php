<div class="container-fluid p-3">
    
    <div class="row mb-3 align-items-center">
        <div class="col-md-4">
            <h3><i class="fa-solid fa-boxes-stacked"></i> Laporan Stok</h3>
            <small class="text-muted">Monitoring Masuk, Keluar & Sisa</small>
        </div>
        
        <div class="col-md-8 text-end">
            <div class="d-inline-flex align-items-center gap-2 p-2 bg-white border rounded shadow-sm">
                
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light fw-bold">Periode</span>
                    <select id="filterBulan" class="form-select" style="width: 110px;">
                        <?php
                        $bulanIni = date('m');
                        $namaBulan = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
                        for ($i = 1; $i <= 12; $i++) {
                            $val = str_pad($i, 2, '0', STR_PAD_LEFT);
                            $sel = ($val == $bulanIni) ? 'selected' : '';
                            echo "<option value='$val' $sel>" . $namaBulan[$i-1] . "</option>";
                        }
                        ?>
                    </select>
                    <select id="filterTahun" class="form-select" style="width: 80px;">
                        <?php
                        $tahunIni = date('Y');
                        for ($i = $tahunIni; $i >= $tahunIni - 2; $i--) {
                            echo "<option value='$i'>$i</option>";
                        }
                        ?>
                    </select>
                </div>

                <select id="filterKategori" class="form-select form-select-sm" style="width: 180px;">
                    <option value="">Semua Kategori</option>
                    <?php
                    if (isset($conn)) {
                        $sqlKat = "SELECT NamaKategori FROM tblkategoribarang ORDER BY NamaKategori";
                        $resKat = $conn->query($sqlKat);
                        if ($resKat) {
                            while ($rowK = $resKat->fetch_assoc()) {
                                $nama = htmlspecialchars($rowK['NamaKategori']);
                                echo "<option value='$nama'>$nama</option>";
                            }
                        }
                    }
                    ?>
                </select>
                
                <button class="btn btn-primary btn-sm px-4 fw-bold" onclick="loadData()">
                    <i class="fa-solid fa-search"></i> Tampilkan
                </button>
                
                <div class="vr mx-2"></div>

                <button class="btn btn-success btn-sm" onclick="downloadExcel()" title="Download Excel">
                    <i class="fa-solid fa-file-excel"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 75vh; overflow-y: auto;">
                <table class="table table-striped table-hover align-middle mb-0" id="tabelStok">
                    <thead class="table-dark text-center sticky-top" style="top:0; z-index:1020;">
                        <tr>
                            <th width="5%">No</th>
                            <th width="30%" class="text-start">Nama Barang</th>
                            <th width="15%">Kategori</th>
                            <th width="10%">Satuan</th>
                            <th width="10%" class="bg-secondary">Stok Awal</th> 
                            <th width="10%">Masuk</th>
                            <th width="10%">Keluar</th>
                            <th width="10%" class="bg-primary text-white">Sisa</th>
                            <th width="10%">Status</th>
                        </tr>
                    </thead>
                    <tbody id="dataStok">
                        <tr><td colspan="9" class="text-center py-5 text-muted">Silakan pilih filter dan klik <b>Tampilkan</b></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light small text-muted d-flex justify-content-between">
            <div>
                <i class="fa-solid fa-info-circle me-1"></i> 
                <span id="totalDataInfo">Menampilkan 0 data</span>
            </div>
            <div>
                <strong>Rumus:</strong> Stok Awal = (Sisa Akhir - Masuk) + Keluar
            </div>
        </div>
    </div>
</div>

<script>
    let allData = [];

    $(document).ready(function() {
        loadData();
    });

    function loadData() {
        const bln = document.getElementById('filterBulan').value;
        const thn = document.getElementById('filterTahun').value;
        const tbody = document.getElementById('dataStok');

        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Sedang memuat data...</td></tr>';
        
        fetch(`Laporan/LaporanStokData.php?bulan=${bln}&tahun=${thn}`)
            .then(response => response.json())
            .then(data => {
                if(data.error) {
                    tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger fw-bold">${data.error}</td></tr>`;
                    return;
                }
                allData = data;
                applyFilters(); 
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger fw-bold">Gagal koneksi ke server</td></tr>';
            });
    }

    function applyFilters() {
        const cat = document.getElementById('filterKategori').value;
        const filtered = allData.filter(item => {
            let itemCat = item.Kategori ? item.Kategori : "";
            return cat === "" || itemCat === cat;
        });
        renderTable(filtered);
    }

    function renderTable(data) {
        const tbody = document.getElementById('dataStok');
        let html = '';
        let no = 1;

        data.forEach(item => {
            let badgeClass = 'bg-success';
            let statusText = item.Status || 'AMAN';
            
            // --- LOGIKA BARU DI JS (BATAS 10) ---
            if(item.StokAkhir == 0) {
                badgeClass = 'bg-danger';
                statusText = 'HABIS';
            } else if(item.StokAkhir < 10) { // <--- DIGANTI JADI 10
                badgeClass = 'bg-warning text-dark';
                statusText = 'KRITIS';
            }

            html += `
                <tr>
                    <td class="text-center">${no++}</td>
                    <td>
                        <div class="fw-bold text-dark">${item.NamaBarang}</div>
                        <small class="text-muted"><i class="fa fa-barcode me-1"></i>${item.KodeBarang}</small>
                    </td>
                    <td class="text-center small">${item.Kategori || '-'}</td>
                    <td class="text-center fw-bold text-secondary">${item.Satuan}</td>
                    
                    <td class="text-center fw-bold bg-light" style="color:#555;">${item.StokAwal}</td>
                    <td class="text-center text-success fw-bold">+${item.Masuk}</td>
                    <td class="text-center text-danger fw-bold">-${item.Keluar}</td>
                    <td class="text-center fw-bold text-primary" style="font-size:1.1em;">${item.StokAkhir}</td>
                    
                    <td class="text-center">
                        <span class="badge ${badgeClass} w-100">${statusText}</span>
                        ${item.StokPO > 0 ? `<div class="badge bg-info text-dark mt-1 w-100" style="font-size:0.7em">PO: ${item.StokPO}</div>` : ''}
                    </td>
                </tr>
            `;
        });

        if(data.length === 0) {
            html = '<tr><td colspan="9" class="text-center text-muted py-5">Data tidak ditemukan untuk kategori ini.</td></tr>';
        }

        tbody.innerHTML = html;
        document.getElementById('totalDataInfo').innerText = `Menampilkan ${data.length} dari ${allData.length} barang`;
    }

    function downloadExcel() {
        const bln = document.getElementById('filterBulan').value;
        const thn = document.getElementById('filterTahun').value;
        window.open(`Laporan/LaporanStokExcel.php?bulan=${bln}&tahun=${thn}`, '_blank');
    }
</script>
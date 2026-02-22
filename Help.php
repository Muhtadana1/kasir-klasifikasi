<div class="container-fluid p-4">
    
    <div class="d-flex align-items-center mb-4 border-bottom pb-2">
        <h1 class="h3 text-primary mb-0"><i class="fa-solid fa-circle-question me-2"></i> Pusat Bantuan & SOP Toko</h1>
    </div>
    <p class="text-muted">Dokumentasi Standar Operasional Prosedur (SOP), Strategi Bisnis, dan Penanganan Masalah (Troubleshooting).</p>

    <div class="accordion shadow-sm" id="accordionHelp">
        
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne">
                <button class="accordion-button collapsed fw-bold text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                    <i class="fa-solid fa-rotate me-2"></i> 1. Alur Kerja & Siklus Barang
                </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionHelp">
                <div class="accordion-body">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card bg-light border-0 h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fa-solid fa-truck-ramp-box fa-3x text-success mb-3"></i>
                                    <h6 class="fw-bold">1. Barang Masuk (Inbound)</h6>
                                    <p class="small text-muted">Saat supplier mengirim barang.</p>
                                    <div class="alert alert-white border small text-start bg-white">
                                        <b>SOP Admin/Gudang:</b>
                                        <ol class="ps-3 mb-0">
                                            <li>Cek fisik barang (rusak/bagus).</li>
                                            <li>Buka menu <b>Pembelian (PO)</b>.</li>
                                            <li>Input sesuai Faktur Supplier.</li>
                                            <li>Simpan -> Stok Bertambah & HPP Terupdate.</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-light border-0 h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fa-solid fa-cash-register fa-3x text-primary mb-3"></i>
                                    <h6 class="fw-bold">2. Penjualan (Outbound)</h6>
                                    <p class="small text-muted">Saat ada pembeli belanja.</p>
                                    <div class="alert alert-white border small text-start bg-white">
                                        <b>SOP Kasir:</b>
                                        <ol class="ps-3 mb-0">
                                            <li>Scan Barcode / Ketik Nama.</li>
                                            <li>Terima pembayaran.</li>
                                            <li>Cetak Struk.</li>
                                            <li>Stok Berkurang Otomatis.</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-light border-0 h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fa-solid fa-clipboard-check fa-3x text-warning mb-3"></i>
                                    <h6 class="fw-bold">3. Stok Opname (Control)</h6>
                                    <p class="small text-muted">Cek rutin akhir bulan.</p>
                                    <div class="alert alert-white border small text-start bg-white">
                                        <b>SOP Manager:</b>
                                        <ol class="ps-3 mb-0">
                                            <li>Cetak Laporan Stok saat ini.</li>
                                            <li>Hitung fisik di rak.</li>
                                            <li>Jika beda, lakukan penyesuaian (Adjusment).</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="accordion-item border-primary border-start border-4">
            <h2 class="accordion-header" id="headingTwo">
                <button class="accordion-button fw-bold text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="true">
                    <i class="fa-solid fa-chart-column me-2"></i> 2. Laporan, Diagnosa & Solusi Bisnis
                </button>
            </h2>
            <div id="collapseTwo" class="accordion-collapse collapse show" data-bs-parent="#accordionHelp">
                <div class="accordion-body">
                    
                    <p class="text-muted small mb-4">Bagian ini menjelaskan arti indikator warna pada laporan dan tindakan manajerial yang disarankan berdasarkan jurnal operasional.</p>

                    <h5 class="fw-bold mb-3 text-dark border-bottom pb-2"><i class="fa-solid fa-traffic-light"></i> A. Diagnosa Level Stok (Kuantitas)</h5>
                    <ul class="list-group list-group-flush mb-5">
                        
                        <li class="list-group-item bg-light border-start border-danger border-4 mb-3 rounded">
                            <div class="d-flex align-items-start">
                                <span class="badge bg-danger mt-1 me-2 px-3">CRITICAL</span>
                                <div class="w-100">
                                    <b>Stok Kritis (< 20%):</b> Kondisi darurat. Stok hampir habis total.
                                    
                                    <div class="alert alert-white border mt-2 p-3 shadow-sm bg-white">
                                        <h6 class="text-danger fw-bold small"><i class="fa-solid fa-triangle-exclamation"></i> 3 Solusi Manajerial:</h6>
                                        <ol class="small text-muted mb-2 ps-3">
                                            <li class="mb-1"><b>Emergency Order:</b> Lakukan pemesanan cito (kilat) hari ini juga. Biaya kirim mahal lebih baik daripada kehilangan omzet (Stockout Cost).</li>
                                            <li class="mb-1"><b>Vendor Managed Inventory:</b> Minta supplier kirim stok darurat segera.</li>
                                            <li class="mb-1"><b>Limitasi Penjualan:</b> Batasi grosir agar stok cukup untuk eceran sampai barang datang.</li>
                                        </ol>
                                        <div class="text-end border-top pt-2 mt-2">
                                            <small class="text-muted fst-italic" style="font-size: 10px;">
                                                <i class="fa-solid fa-book"></i> Ref: 
                                                <a href="https://link.springer.com/chapter/10.1007/978-3-8349-9320-5_22" target="_blank" class="text-decoration-none text-muted fw-bold">
                                                    Chopra & Meindl "Supply Chain Management" (Stockout Cost) <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 9px;"></i>
                                                </a>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <li class="list-group-item bg-light border-start border-warning border-4 mb-3 rounded">
                            <div class="d-flex align-items-start">
                                <span class="badge bg-warning text-dark mt-1 me-2 px-3">WARNING</span>
                                <div class="w-100">
                                    <b>Stok Menipis (20% - 40%):</b> Mendekati titik pemesanan ulang (Reorder Point).
                                    
                                    <div class="alert alert-white border mt-2 p-3 shadow-sm bg-white">
                                        <h6 class="text-warning text-dark fw-bold small"><i class="fa-solid fa-bell"></i> 3 Solusi Manajerial:</h6>
                                        <ol class="small text-muted mb-2 ps-3">
                                            <li class="mb-1"><b>Prepare PO:</b> Masukkan item ini ke draft belanja minggu ini.</li>
                                            <li class="mb-1"><b>Cek Lead Time:</b> Pastikan durasi pengiriman supplier tidak lebih lama dari sisa umur stok.</li>
                                            <li class="mb-1"><b>Review Tren:</b> Jika penjualan meningkat tajam, naikkan jumlah order (Safety Stock).</li>
                                        </ol>
                                        <div class="text-end border-top pt-2 mt-2">
                                            <small class="text-muted fst-italic" style="font-size: 10px;">
                                                <i class="fa-solid fa-book"></i> Ref: 
                                                <a href="https://scholar.google.com/scholar?hl=id&as_sdt=0%2C5&q=%3A+Stevenson+%22Operations+Management%22+&btnG=" target="_blank" class="text-decoration-none text-muted fw-bold">
                                                    Stevenson "Operations Management" (Reorder Point Theory) <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 9px;"></i>
                                                </a>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>


                    <h5 class="fw-bold mb-3 text-dark border-bottom pb-2"><i class="fa-solid fa-gauge-high"></i> B. Analisis Perputaran (Fast/Slow Moving)</h5>
                    <ul class="list-group list-group-flush">
                        
                        <li class="list-group-item bg-light border-start border-success border-4 mb-3 rounded">
                            <div class="d-flex align-items-start">
                                <i class="fa-solid fa-bolt text-warning mt-1 me-2 fa-lg"></i>
                                <div class="w-100">
                                    <b>FAST MOVING (Barang Laris):</b> Rasio Jual > 30%. Sapi perah (Cash Cow) toko.
                                    
                                    <div class="alert alert-white border mt-2 p-3 shadow-sm bg-white">
                                        <h6 class="text-success fw-bold small"><i class="fa-solid fa-lightbulb"></i> 3 Solusi Manajerial:</h6>
                                        <ol class="small text-muted mb-2 ps-3">
                                            <li class="mb-1"><b>Golden Zone Placement:</b> Letakkan di rak paling strategis (setinggi mata, dekat kasir) agar mudah diambil.</li>
                                            <li class="mb-1"><b>Safety Stock Tinggi:</b> Naikkan batas minimal stok. Kehabisan barang ini = kehilangan pelanggan.</li>
                                            <li class="mb-1"><b>No Discount:</b> Hindari diskon. Fokus pada ketersediaan barang, bukan pemotongan harga.</li>
                                        </ol>
                                        
                                        <div class="text-end border-top pt-2 mt-2">
                                            <div class="d-flex justify-content-end flex-column">
                                                <small class="text-muted fst-italic mb-1" style="font-size: 10px;">
                                                    <i class="fa-solid fa-book"></i> Ref 1: 
                                                    <a href="https://scholar.google.com/scholar?hl=id&as_sdt=0%2C5&q=Klumpp+Golden+zone+storage+assignment+and+picking+performance%3A+An+empirical+analysis+of+manual+picker-to-parts+OP+systems+in+grocery+retailing%22+&btnG=" target="_blank" class="text-decoration-none text-muted fw-bold">
                                                    Dominic Loske, Jonas Koreis, Matthias "Klumpp Golden zone storage assignment and picking performance: An empirical analysis of manual picker-to-parts OP systems in grocery retailing" <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 9px;"></i>
                                                    </a>
                                                </small>
                                                <small class="text-muted fst-italic" style="font-size: 10px;">
                                                    <i class="fa-solid fa-book"></i> Ref 2: 
                                                    <a href="https://scholar.google.com/scholar?hl=id&as_sdt=0%2C5&scioq=Heizer+%26+Render+%22Operations+Management%22+&q=%22Operations+Management%3A+Sustainability+and+Supply+Chain+Management%22&btnG=" target="_blank" class="text-decoration-none text-muted fw-bold">
                                                        Heizer & Render "Operations Management: Sustainability and Supply Chain Management" (Safety Stock) <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 9px;"></i>
                                                    </a>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <li class="list-group-item bg-light border-start border-danger border-4 mb-2 rounded">
                            <div class="d-flex align-items-start">
                                <i class="fa-solid fa-snail text-secondary mt-1 me-2 fa-lg"></i>
                                <div class="w-100">
                                    <b>SLOW MOVING (Barang Lambat):</b> Rasio Jual <= 30%. Resiko modal mandek.
                                    
                                    <div class="alert alert-white border mt-2 p-3 shadow-sm bg-white">
                                        <h6 class="text-danger fw-bold small"><i class="fa-solid fa-briefcase-medical"></i> 3 Solusi Manajerial:</h6>
                                        <ol class="small text-muted mb-2 ps-3">
                                            <li class="mb-1"><b>Bundling Strategy:</b> Jual paket "Beli Barang Laris, Gratis Barang Ini" untuk mendorong stok keluar.</li>
                                            <li class="mb-1"><b>Liquidation Discount:</b> Gunakan label "Cuci Gudang" atau diskon besar untuk mengubah barang jadi uang tunai.</li>
                                            <li class="mb-1"><b>Stop Reorder:</b> Hentikan pembelian barang jenis ini sampai stok di gudang benar-benar habis.</li>
                                        </ol>

                                        <div class="text-end border-top pt-2 mt-2">
                                            <div class="d-flex justify-content-end flex-column">
                                                <small class="text-muted fst-italic mb-1" style="font-size: 10px;">
                                                    <i class="fa-solid fa-book"></i> Ref 1: 
                                                    <a href="https://scholar.google.com/citations?view_op=view_citation&hl=id&user=VS0HD88AAAAJ&citation_for_view=VS0HD88AAAAJ:u-x6o8ySG0sC" target="_blank" class="text-decoration-none text-muted fw-bold">
                                                    Raghu Nandan Giri, Shyamal Kumar Mondal, Manoranjan Maiti "Bundle pricing strategies for two complementary products with different channel powers" <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 9px;"></i>
                                                    </a>
                                                </small>
                                                <small class="text-muted fst-italic" style="font-size: 10px;">
                                                    <i class="fa-solid fa-book"></i> Ref 2: 
                                                    <a href="https://scholar.google.com/scholar?hl=id&as_sdt=0%2C5&q=A+sustainable+inventory+model+with+controllable+carbon+emissions%2C+deterioration+and+discount+policy&btnG=" target="_blank" class="text-decoration-none text-muted fw-bold">
                                                    Abu Hashan Md Mashud, Dipa Roy, Yosef Daryanto, Ripon Kumar Chakrabortty, Ming-Lang Tseng "A sustainable inventory model with controllable carbon emissions, deterioration and discount policy" <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 9px;"></i>
                                                    </a>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>

                    </ul>

                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header" id="headingThree">
                <button class="accordion-button collapsed fw-bold text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                    <i class="fa-solid fa-cash-register me-2"></i> 3. SOP Kasir & Retur Barang
                </button>
            </h2>
            <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionHelp">
                <div class="accordion-body">
                    
                    <div class="mb-4">
                        <h6 class="fw-bold"><i class="fa-solid fa-desktop text-primary"></i> A. Prosedur Kasir</h6>
                        <div class="alert alert-white border shadow-sm">
                            <ul class="small text-muted mb-0">
                                <li class="mb-2"><b>1. Buka Modal Awal:</b> Saat pagi, kasir wajib input "Modal Laci" (uang kembalian) jika fitur aktif.</li>
                                <li class="mb-2"><b>2. Transaksi:</b> Pastikan kursor aktif di kolom barcode. Gunakan tombol <b>F9</b> (Bayar) untuk mempercepat.</li>
                                <li class="mb-2"><b>3. Pending Transaksi:</b> Jika pembeli ingin tambah barang lagi, gunakan fitur "Hold/Simpan Sementara" agar antrian lain tidak macet.</li>
                            </ul>
                        </div>
                    </div>

                    <div>
                        <h6 class="fw-bold"><i class="fa-solid fa-rotate-left text-danger"></i> B. Prosedur Retur (Pengembalian)</h6>
                        <div class="alert alert-white border shadow-sm">
                            <ul class="small text-muted mb-0">
                                <li class="mb-2"><b>1. Cek Nota & Fisik:</b> Wajib minta struk belanja asli. Cek kondisi barang (apakah cacat pabrik atau kesalahan user).</li>
                                <li class="mb-2"><b>2. Input Sistem:</b> Masuk menu <b>Transaksi -> Retur Penjualan</b>.</li>
                                <li class="mb-2"><b>3. Persetujuan:</b> Retur yang diinput kasir statusnya <b>PENDING</b>. Manager/Admin harus melakukan "Approve" agar stok gudang bertambah kembali.</li>
                            </ul>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header" id="headingFour">
                <button class="accordion-button collapsed fw-bold text-danger" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> 4. Kendala Umum & Solusinya
                </button>
            </h2>
            <div id="collapseFour" class="accordion-collapse collapse" data-bs-parent="#accordionHelp">
                <div class="accordion-body">
                    
                    <p class="small text-muted">Daftar masalah yang sering terjadi di lapangan dan cara mengatasinya secara mandiri.</p>

                    <div class="d-flex align-items-start mb-3 border-bottom pb-3">
                        <div class="me-3">
                            <span class="badge bg-danger p-2"><i class="fa-solid fa-bug fa-lg"></i></span>
                        </div>
                        <div class="w-100">
                            <h6 class="fw-bold text-dark">Kasus: Stok Barang Menjadi Minus (-)</h6>
                            <p class="small text-muted mb-2">Penyebab: Kasir menjual barang saat stok di komputer 0, atau salah input stok awal.</p>
                            
                            <div class="alert alert-light border border-danger p-3 mb-0">
                                <h6 class="text-danger fw-bold small mb-2"><i class="fa-solid fa-wrench"></i> Langkah Perbaikan:</h6>
                                <ol class="small text-muted mb-0 ps-3">
                                    <li>Lakukan <b>Stok Opname</b> fisik untuk barang tersebut.</li>
                                    <li>Buka menu <b>Master Barang</b>, lalu edit jumlah stok sesuai hitungan fisik (Stock Adjustment).</li>
                                    <li>Cek riwayat "Kartu Stok" untuk melihat siapa yang melakukan transaksi minus tersebut.</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
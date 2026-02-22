<style>
    /* --- LAYOUT UTAMA (FULL SCREEN) --- */
    .pos-container { 
        height: calc(100vh - 80px); 
        display: flex; 
        overflow: hidden; 
    }

    /* BAGIAN KIRI (KERANJANG) */
    .pos-left { 
        height: 100%; 
        display: flex; 
        flex-direction: column; 
        border-right: 2px solid #dee2e6; 
        background-color: #f8f9fa; 
    }
    .cart-table-container { 
        flex-grow: 1; 
        overflow-y: auto; 
        background-color: white; 
        border-bottom: 1px solid #dee2e6;
    }
    .total-section { 
        background-color: #2c3e50; 
        color: white; 
        padding: 15px; 
        flex-shrink: 0; 
    }

    /* BAGIAN KANAN (PRODUK) */
    .pos-right { 
        height: 100%; 
        display: flex; 
        flex-direction: column; 
        background-color: #ffffff; 
    }
    .pos-right-header {
        padding: 15px; 
        background-color: white;
        border-bottom: 1px solid #dee2e6;
        flex-shrink: 0;
        z-index: 10;
    }
    .pos-right-grid {
        flex-grow: 1; 
        overflow-y: auto; 
        padding: 15px;
        background-color: #fdfdfd;
    }

    /* KARTU PRODUK */
    .grid-card {
        cursor: pointer;
        transition: all 0.1s;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-weight: bold;
        background: white;
        position: relative;
        overflow: hidden;
    }
    .grid-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-color: #3498db; }
    .grid-card:active { transform: scale(0.98); background-color: #e2e6ea; }
    
    .cat-card { border-left: 5px solid #3498db; color: #2980b9; font-size: 1.1em; }
    .prod-card { border-left: 5px solid #2ecc71; color: #27ae60; flex-direction: column; }
</style>

<div class="container-fluid p-0">
    <div class="row g-0 pos-container">
        
    <div class="col-md-5 pos-left">
    <div class="p-3 bg-primary text-white d-flex justify-content-between align-items-center shadow-sm">
        <h5 class="m-0"><i class="fa-solid fa-cart-shopping"></i> Kasir</h5>
        
        <div>
            <a href="MainForm.php?page=KasirRiwayat" target="" class="btn btn-sm btn-warning text-dark fw-bold me-2">
                <i class="fa-solid fa-clock-rotate-left"></i> Riwayat
            </a>

            <button class="btn btn-sm btn-danger fw-bold" onclick="resetCart()">
                <i class="fa-solid fa-trash"></i> Reset (F2)
            </button>
        </div>

    </div>

            <div class="cart-table-container">
                <table class="table table-striped table-hover mb-0" id="cartTable">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th width="45%">Nama Barang</th>
                            <th width="20%" class="text-center">Qty</th>
                            <th width="25%" class="text-end">Subtotal</th>
                            <th width="10%"></th>
                        </tr>
                    </thead>
                    <tbody id="cartBody"></tbody>
                </table>
            </div>

            <div class="total-section shadow-lg">
                <div class="d-flex justify-content-between mb-3">
                    <span style="font-size: 1.5rem;">Total:</span>
                    <span id="lblGrandTotal" style="font-size: 2rem; font-weight: bold; color: #2ecc71;">0</span>
                </div>
                <button class="btn btn-success w-100 py-3 fw-bold shadow" style="font-size: 1.5rem;" onclick="showPaymentModal()">
                    <i class="fa-solid fa-money-bill-wave"></i> BAYAR [Spasi]
                </button>
            </div>
        </div>

        <div class="col-md-7 pos-right">
            <div class="pos-right-header shadow-sm">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="inputCari" class="form-control" placeholder="Ketik nama barang..." autocomplete="off">
                </div>
                
                <div id="searchResults" class="list-group mt-1 shadow" style="display:none; position: absolute; z-index: 999; width: 55%; max-height: 300px; overflow-y: auto;"></div>
                
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="text-muted fw-bold" id="gridTitle"><i class="fa-solid fa-list"></i> Kategori Barang</span>
                    <button class="btn btn-sm btn-secondary px-3" id="btnBackCat" style="display:none;" onclick="showCategories()">
                        <i class="fa-solid fa-arrow-left"></i> Kembali
                    </button>
                </div>
            </div>

            <div class="pos-right-grid">
                <div class="row g-2" id="gridArea">
                    <?php
                    // Load Kategori Awal
                    $sqlCat = "SELECT KodeKategori, NamaKategori FROM tblkategoribarang ORDER BY NamaKategori";
                    $resCat = $conn->query($sqlCat);
                    if ($resCat) {
                        while ($row = $resCat->fetch_assoc()) {
                            echo '
                            <div class="col-md-3 col-6">
                                <div class="card grid-card cat-card shadow-sm" onclick="loadProducts(\''.$row['KodeKategori'].'\', \''.$row['NamaKategori'].'\')">
                                    <div>'.$row['NamaKategori'].'</div>
                                </div>
                            </div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fa-solid fa-cash-register"></i> Pembayaran</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <small class="text-muted">Total Belanja</small>
                    <h1 class="fw-bold display-4 text-success" id="modalTotalDisplay">0</h1>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Uang Diterima</label>
                    <input type="text" id="inputBayar" class="form-control form-control-lg text-end" placeholder="Masukkan nominal..." oninput="hitungKembali()">
                </div>
                <div class="alert alert-secondary d-flex justify-content-between align-items-center">
                    <span class="fw-bold">KEMBALI:</span>
                    <span id="lblKembali" class="fs-4 fw-bold text-danger">0</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary px-4 fw-bold" onclick="processTransaction()">SELESAI (Enter)</button>
            </div>
        </div>
    </div>
</div>

<script>
    let cart = [];
    let initialCategoriesHTML = "";

    document.addEventListener("DOMContentLoaded", () => {
        initialCategoriesHTML = document.getElementById('gridArea').innerHTML;
        document.getElementById("inputCari").focus();
    });

    // --- NAVIGASI KATEGORI & PRODUK ---
    function showCategories() {
        const grid = document.getElementById('gridArea');
        grid.innerHTML = initialCategoriesHTML;
        document.getElementById('btnBackCat').style.display = 'none';
        document.getElementById('gridTitle').innerHTML = '<i class="fa-solid fa-list"></i> Kategori Barang';
    }

    function loadProducts(kategoriId, kategoriNama) {
        const grid = document.getElementById('gridArea');
        grid.innerHTML = '<div class="col-12 text-center p-5"><div class="spinner-border text-primary"></div><p>Memuat...</p></div>';
        
        document.getElementById('btnBackCat').style.display = 'inline-block';
        document.getElementById('gridTitle').innerHTML = '<i class="fa-solid fa-box"></i> ' + kategoriNama;

        fetch('Transaksi/KasirCari.php?kategori=' + kategoriId)
            .then(res => res.json())
            .then(data => {
                grid.innerHTML = '';
                if(data.length === 0) {
                    grid.innerHTML = '<div class="col-12 text-center text-muted p-5"><h5>Tidak ada produk.</h5></div>';
                    return;
                }
                data.forEach(item => {
                    let card = `
                    <div class="col-md-3 col-6">
                        <div class="card grid-card prod-card shadow-sm" onclick="addToCart('${item.id}', '${item.text}', ${item.harga})">
                            <div class="text-dark text-truncate w-100 px-2" style="font-size:0.9rem">${item.text}</div>
                            <div class="mt-1 fw-bold text-success">Rp ${FormatUSNumeric(item.harga)}</div>
                        </div>
                    </div>`;
                    grid.innerHTML += card;
                });
            });
    }

    // --- KERANJANG BELANJA ---
    function addToCart(id, nama, harga) {
        let item = cart.find(x => x.id === id);
        if (item) {
            item.qty++;
        } else {
            cart.push({ id: id, nama: nama, harga: parseFloat(harga), qty: 1 });
        }
        renderCart();
        
        // Reset pencarian
        document.getElementById("searchResults").style.display = 'none';
        document.getElementById("inputCari").value = '';
        document.getElementById("inputCari").focus();
    }

    function renderCart() {
        const tbody = document.getElementById("cartBody");
        tbody.innerHTML = "";
        let total = 0;
        
        cart.forEach((item, i) => {
            let sub = item.qty * item.harga;
            total += sub;
            tbody.innerHTML += `
                <tr>
                    <td style="vertical-align: middle;">
                        <div class="fw-bold text-truncate" style="max-width: 200px;">${item.nama}</div>
                        <small class="text-muted">${item.id}</small>
                    </td>
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm text-center px-0" value="${item.qty}" onchange="updateQty(${i}, this.value)" min="1">
                    </td>
                    <td class="text-end fw-bold" style="vertical-align: middle;">${FormatUSNumeric(sub)}</td>
                    <td class="text-center" style="vertical-align: middle;">
                        <button class="btn btn-sm text-danger" onclick="removeItem(${i})"><i class="fa-solid fa-trash-can"></i></button>
                    </td>
                </tr>`;
        });

        document.getElementById("lblGrandTotal").innerText = FormatUSNumeric(total);
        
        // Auto scroll ke bawah
        const container = document.querySelector('.cart-table-container');
        container.scrollTop = container.scrollHeight;
    }

    function updateQty(i, val) {
        if(val < 1) val = 1;
        cart[i].qty = parseInt(val);
        renderCart();
    }

    function removeItem(i) {
        cart.splice(i, 1);
        renderCart();
    }

    function resetCart() {
        if(cart.length > 0 && confirm("Kosongkan keranjang?")) {
            cart = [];
            renderCart();
        }
    }

    // --- PENCARIAN MANUAL (Live Search) ---
    document.getElementById('inputCari').addEventListener('keyup', function(e) {
        let q = this.value;
        let box = document.getElementById('searchResults');
        
        if(q.length < 2) {
            box.style.display = 'none';
            return;
        }

        // Fetch data
        fetch('Transaksi/KasirCari.php?q=' + encodeURIComponent(q))
            .then(res => res.json())
            .then(data => {
                box.innerHTML = '';
                if(data.length > 0) {
                    box.style.display = 'block';
                    data.forEach(item => {
                        let a = document.createElement('a');
                        a.className = 'list-group-item list-group-item-action cursor-pointer';
                        a.style.cursor = 'pointer';
                        a.innerHTML = `<div class="d-flex justify-content-between">
                                        <span>${item.text}</span>
                                        <span class="fw-bold text-primary">Rp ${FormatUSNumeric(item.harga)}</span>
                                       </div>`;
                        a.onclick = () => addToCart(item.id, item.text, item.harga);
                        box.appendChild(a);
                    });
                } else {
                    box.style.display = 'none';
                }
            });
    });

    // --- PEMBAYARAN ---
    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));

    function showPaymentModal() {
        if(cart.length === 0) return alert("Keranjang kosong!");
        
        let total = cart.reduce((sum, item) => sum + (item.qty * item.harga), 0);
        document.getElementById("modalTotalDisplay").innerText = FormatUSNumeric(total);
        document.getElementById("inputBayar").value = "";
        document.getElementById("lblKembali").innerText = "0";
        
        paymentModal.show();
        setTimeout(() => document.getElementById("inputBayar").focus(), 500);
    }

    function hitungKembali() {
        let total = parseFloat(document.getElementById("modalTotalDisplay").innerText.replace(/,/g,''));
        let rawBayar = document.getElementById("inputBayar").value.replace(/,/g,'');
        let bayar = parseFloat(rawBayar) || 0;
        
        let kembali = bayar - total;
        document.getElementById("lblKembali").innerText = FormatUSNumeric(kembali);
        
        let elKembali = document.getElementById("lblKembali");
        if(kembali < 0) elKembali.className = "fs-4 fw-bold text-danger";
        else elKembali.className = "fs-4 fw-bold text-success";
    }

    document.getElementById("inputBayar").addEventListener("keydown", function(e) {
        if(e.key === "Enter") processTransaction();
    });

    function processTransaction() {
        let total = parseFloat(document.getElementById("modalTotalDisplay").innerText.replace(/,/g,''));
        let bayar = parseFloat(document.getElementById("inputBayar").value.replace(/,/g,'')) || 0;
        
        if(bayar < total) {
            alert("Uang pembayaran kurang!");
            return;
        }

        const payload = {
            subtotal: total,
            diskon: 0,
            grandtotal: total,
            bayar: bayar,
            kembali: (bayar - total),
            detail: cart
        };

        // Tombol jadi loading
        const btn = document.querySelector('#paymentModal .modal-footer .btn-primary');
        btn.disabled = true;
        btn.innerText = "Menyimpan...";

        fetch('Transaksi/KasirSave.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerText = "SELESAI (Enter)";
            
            if(data.success) {
                alert("Transaksi Berhasil!\nNo Nota: " + data.noNota + "\nKembalian: Rp " + FormatUSNumeric(bayar - total));
                paymentModal.hide();
                cart = [];
                renderCart();
                showCategories();
                document.getElementById("inputCari").focus();
            } else {
                alert("Gagal: " + data.error);
            }
        })
        .catch(err => {
            btn.disabled = false;
            console.error(err);
            alert("Terjadi kesalahan koneksi.");
        });
    }

    // Shortcut
    document.addEventListener('keydown', function(e) {
        if(e.key === "F2") { e.preventDefault(); resetCart(); }
        if(e.code === "Space" && e.target.tagName !== 'INPUT') { e.preventDefault(); showPaymentModal(); }
    });
</script>
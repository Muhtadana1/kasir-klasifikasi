<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$akses = $_SESSION['hak_akses']['User'] ?? ['View' => 0, 'Add' => 0, 'Edit' => 0, 'Delete' => 0];

// Security: Hanya yang punya akses View boleh buka
if (!$akses['View']) {
    echo "<div class='alert alert-danger'>AKSES DITOLAK: Anda tidak memiliki izin mengelola User.</div>";
    exit;
}
?>

<div style="padding: 10px">
    <div class="d-flex justify-content-between mb-2">
        <form name="cariuser" method="post" action="" class="d-flex gap-2">
            <input type="text" id="cari" name="cari" class="form-control" placeholder="Cari User" style="width: 250px;" value="<?= isset($_POST['cari']) ? htmlspecialchars($_POST['cari']) : '' ?>" />
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i> Cari</button>
        </form>

        <?php if ($akses['Add']): ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#userModal" onclick="clearUserObj();">
                <i class="fa-solid fa-user-plus"></i> Tambah User
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive" style="max-height: 80vh; overflow-y: auto; border: 1px solid #dee2e6;">
        <table class="table table-bordered table-hover table-striped mb-0">
            <thead class="table-primary text-center" style="position: sticky; top: 0; z-index: 1;">
                <tr>
                    <th>USERNAME</th>
                    <th>JABATAN (ROLE)</th>
                    <?php if ($akses['Edit'] || $akses['Delete']): ?>
                        <th style="width: 120px;">AKSI</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody style="font-size:12px">
                <?php
                $cari = $_POST['cari'] ?? '';
                $search = "%$cari%";

                // QUERY JOIN: Ambil Nama Jabatan dari tblkategoriuser
                $sql = "SELECT a.KodeUser, a.UserLogin, a.KodeKategori, b.NamaKategoriUser 
                        FROM tbllogin a 
                        LEFT JOIN tblkategoriuser b ON a.KodeKategori = b.KodeKategori
                        WHERE a.UserLogin LIKE ? 
                        ORDER BY a.UserLogin LIMIT 100";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $search);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                   while($row = $result->fetch_assoc()){
                       $kode = $row['KodeUser'];
                       $user = htmlspecialchars($row['UserLogin'], ENT_QUOTES);
                       $idKat = $row['KodeKategori'];
                       $namaKat = htmlspecialchars($row['NamaKategoriUser'] ?? 'Unknown', ENT_QUOTES);
                   ?>
                <tr>
                    <td><?= $user ?></td>
                    <td><?= $namaKat ?></td>
                    
                    <?php if ($akses['Edit'] || $akses['Delete']): ?>
                    <td class="text-center">
                        <?php if ($akses['Edit']): ?>
                            <button class="btn btn-sm btn-primary me-1" onclick="EditUser('<?=$kode?>','<?=$user?>','<?=$idKat?>')">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($akses['Delete']): ?>
                            <button class="btn btn-sm btn-danger" onclick="DeleteUser('<?=$kode?>','<?=$user?>')">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php
                   }
                } else {
                    $cols = 2 + (($akses['Edit'] || $akses['Delete']) ? 1 : 0);
                    echo "<tr><td colspan='$cols' class='text-center'>Data tidak ditemukan.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="userModalLabel">Form Data User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" id="userLogin" required>
                        <input type="hidden" value="0" id="kodeuser" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" placeholder="Kosongkan jika tidak ingin mengubah password">
                        <small class="text-muted">*Wajib diisi untuk user baru</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jabatan (Role)</label>
                        <select class="form-select" id="kategori">
                            <option value="">-- Pilih Jabatan --</option>
                            <?php
                            // Ambil Data Jabatan untuk Dropdown
                            $sqlKat = "SELECT * FROM tblkategoriuser ORDER BY NamaKategoriUser";
                            $resKat = $conn->query($sqlKat);
                            while($kat = $resKat->fetch_assoc()){
                                echo "<option value='".$kat['KodeKategori']."'>".$kat['NamaKategoriUser']."</option>";
                            }
                            ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveUser()">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script>
    function clearUserObj() {
        document.getElementById('kodeuser').value = '0';
        document.getElementById('userLogin').value = '';
        document.getElementById('password').value = '';
        document.getElementById('kategori').value = '';
        document.getElementById('userModalLabel').innerText = "Tambah User Baru";
    }

    function EditUser(kode, user, idKategori) {
        document.getElementById('kodeuser').value = kode;
        document.getElementById('userLogin').value = user;
        document.getElementById('password').value = ''; // Password dikosongkan demi keamanan
        document.getElementById('kategori').value = idKategori;
        
        document.getElementById('userModalLabel').innerText = "Edit User: " + user;
        new bootstrap.Modal(document.getElementById('userModal')).show();
    }

    function DeleteUser(kode, user) {
        if (!confirm("Yakin hapus user: " + user + " ?")) return;
        
        fetch('DataMaster/UserDel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ kodeuser: kode })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('User berhasil dihapus!');
                location.reload(); 
            } else {
                alert('Gagal: ' + data.error);
            }
        });
    }

    function saveUser() {
        const kode = document.getElementById('kodeuser').value;
        const user = document.getElementById('userLogin').value;
        const pass = document.getElementById('password').value;
        const kat = document.getElementById('kategori').value;

        if (!user || !kat) {
            alert('Username dan Jabatan harus diisi!'); return;
        }
        // Validasi Password Baru
        if (kode == '0' && !pass) {
            alert('Password wajib diisi untuk user baru!'); return;
        }

        fetch('DataMaster/UserSave.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                kodeuser: kode,
                userlogin: user,
                password: pass,
                kategori: kat // Ini sekarang mengirim ID Angka (misal: 1, 2, 3)
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Berhasil disimpan!');
                location.reload();
            } else {
                alert('Gagal: ' + data.error);
            }
        });
    }
</script>
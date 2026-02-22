<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth
{
    private $conn;

    public function __construct($dbConnection)
    {
        $this->conn = $dbConnection;
    }

    // --- FUNGSI LOGIN UTAMA (UPDATED) ---
    public function login($username, $password)
    {
        // 1. Ambil data user dan ID Kategorinya
        $sql = "SELECT * FROM TblLogin WHERE Userlogin = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Verifikasi Password
            if (password_verify($password, $row['PassLogin'])) {
                
                // Set Session Dasar
                $_SESSION['username'] = $username;
                $_SESSION['kategoriuser'] = $row['KategoriUser']; // Nama Jabatan (Teks)
                $_SESSION['kodekategori'] = $row['KodeKategori']; // ID Jabatan (Angka) - PENTING!

                // 2. LOAD HAK AKSES DARI DATABASE (MATRIX PERMISSION)
                // Kita ambil semua aturan main untuk jabatan user ini
                $hakAkses = [];
                $sqlHak = "SELECT * FROM tblhakakses WHERE KodeKategori = ?";
                $stmtHak = $this->conn->prepare($sqlHak);
                $stmtHak->bind_param("i", $row['KodeKategori']);
                $stmtHak->execute();
                $resHak = $stmtHak->get_result();

                while($h = $resHak->fetch_assoc()) {
                    // Simpan dalam format array yang mudah dibaca: 
                    // $_SESSION['hak_akses']['Barang']['Edit'] = 1
                    $hakAkses[$h['NamaFitur']] = [
                        'View'   => $h['AksesView'],
                        'Add'    => $h['AksesAdd'],
                        'Edit'   => $h['AksesEdit'],
                        'Delete' => $h['AksesDelete'],
                        'Special'=> $h['AksesSpecial']
                    ];
                }
                
                // Simpan Matrix Akses ke Session
                $_SESSION['hak_akses'] = $hakAkses;

                header("Location: MainForm.php");
                exit;
            } else {
                return "Password salah.";
            }
        } else {
            return "Username tidak ditemukan.";
        }
    }

    // --- FUNGSI BARU: PENGECEKAN AKSES ---
    // Dipanggil di setiap halaman untuk validasi tombol/fitur
    public function cekAkses($fitur, $aksi) {
        // Jika user adalah SuperAdmin (ID=3), berikan akses "Dewa" (bypass semua)
        // Opsional: Hapus baris if ini jika SuperAdmin juga ingin dibatasi tabel
        if (isset($_SESSION['kodekategori']) && $_SESSION['kodekategori'] == 3) {
            return true;
        }

        // Cek di session apakah izinnya bernilai 1
        if (isset($_SESSION['hak_akses'][$fitur][$aksi]) && $_SESSION['hak_akses'][$fitur][$aksi] == 1) {
            return true;
        }
        return false;
    }

    // --- FUNGSI LAMA (TETAP DIPERTAHANKAN) ---
    public function GetNotaNo($Tanggal, $JenisNota)
    {
        $sql = "SELECT FuncGetNotaNo( ? , ? ) AS LastNo; ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $Tanggal, $JenisNota);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc(); 
        return $row['LastNo'];
    }
    
    public function isBarangExist($namabarang,$idkategori){
        $sql = "SELECT NamaBarang FROM viewBarang WHERE NamaBarang = ? AND idKategori = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $namabarang,$idkategori);
        $stmt->execute();
        $result = $stmt->get_result();
        return ($result->num_rows > 0);
    }
}
?>
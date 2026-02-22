<?php
// Sambungkan ke database
include __DIR__ . '/../Database/DBConnection.php';
include __DIR__ . '/../Database/UserLoginAuth.php'; // Wajib include Auth

if ($conn === null) {
    echo json_encode(['success' => false, 'error' => 'koneksi gagal']);
    exit;
}

// --- SECURITY CHECK MULAI ---
$auth = new Auth($conn); // Pastikan class Auth sudah diupdate di file UserLoginAuth.php

$data = json_decode(file_get_contents("php://input"), true);
if ($data === null) {
    echo json_encode(['success' => false, 'error' => 'Data Barang tidak valid !']);
    exit;
}

// Cek apakah ini Insert (0) atau Update
$isNew = ($data['kodebarang'] == "0");

// Jika Baru tapi TIDAK punya akses Add -> Tolak
if ($isNew && !$auth->cekAkses('Barang', 'Add')) {
    echo json_encode(['success' => false, 'error' => 'AKSES DITOLAK: Anda tidak memiliki izin MENAMBAH Barang.']);
    exit;
}
// Jika Update tapi TIDAK punya akses Edit -> Tolak
if (!$isNew && !$auth->cekAkses('Barang', 'Edit')) {
    echo json_encode(['success' => false, 'error' => 'AKSES DITOLAK: Anda tidak memiliki izin MENGEDIT Barang.']);
    exit;
}
// --- SECURITY CHECK SELESAI ---

// Validasi data
if (!isset($data['namabarang'], $data['idkategori'], $data['satuan'])) {
    echo json_encode(['success' => false, 'error' => 'Data barang harus lengkap sebelum disimpan !']);
    exit;
}

// Ambil nilai dari data
$kodebarang = $data['kodebarang'];
$namabarang = $data['namabarang'];
$idkategori = $data['idkategori'];
$satuan = $data['satuan'];
$hargabeli = $data['hargabeli'];
$hargajual = $data['hargajual'];
$catatan = $data['catatan'];
$lamaorder = $data['lamaorder'];

// Simpan data ke database
if ($kodebarang == "0") {
    $message = $auth->isBarangExist($namabarang, $idkategori);
    if ($message === true) {
        echo json_encode(['success' => false, 'error' => 'Barang sudah ada']);
        exit;
    }
    $sql = "INSERT INTO TblBarang (NamaBarang,idKategori,Satuan,HargaBeli,HargaJual,HPP,Jumlah,LamaOrder,Catatan,Aktif) VALUES (?,?,?,?,?,0,0,?,?,'1')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $namabarang, $idkategori, $satuan, $hargabeli, $hargajual, $lamaorder, $catatan);
} else {
    $sql = "UPDATE TblBarang SET NamaBarang=?,idKategori=?,Satuan=?,HargaBeli=?,HargaJual=?,LamaOrder=?,Catatan=? WHERE KodeBarang=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $namabarang, $idkategori, $satuan, $hargabeli, $hargajual, $lamaorder, $catatan, $kodebarang);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
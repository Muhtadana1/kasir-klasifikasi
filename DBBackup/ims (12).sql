-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 22, 2026 at 03:25 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ims`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `SimulasiTransaksiKasir` ()   BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE total_transaksi INT DEFAULT 20; -- Jumlah transaksi yang akan dibuat
    DECLARE nota_baru VARCHAR(20);
    DECLARE tgl_transaksi DATE;
    DECLARE jam_transaksi TIME;
    DECLARE jumlah_item INT;
    DECLARE k INT;
    DECLARE id_barang_acak INT;
    DECLARE qty_acak INT;
    DECLARE harga_jual DECIMAL(18,2);
    DECLARE total_belanja DECIMAL(18,2);
    DECLARE id_detail INT;
    DECLARE nm_barang VARCHAR(255);

    -- ==========================================================================
    -- 1. BERSIHKAN DATA SIMULASI LAMA (Supaya tidak Error Duplicate)
    -- ==========================================================================
    
    -- Hapus Kartu Stok (Ini akan otomatis mengembalikan stok barang karena Trigger)
    DELETE FROM tblkartustok WHERE idTransaksi LIKE 'POS-TEST-%';
    
    -- Hapus Detail Transaksi
    DELETE FROM tblpenjualankasirdetail WHERE NoNota LIKE 'POS-TEST-%';
    
    -- Hapus Header Transaksi
    DELETE FROM tblpenjualankasir WHERE NoNota LIKE 'POS-TEST-%';
    
    -- ==========================================================================
    -- 2. MULAI BUAT TRANSAKSI BARU
    -- ==========================================================================
    
    WHILE i <= total_transaksi DO
        -- Buat Nomor Nota (POS-TEST-0001 s/d 0020)
        SET nota_baru = CONCAT('POS-TEST-', LPAD(i, 4, '0'));
        
        -- Acak Tanggal (Dalam bulan ini)
        SET tgl_transaksi = DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL FLOOR(RAND() * 28) DAY);
        SET jam_transaksi = SEC_TO_TIME(FLOOR(RAND() * 86400));

        -- Insert Header
        INSERT INTO `tblpenjualankasir` (`NoNota`, `Tanggal`, `Jam`, `SubTotal`, `GrandTotal`, `Bayar`, `Kembali`, `KodeUser`)
        VALUES (nota_baru, tgl_transaksi, jam_transaksi, 0, 0, 0, 0, 1);

        -- Tentukan jumlah jenis barang (1-4 jenis per nota)
        SET jumlah_item = FLOOR(1 + (RAND() * 4));
        SET k = 1;
        SET total_belanja = 0;

        -- Loop Detail Barang
        WHILE k <= jumlah_item DO
            -- Pilih Barang Acak yang Stoknya Masih Ada (>5) agar tidak minus
            SELECT KodeBarang, HargaJual, NamaBarang INTO id_barang_acak, harga_jual, nm_barang 
            FROM tblbarang 
            WHERE Jumlah > 5 
            ORDER BY RAND() LIMIT 1;

            IF id_barang_acak IS NOT NULL THEN
                -- Beli sedikit saja (1-3 pcs) agar variasi banyak tapi stok aman
                SET qty_acak = FLOOR(1 + (RAND() * 3));
                
                -- Insert Detail
                INSERT INTO `tblpenjualankasirdetail` (`NoNota`, `idBarang`, `Jumlah`, `HargaSatuan`, `TotalHarga`)
                VALUES (nota_baru, id_barang_acak, qty_acak, harga_jual, (harga_jual * qty_acak));
                
                SET id_detail = LAST_INSERT_ID();
                SET total_belanja = total_belanja + (harga_jual * qty_acak);

                -- Update Kartu Stok (Agar Laporan Jalan)
                INSERT INTO `tblkartustok` (`idBarang`, `Tanggal`, `Keluar`, `JenisTransaksi`, `idTransaksi`, `idDetailTransaksi`, `CatatanKS`, `Fisik`)
                VALUES (id_barang_acak, tgl_transaksi, qty_acak, 'KASIR', nota_baru, id_detail, CONCAT('Simulasi: ', nm_barang), (qty_acak * -1));

                -- Update Stok Master
                UPDATE `tblbarang` SET Jumlah = Jumlah - qty_acak WHERE KodeBarang = id_barang_acak;
            END IF;

            SET k = k + 1;
        END WHILE;

        -- Update Total Akhir di Header
        UPDATE `tblpenjualankasir` 
        SET SubTotal = total_belanja, GrandTotal = total_belanja, Bayar = total_belanja 
        WHERE NoNota = nota_baru;

        SET i = i + 1;
    END WHILE;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `spApproveRetur` (IN `p_NoRetur` VARCHAR(20), IN `p_AdminID` INT)   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_idBarang INT;
    DECLARE v_Jumlah INT;
    DECLARE v_Tanggal DATE;
    DECLARE v_Alasan VARCHAR(100);
    DECLARE v_AutoNumDetail INT;
    
    -- Cursor untuk meloop barang apa saja yang diretur di nota ini
    DECLARE curDetail CURSOR FOR 
        SELECT idBarang, Jumlah, Alasan, AutoNum 
        FROM tblreturpenjualandetail 
        WHERE NoRetur = p_NoRetur;
        
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    -- Ambil Tanggal Retur
    SELECT Tanggal INTO v_Tanggal FROM tblreturpenjualan WHERE NoRetur = p_NoRetur;

    -- Mulai Loop
    OPEN curDetail;
    read_loop: LOOP
        FETCH curDetail INTO v_idBarang, v_Jumlah, v_Alasan, v_AutoNumDetail;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- A. Tambah Stok Fisik di Master Barang
        UPDATE tblbarang 
        SET Jumlah = Jumlah + v_Jumlah 
        WHERE KodeBarang = v_idBarang;

        -- B. Catat di Kartu Stok (Masuk)
        -- Gunakan SP yang sudah ada (spInsertKS)
        CALL spInsertKS(v_idBarang, v_Tanggal, v_Jumlah, 'RETUR', p_NoRetur, v_AutoNumDetail, CONCAT('Retur: ', v_Alasan));
        
    END LOOP;
    CLOSE curDetail;

    -- C. Update Status Header jadi APPROVED
    UPDATE tblreturpenjualan 
    SET Status = 'APPROVED', ApprovedBy = p_AdminID 
    WHERE NoRetur = p_NoRetur;

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `spDeleteKS` (IN `JenisTransaksi1` VARCHAR(50), IN `idTransaksi1` VARCHAR(50), IN `idDetailTransksi1` VARCHAR(50))  DETERMINISTIC BEGIN
	DELETE FROM tblkartustok WHERE JenisTransaksi=JenisTransaksi1 AND idTransaksi=idTransaksi1 AND idDetailTransaksi=idDetailTransksi1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `spInsertKS` (IN `idBarang1` INT, IN `Tanggal1` DATE, IN `Jumlah1` SMALLINT, IN `JenisTransaksi1` VARCHAR(50), IN `idTransaksi1` VARCHAR(50), IN `idDetailTransaksi1` VARCHAR(50), IN `Catatan1` TINYTEXT)   BEGIN
    -- Logika PO (Masuk ke Stok PO & Fisik tertunda)
    IF JenisTransaksi1='PO' THEN
        INSERT INTO tblkartustok (idBarang, Tanggal, Masuk, JenisTransaksi, idTransaksi, idDetailTransaksi, CatatanKS, PO) 
        VALUES (idBarang1, Tanggal1, Jumlah1, JenisTransaksi1, idTransaksi1, idDetailTransaksi1, Catatan1, Jumlah1);

    -- Logika BL (Stok PO berkurang, Stok Fisik Bertambah)
    ELSEIF JenisTransaksi1='BL' THEN
        INSERT INTO tblkartustok (idBarang, Tanggal, Masuk, JenisTransaksi, idTransaksi, idDetailTransaksi, CatatanKS, Fisik, PO, BL) 
        VALUES (idBarang1, Tanggal1, Jumlah1, JenisTransaksi1, idTransaksi1, idDetailTransaksi1, Catatan1, Jumlah1, (Jumlah1 * -1), Jumlah1);

    -- Logika SO (Masuk ke Stok SO & Fisik tertunda)
    ELSEIF JenisTransaksi1='SO' THEN
        INSERT INTO tblkartustok (idBarang, Tanggal, JenisTransaksi, idTransaksi, idDetailTransaksi, CatatanKS, SO) 
        VALUES (idBarang1, Tanggal1, JenisTransaksi1, idTransaksi1, idDetailTransaksi1, Catatan1, Jumlah1);

    -- Logika SJ (Stok SO berkurang, Stok Fisik Berkurang)
    ELSEIF JenisTransaksi1='SJ' THEN
        INSERT INTO tblkartustok (idBarang, Tanggal, Keluar, JenisTransaksi, idTransaksi, idDetailTransaksi, CatatanKS, Fisik, SO, SJ) 
        VALUES (idBarang1, Tanggal1, Jumlah1, JenisTransaksi1, idTransaksi1, idDetailTransaksi1, Catatan1, (Jumlah1 * -1), (Jumlah1 * -1), Jumlah1);

    -- ============================================================
    -- LOGIKA BARU: KASIR (POS)
    -- Langsung mengurangi Stok Fisik (Tanpa pesanan/SO)
    -- ============================================================
    ELSEIF JenisTransaksi1='KASIR' THEN
        INSERT INTO tblkartustok (idBarang, Tanggal, Keluar, JenisTransaksi, idTransaksi, idDetailTransaksi, CatatanKS, Fisik) 
        VALUES (idBarang1, Tanggal1, Jumlah1, 'KASIR', idTransaksi1, idDetailTransaksi1, Catatan1, (Jumlah1 * -1));
    
    END IF;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `FuncGetNotaNo` (`Tanggal` DATE, `JenisNota` VARCHAR(3)) RETURNS VARCHAR(30) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC BEGIN
   DECLARE result VARCHAR(30);
   DECLARE urutan INT;

    /* PERBAIKAN: 
    Query diubah dari 'view...' menjadi tabel master ('tblpo', 'tblso', etc.)
    untuk mencegah error jika ada data orphaned (customer/supplier terhapus) 
    yang menyebabkan VIEW tidak menampilkan MAX Kode yang sebenarnya.
    */

    IF JenisNota='PO' THEN
        SELECT MAX(KodePO)
        INTO result
        FROM tblpo -- DIGANTI DARI viewdaftarpo
        WHERE KodePO LIKE CONCAT(JenisNota, '-', YEAR(Tanggal) , '-', '%');
    ELSEIF JenisNota='BL' THEN
        SELECT MAX(KodePenerimaan)
        INTO result
        FROM tblpenerimaanbarang -- DIGANTI DARI viewdaftarbl
        WHERE KodePenerimaan LIKE CONCAT(JenisNota, '-', YEAR(Tanggal) , '-', '%');
    ELSEIF JenisNota='SO' THEN
        SELECT MAX(KodeSO)
        INTO result
        FROM tblso -- DIGANTI DARI viewdaftarSO
        WHERE KodeSO LIKE CONCAT(JenisNota, '-', YEAR(Tanggal) , '-', '%');
    ELSEIF JenisNota='SJ' THEN
        SELECT MAX(KodeSJ)
        INTO result
        FROM tblsuratjalan -- DIGANTI DARI viewdaftarSJ
        WHERE KodeSJ LIKE CONCAT(JenisNota, '-', YEAR(Tanggal) , '-', '%');
    END IF;

    IF result IS NULL THEN
        SET result = CONCAT(JenisNota, '-', YEAR(Tanggal) , '-000001');
    ELSE
        SET urutan = RIGHT(result,6) + 1;
        SET result = LPAD(urutan, 6, '0');
        SET result = CONCAT(JenisNota, '-', YEAR(Tanggal) , '-' ,result);
    END IF;
    RETURN result;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `FuncIsBarangHarusOrder` (`idBarang` SMALLINT, `LamaOrder` TINYINT, `SaldoAkhir` SMALLINT) RETURNS CHAR(1) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC BEGIN
	DECLARE JumlahJualAverage DECIMAL(8,2);
	DECLARE TglAwal DATE;
	DECLARE TglSampai DATE;
	DECLARE isHarusOrder CHAR(1);
	DECLARE JumlahHari TINYINT;
	SET TglSampai = CURDATE();
	SET TglAwal = DATE_ADD(CURDATE(), INTERVAL -2 MONTH);
	SET JumlahHari = DATEDIFF(TglSampai,TglAwal);
	SELECT SUM(JumlahDetSO) INTO JumlahJualAverage  FROM viewdaftarsodetailbarang INNER JOIN viewdaftarso ON idSO=KodeSO WHERE idBarang=idBarang AND Aktif='1' AND Tanggal BETWEEN TglAwal AND TglSampai;
	SET JumlahJualAverage=JumlahJualAverage/JumlahHari;
	IF (JumlahJualAverage=0) THEN
		SET isHarusOrder='0';
	ELSE
		IF ((SaldoAkhir/JumlahJualAverage)<LamaOrder) THEN
			SET isHarusOrder='1';
		ELSE
			SET isHarusOrder='0';
		END IF;
	
	END IF;
	RETURN isHarusOrder;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `FuncPOGetTotalBL` (`KodePO` VARCHAR(30)) RETURNS SMALLINT(6) DETERMINISTIC BEGIN
	DECLARE JmlTerima SMALLINT;
	
	SELECT 
	        IFNULL(SUM(tblpodetailbarang.JumlahDiterima), 0)
	    INTO 
	        JmlTerima
	    FROM 
	        tblpodetailbarang
	    WHERE 
	        idPO = KodePO;
	RETURN JmlTerima;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `FuncSOgetTotalSJ` (`KodeSO` VARCHAR(20)) RETURNS SMALLINT(6) DETERMINISTIC BEGIN
	DECLARE JmlSJ SMALLINT;
	SELECT 
	   IFNULL(SUM(TblSODetailBarang.JumlahSJ), 0)
	   INTO 
	        JmlSJ
	    FROM 
	        TblSODetailBarang
	    WHERE 
	        idSO = KodeSO;
	RETURN JmlSJ;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tblbarang`
--

CREATE TABLE `tblbarang` (
  `KodeBarang` smallint(6) NOT NULL,
  `NamaBarang` varchar(255) NOT NULL,
  `Satuan` varchar(100) NOT NULL,
  `idKategori` tinyint(4) NOT NULL,
  `HargaBeli` decimal(20,0) NOT NULL DEFAULT 0,
  `HargaJual` decimal(20,0) NOT NULL DEFAULT 0,
  `HPP` decimal(20,0) NOT NULL DEFAULT 0,
  `Jumlah` smallint(6) NOT NULL DEFAULT 0,
  `LamaOrder` tinyint(4) NOT NULL DEFAULT 0,
  `Catatan` tinytext NOT NULL,
  `Aktif` char(1) NOT NULL DEFAULT '1',
  `PO` smallint(6) NOT NULL DEFAULT 0,
  `SO` smallint(6) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblbarang`
--

INSERT INTO `tblbarang` (`KodeBarang`, `NamaBarang`, `Satuan`, `idKategori`, `HargaBeli`, `HargaJual`, `HPP`, `Jumlah`, `LamaOrder`, `Catatan`, `Aktif`, `PO`, `SO`) VALUES
(1, 'Semen Gresik 40kg (Sak)', '1', 1, 48000, 53000, 0, 91, 3, '', '1', 0, 0),
(2, 'Semen Gresik 50kg (Sak)', '1', 1, 58000, 64000, 0, 80, 3, '', '1', 0, 0),
(3, 'Semen Tiga Roda 40kg (Sak)', '1', 1, 47000, 52000, 0, 100, 3, '', '1', 0, 0),
(4, 'Semen Tiga Roda 50kg (Sak)', '1', 1, 57000, 63000, 0, 80, 3, '', '1', 0, 0),
(5, 'Semen Holcim 40kg (Sak)', '1', 1, 46000, 51000, 0, 50, 3, '', '1', 0, 0),
(6, 'Semen Putih Tiga Roda (Sak)', '1', 1, 95000, 110000, 0, 20, 3, '', '1', 0, 0),
(7, 'Semen Instan MU-200 (Sak)', '1', 1, 120000, 135000, 0, 24, 3, '', '1', 0, 0),
(8, 'Semen Instan MU-380 (Sak)', '1', 1, 95000, 110000, 0, 30, 3, '', '1', 0, 0),
(9, 'Semen Warna Nat AM-50 (Bks)', '2', 1, 15000, 18000, 0, 50, 3, '', '1', 0, 0),
(10, 'Pasir Muntilan (M3)', '3', 1, 250000, 300000, 0, 13, 3, '', '1', 0, 0),
(11, 'Bata Merah (Pcs)', '4', 1, 800, 1200, 0, 495, 3, '', '1', 0, 0),
(28, 'Pipa PVC Maspion AW 1/2\" (Btg)', '7', 3, 25000, 30000, 0, 100, 3, '', '1', 0, 0),
(29, 'Pipa PVC Maspion AW 3/4\" (Btg)', '7', 3, 35000, 40000, 0, 80, 3, '', '1', 0, 0),
(30, 'Pipa PVC Maspion D 3\" (Btg)', '7', 3, 85000, 100000, 0, 35, 3, '', '1', 0, 0),
(31, 'Pipa PVC Maspion D 4\" (Btg)', '7', 3, 110000, 130000, 0, 27, 3, '', '1', 0, 0),
(32, 'Pipa PVC Rucika AW 1/2\" (Btg)', '7', 3, 32000, 38000, 0, 60, 3, '', '1', 0, 0),
(33, 'Pipa PVC Rucika AW 3/4\" (Btg)', '7', 3, 42000, 50000, 0, 48, 3, '', '1', 0, 0),
(34, 'Pipa Galvanis Medium 1/2\" (Btg)', '7', 3, 120000, 140000, 0, 15, 3, '', '1', 0, 0),
(35, 'Pipa Galvanis Medium 3/4\" (Btg)', '7', 3, 155000, 180000, 0, 15, 3, '', '1', 0, 0),
(36, 'Pipa Galvanis Medium 1\" (Btg)', '7', 3, 210000, 240000, 0, 10, 3, '', '1', 0, 0),
(37, 'Knee PVC 1/2\" (Pcs)', '4', 3, 2500, 4000, 0, 100, 3, '', '1', 0, 0),
(38, 'Tee PVC 1/2\" (Pcs)', '4', 3, 3500, 5000, 0, 79, 3, '', '1', 0, 0),
(39, 'Sock Drat Luar 1/2\" (Pcs)', '4', 3, 3000, 5000, 0, 100, 3, '', '1', 0, 0),
(40, 'Knee PVC 3/4\" (Pcs)', '4', 3, 3500, 5000, 0, 80, 3, '', '1', 0, 0),
(41, 'Knee Besi Galvanis 1/2\" (Pcs)', '4', 3, 7000, 10000, 0, 50, 3, '', '1', 0, 0),
(42, 'Tee Besi Galvanis 1/2\" (Pcs)', '4', 3, 10000, 14000, 0, 40, 3, '', '1', 0, 0),
(43, 'Double Nepple 1/2\" (Pcs)', '4', 3, 5000, 8000, 0, 60, 3, '', '1', 0, 0),
(44, 'Seal Tape Onda 10m (Pcs)', '4', 3, 3000, 5000, 0, 99, 3, '', '1', 0, 0),
(45, 'Lem Pipa Isarplas Tube (Pcs)', '4', 3, 8500, 11000, 0, 100, 3, '', '1', 0, 0),
(46, 'Kran Plastik PVC 1/2\" (Pcs)', '4', 3, 10000, 15000, 0, 47, 3, '', '1', 0, 0),
(47, 'Kran Besi Onda 1/2\" (Pcs)', '4', 3, 25000, 35000, 0, 40, 3, '', '1', 0, 0),
(48, 'Besi Beton Polos 8mm (Btg)', '7', 4, 48000, 55000, 0, 80, 3, '', '1', 0, 0),
(49, 'Besi Beton Polos 10mm (Btg)', '7', 4, 72000, 85000, 0, 60, 3, '', '1', 0, 0),
(50, 'Besi Beton Ulir 13mm (Btg)', '7', 4, 110000, 130000, 0, 7, 3, '', '1', 0, 0),
(51, 'Kanal C 0.75mm Galvalum (Btg)', '7', 4, 75000, 85000, 0, 100, 3, '', '1', 0, 0),
(52, 'Reng 0.45mm Galvalum (Btg)', '7', 4, 35000, 42000, 0, 95, 3, '', '1', 0, 0),
(53, 'Hollow 2x4 Galvalum (Btg)', '7', 4, 18000, 24000, 0, 95, 3, '', '1', 0, 0),
(54, 'Hollow 4x4 Galvalum (Btg)', '7', 4, 25000, 32000, 0, 97, 3, '', '1', 0, 0),
(55, 'Kawat Bendrat (Roll)', '8', 4, 18000, 25000, 0, 50, 3, '', '1', 0, 0),
(56, 'Kabel Eterna NYA 1.5mm Hitam (Roll)', '8', 5, 320000, 350000, 0, 20, 3, '', '1', 0, 0),
(57, 'Kabel Eterna NYA 2.5mm Hitam (Roll)', '8', 5, 480000, 530000, 0, 15, 3, '', '1', 0, 0),
(58, 'Kabel Eterna NYA 1.5mm (Meter)', '9', 5, 3500, 5000, 0, 187, 3, '', '1', 0, 0),
(59, 'Kabel Eterna NYA 2.5mm (Meter)', '9', 5, 5500, 7000, 0, 184, 3, '', '1', 0, 0),
(60, 'Kabel Eterna NYM 2x1.5mm (Meter)', '9', 5, 8500, 10500, 0, 150, 3, '', '1', 0, 0),
(61, 'Kabel Eterna NYM 2x2.5mm (Meter)', '9', 5, 11000, 13500, 0, 150, 3, '', '1', 0, 0),
(62, 'Kabel Eterna NYM 3x1.5mm (Meter)', '9', 5, 11000, 13500, 0, 93, 3, '', '1', 0, 0),
(63, 'Kabel Eterna NYM 3x2.5mm (Meter)', '9', 5, 16000, 19000, 0, 78, 3, '', '1', 0, 0),
(64, 'Kabel Eterna NYY 2x1.5mm (Meter)', '9', 5, 10000, 13000, 0, 83, 3, '', '1', 0, 0),
(65, 'Kabel Serabut 2x0.75mm (Meter)', '9', 5, 3000, 5000, 0, 300, 3, '', '1', 0, 0),
(66, 'Kabel Serabut Putih 2x1.5mm (Meter)', '9', 5, 5500, 7500, 0, 133, 3, '', '1', 0, 0),
(67, 'Kabel Twist/SR 2x10mm (Meter)', '9', 5, 4500, 6500, 0, 299, 3, '', '1', 0, 0),
(68, 'Kabel Antena TV Kitani 5C (Meter)', '9', 5, 4000, 6000, 0, 96, 3, '', '1', 0, 0),
(69, 'Lampu Philips LED 10 Watt (Pcs)', '4', 5, 38000, 45000, 0, 50, 3, '', '1', 0, 0),
(70, 'Lampu Hannochs LED 10 Watt (Pcs)', '4', 5, 18000, 25000, 0, 45, 3, '', '1', 0, 0),
(71, 'Saklar Engkel Broco (Pcs)', '4', 5, 12000, 15000, 0, 41, 3, '', '1', 0, 0),
(72, 'Stop Kontak Broco (Pcs)', '4', 5, 13000, 16000, 0, 59, 3, '', '1', 0, 0),
(73, 'Isolasi Listrik Unibell (Pcs)', '4', 5, 5000, 7000, 0, 79, 3, '', '1', 0, 0),
(74, 'Keramik Asia Tile 40x40 Putih (Dos)', '10', 6, 48000, 55000, 0, 40, 3, '', '1', 0, 0),
(75, 'Keramik Mulia 40x40 Motif Kayu (Dos)', '10', 6, 55000, 65000, 0, 38, 3, '', '1', 0, 0),
(76, 'Keramik Dinding 25x40 Putih (Dos)', '10', 6, 55000, 65000, 0, 30, 3, '', '1', 0, 0),
(77, 'Keramik Lantai KM 25x25 Kasar (Dos)', '10', 6, 50000, 60000, 0, 36, 3, '', '1', 0, 0),
(78, 'Granit Indogress 60x60 Polos (Dos)', '10', 6, 140000, 165000, 0, 19, 3, '', '1', 0, 0),
(79, 'Triplek 3mm 122x244 (Lbr)', '11', 7, 45000, 55000, 0, 50, 3, '', '1', 0, 0),
(80, 'Triplek 8mm 122x244 (Lbr)', '11', 7, 95000, 115000, 0, 24, 3, '', '1', 0, 0),
(81, 'Gypsum Jaya Board 9mm (Lbr)', '11', 7, 60000, 75000, 0, 9, 3, '', '1', 0, 0),
(82, 'GRC Board 4mm (Lbr)', '11', 7, 55000, 68000, 0, 58, 3, '', '1', 0, 0),
(83, 'Asbes Gelombang 180x105 (Lbr)', '11', 7, 45000, 55000, 0, 49, 3, '', '1', 0, 0),
(84, 'Seng Galvalum 0.3mm (Meter)', '9', 7, 35000, 45000, 0, 100, 3, '', '1', 0, 0),
(85, 'Palu Kambing Gagang Kayu (Pcs)', '4', 8, 25000, 35000, 0, 18, 3, '', '1', 0, 0),
(86, 'Gergaji Kayu Cap Mata 18\" (Pcs)', '4', 8, 65000, 80000, 0, 15, 3, '', '1', 0, 0),
(87, 'Meteran 5 Meter (Pcs)', '4', 8, 15000, 25000, 0, 35, 3, '', '1', 0, 0),
(88, 'Kuas Cat 3 Inch (Pcs)', '4', 8, 8000, 12000, 0, 76, 3, '', '1', 0, 0),
(89, 'Roll Cat Tembok Set (Pcs)', '4', 8, 22000, 30000, 0, 37, 3, '', '1', 0, 0),
(90, 'Bak Cat Plastik (Pcs)', '4', 8, 8000, 12000, 0, 50, 3, '', '1', 0, 0),
(91, 'Cethok Semen Galur (Pcs)', '4', 8, 15000, 20000, 0, 50, 3, '', '1', 0, 0),
(92, 'Sekop Pasir Crocodile (Pcs)', '4', 8, 65000, 80000, 0, 20, 3, '', '1', 0, 0),
(93, 'Cangkul Buaya (Kepala)', '4', 8, 45000, 60000, 0, 19, 3, '', '1', 0, 0),
(94, 'Waterpass 18 Inch (Pcs)', '4', 8, 25000, 35000, 0, 15, 3, '', '1', 0, 0),
(95, 'Mata Bor Besi 6mm (Pcs)', '4', 8, 12000, 16000, 0, 80, 3, '', '1', 0, 0),
(96, 'Mata Bor Beton 10mm (Pcs)', '4', 8, 22000, 28000, 0, 58, 3, '', '1', 0, 0),
(97, 'Obeng Bolak Balik Tekiro', '4', 8, 25000, 35000, 0, 29, 3, '', '1', 0, 0),
(98, 'Tang Kombinasi 8\" (Pcs)', '4', 8, 30000, 45000, 0, 25, 3, '', '1', 0, 0),
(99, 'HCL Murni / Air Keras (Btl)', '12', 9, 10000, 15000, 0, 50, 3, '', '1', 0, 0),
(100, 'HCL Bubuk / Serbuk Ajaib (Bks)', '2', 9, 10000, 15000, 0, 50, 3, '', '1', 0, 0),
(101, 'Soda Api / Caustic Soda (Bks)', '2', 9, 15000, 20000, 0, 37, 3, '', '1', 0, 0),
(102, 'Porstex Ungu 1000ml (Btl)', '12', 9, 18000, 23000, 0, 45, 3, '', '1', 0, 0),
(103, 'Vixal Kuat Harum 780ml (Btl)', '12', 9, 16000, 20000, 0, 60, 3, '', '1', 0, 0),
(104, 'Lem Rajawali Putih 350g (Bks)', '2', 9, 10000, 13000, 0, 50, 3, '', '1', 0, 0),
(105, 'Lem Fox Kuning 70g (Klg)', '5', 9, 9000, 12000, 0, 59, 3, '', '1', 0, 0),
(106, 'Lem Sealant Silikon Clear', '12', 9, 22000, 30000, 0, 25, 3, '', '1', 0, 0),
(107, 'Lem G / Korea (Pcs)', '4', 9, 5000, 8000, 0, 94, 3, '', '1', 0, 0),
(108, 'Paku Kayu 5cm (Kg)', '13', 10, 16000, 20000, 0, 39, 3, '', '1', 0, 0),
(109, 'Paku Kayu 7cm (Kg)', '13', 10, 16000, 20000, 0, 56, 3, '', '1', 0, 0),
(110, 'Paku Beton 3cm (Dos)', '10', 10, 25000, 35000, 0, 34, 3, '', '1', 0, 0),
(111, 'Paku Payung Seng (Kg)', '13', 10, 25000, 30000, 0, 27, 3, '', '1', 0, 0),
(112, 'Amplas Kertas No 100 (Lbr)', '11', 10, 2500, 5000, 0, 73, 3, '', '1', 0, 0),
(113, 'Ember Cor Hitam (Pcs)', '4', 10, 5000, 8000, 0, 192, 3, '', '1', 0, 0),
(114, 'Karung Plastik Putih (Lbr)', '11', 10, 1500, 3000, 0, 486, 3, '', '1', 0, 0),
(115, 'Terpal Plastik A3 2x3m', '11', 10, 25000, 35000, 0, 7, 3, '', '1', 0, 0),
(116, 'Sapu Lidi Kasur/Taman', '4', 10, 8000, 12000, 0, 60, 3, '', '1', 0, 0),
(117, 'Sikat Kawat Tangan (Pcs)', '4', 8, 5000, 8000, 0, 60, 3, '', '1', 0, 0),
(118, 'Dulux Catylac Interior Putih 5kg', '5', 2, 135000, 148500, 0, 13, 3, 'Interior', '1', 0, 0),
(119, 'Dulux Catylac Interior Putih 25kg', '17', 2, 650000, 715000, 0, 32, 5, 'Interior Jumbo', '1', 0, 0),
(120, 'Dulux Catylac Interior Merah 5kg', '5', 2, 135000, 148500, 0, 14, 3, 'Interior', '1', 0, 0),
(121, 'Dulux Catylac Interior Merah 25kg', '17', 2, 650000, 715000, 0, 43, 5, 'Interior Jumbo', '1', 0, 0),
(122, 'Dulux Catylac Interior Biru 5kg', '5', 2, 135000, 148500, 0, 10, 3, 'Interior', '1', 0, 0),
(123, 'Dulux Catylac Interior Biru 25kg', '17', 2, 650000, 715000, 0, 43, 5, 'Interior Jumbo', '1', 0, 0),
(124, 'Dulux Catylac Interior Hijau 5kg', '5', 2, 135000, 148500, 0, 10, 3, 'Interior', '1', 0, 0),
(125, 'Dulux Catylac Interior Hijau 25kg', '17', 2, 650000, 715000, 0, 24, 5, 'Interior Jumbo', '1', 0, 0),
(126, 'Dulux Catylac Interior Kuning 5kg', '5', 2, 135000, 148500, 0, 15, 3, 'Interior', '1', 0, 0),
(127, 'Dulux Catylac Interior Kuning 25kg', '17', 2, 650000, 715000, 0, 46, 5, 'Interior Jumbo', '1', 0, 0),
(128, 'Dulux Catylac Interior Abu-abu 5kg', '5', 2, 135000, 148500, 0, 14, 3, 'Interior', '1', 0, 0),
(129, 'Dulux Catylac Interior Abu-abu 25kg', '17', 2, 650000, 715000, 0, 23, 5, 'Interior Jumbo', '1', 0, 0),
(130, 'Vinilex Interior Putih 5kg', '5', 2, 125000, 137500, 0, 10, 3, 'Interior', '1', 0, 0),
(131, 'Vinilex Interior Putih 25kg', '17', 2, 550000, 605000, 0, 34, 5, 'Interior Jumbo', '1', 0, 0),
(132, 'Vinilex Interior Merah 5kg', '5', 2, 125000, 137500, 0, 14, 3, 'Interior', '1', 0, 0),
(133, 'Vinilex Interior Merah 25kg', '17', 2, 550000, 605000, 0, 33, 5, 'Interior Jumbo', '1', 0, 0),
(134, 'Vinilex Interior Biru 5kg', '5', 2, 125000, 137500, 0, 16, 3, 'Interior', '1', 0, 0),
(135, 'Vinilex Interior Biru 25kg', '17', 2, 550000, 605000, 0, 31, 5, 'Interior Jumbo', '1', 0, 0),
(136, 'Vinilex Interior Hijau 5kg', '5', 2, 125000, 137500, 0, 18, 3, 'Interior', '1', 0, 0),
(137, 'Vinilex Interior Hijau 25kg', '17', 2, 550000, 605000, 0, 38, 5, 'Interior Jumbo', '1', 0, 0),
(138, 'Vinilex Interior Kuning 5kg', '5', 2, 125000, 137500, 0, 19, 3, 'Interior', '1', 0, 0),
(139, 'Vinilex Interior Kuning 25kg', '17', 2, 550000, 605000, 0, 48, 5, 'Interior Jumbo', '1', 0, 0),
(140, 'Vinilex Interior Abu-abu 5kg', '5', 2, 125000, 137500, 0, 21, 3, 'Interior', '1', 0, 0),
(141, 'Vinilex Interior Abu-abu 25kg', '17', 2, 550000, 605000, 0, 34, 5, 'Interior Jumbo', '1', 0, 0),
(142, 'Avitex Interior Putih 5kg', '5', 2, 110000, 121000, 0, 23, 3, 'Interior', '1', 0, 0),
(143, 'Avitex Interior Putih 25kg', '17', 2, 480000, 528000, 0, 43, 5, 'Interior Jumbo', '1', 0, 0),
(144, 'Avitex Interior Merah 5kg', '5', 2, 110000, 121000, 0, 20, 3, 'Interior', '1', 0, 0),
(145, 'Avitex Interior Merah 25kg', '17', 2, 480000, 528000, 0, 32, 5, 'Interior Jumbo', '1', 0, 0),
(146, 'Avitex Interior Biru 5kg', '5', 2, 110000, 121000, 0, 19, 3, 'Interior', '1', 0, 0),
(147, 'Avitex Interior Biru 25kg', '17', 2, 480000, 528000, 0, 32, 5, 'Interior Jumbo', '1', 0, 0),
(148, 'Avitex Interior Hijau 5kg', '5', 2, 110000, 121000, 0, 21, 3, 'Interior', '1', 0, 0),
(149, 'Avitex Interior Hijau 25kg', '17', 2, 480000, 528000, 0, 23, 5, 'Interior Jumbo', '1', 0, 0),
(150, 'Avitex Interior Kuning 5kg', '5', 2, 110000, 121000, 0, 14, 3, 'Interior', '1', 0, 0),
(151, 'Avitex Interior Kuning 25kg', '17', 2, 480000, 528000, 0, 12, 5, 'Interior Jumbo', '1', 0, 0),
(152, 'Avitex Interior Abu-abu 5kg', '5', 2, 110000, 121000, 0, 4, 3, 'Interior', '1', 0, 0),
(153, 'Avitex Interior Abu-abu 25kg', '17', 2, 480000, 528000, 0, 39, 5, 'Interior Jumbo', '1', 0, 0),
(154, 'Paragon Interior Putih 5kg', '5', 2, 105000, 115500, 0, 19, 3, 'Interior', '1', 0, 0),
(155, 'Paragon Interior Putih 25kg', '17', 2, 450000, 495000, 0, 20, 5, 'Interior Jumbo', '1', 0, 0),
(156, 'Paragon Interior Merah 5kg', '5', 2, 105000, 115500, 0, 15, 3, 'Interior', '1', 0, 0),
(157, 'Paragon Interior Merah 25kg', '17', 2, 450000, 495000, 0, 23, 5, 'Interior Jumbo', '1', 0, 0),
(158, 'Paragon Interior Biru 5kg', '5', 2, 105000, 115500, 0, 14, 3, 'Interior', '1', 0, 0),
(159, 'Paragon Interior Biru 25kg', '17', 2, 450000, 495000, 0, 32, 5, 'Interior Jumbo', '1', 0, 0),
(160, 'Paragon Interior Hijau 5kg', '5', 2, 105000, 115500, 0, 14, 3, 'Interior', '1', 0, 0),
(161, 'Paragon Interior Hijau 25kg', '17', 2, 450000, 495000, 0, 22, 5, 'Interior Jumbo', '1', 0, 0),
(162, 'Paragon Interior Kuning 5kg', '5', 2, 105000, 115500, 0, 12, 3, 'Interior', '1', 0, 0),
(163, 'Paragon Interior Kuning 25kg', '17', 2, 450000, 495000, 0, 34, 5, 'Interior Jumbo', '1', 0, 0),
(164, 'Paragon Interior Abu-abu 5kg', '5', 2, 105000, 115500, 0, 12, 3, 'Interior', '1', 0, 0),
(165, 'Paragon Interior Abu-abu 25kg', '17', 2, 450000, 495000, 0, 22, 5, 'Interior Jumbo', '1', 0, 0),
(166, 'Dulux Weathershield Eksterior Putih 2.5L', '5', 2, 285000, 313500, 0, 14, 3, 'Eksterior', '1', 0, 0),
(167, 'Dulux Weathershield Eksterior Putih 20L', '17', 2, 1800000, 1980000, 0, 12, 5, 'Eksterior Jumbo', '1', 0, 0),
(168, 'Dulux Weathershield Eksterior Abu-abu 2.5L', '5', 2, 285000, 313500, 0, 15, 3, 'Eksterior', '1', 0, 0),
(169, 'Dulux Weathershield Eksterior Abu-abu 20L', '17', 2, 1800000, 1980000, 0, 32, 5, 'Eksterior Jumbo', '1', 0, 0),
(170, 'Dulux Weathershield Eksterior Krem 2.5L', '5', 2, 285000, 313500, 0, 12, 3, 'Eksterior', '1', 0, 0),
(171, 'Dulux Weathershield Eksterior Krem 20L', '17', 2, 1800000, 1980000, 0, 21, 5, 'Eksterior Jumbo', '1', 0, 0),
(172, 'Dulux Weathershield Eksterior Merah Bata 2.5L', '5', 2, 285000, 313500, 0, 18, 3, 'Eksterior', '1', 0, 0),
(173, 'Dulux Weathershield Eksterior Merah Bata 20L', '17', 2, 1800000, 1980000, 0, 11, 5, 'Eksterior Jumbo', '1', 0, 0),
(174, 'Dulux Weathershield Eksterior Hijau Lumut 2.5L', '5', 2, 285000, 313500, 0, 21, 3, 'Eksterior', '1', 0, 0),
(175, 'Dulux Weathershield Eksterior Hijau Lumut 20L', '17', 2, 1800000, 1980000, 0, 11, 5, 'Eksterior Jumbo', '1', 0, 0),
(176, 'Dulux Weathershield Eksterior Coklat 2.5L', '5', 2, 285000, 313500, 0, 12, 3, 'Eksterior', '1', 0, 0),
(177, 'Dulux Weathershield Eksterior Coklat 20L', '17', 2, 1800000, 1980000, 0, 11, 5, 'Eksterior Jumbo', '1', 0, 0),
(178, 'Nippon Weatherbond Eksterior Putih 2.5L', '5', 2, 270000, 297000, 0, 14, 3, 'Eksterior', '1', 0, 0),
(179, 'Nippon Weatherbond Eksterior Putih 20L', '17', 2, 1700000, 1870000, 0, 2, 5, 'Eksterior Jumbo', '1', 0, 0),
(180, 'Nippon Weatherbond Eksterior Abu-abu 2.5L', '5', 2, 270000, 297000, 0, 15, 3, 'Eksterior', '1', 0, 0),
(181, 'Nippon Weatherbond Eksterior Abu-abu 20L', '17', 2, 1700000, 1870000, 0, 4, 5, 'Eksterior Jumbo', '1', 0, 0),
(182, 'Nippon Weatherbond Eksterior Krem 2.5L', '5', 2, 270000, 297000, 0, 14, 3, 'Eksterior', '1', 0, 0),
(183, 'Nippon Weatherbond Eksterior Krem 20L', '17', 2, 1700000, 1870000, 0, 8, 5, 'Eksterior Jumbo', '1', 0, 0),
(184, 'Nippon Weatherbond Eksterior Merah Bata 2.5L', '5', 2, 270000, 297000, 0, 8, 3, 'Eksterior', '1', 0, 0),
(185, 'Nippon Weatherbond Eksterior Merah Bata 20L', '17', 2, 1700000, 1870000, 0, 5, 5, 'Eksterior Jumbo', '1', 0, 0),
(186, 'Nippon Weatherbond Eksterior Hijau Lumut 2.5L', '5', 2, 270000, 297000, 0, 10, 3, 'Eksterior', '1', 0, 0),
(187, 'Nippon Weatherbond Eksterior Hijau Lumut 20L', '17', 2, 1700000, 1870000, 0, 12, 5, 'Eksterior Jumbo', '1', 0, 0),
(188, 'Nippon Weatherbond Eksterior Coklat 2.5L', '5', 2, 270000, 297000, 0, 11, 3, 'Eksterior', '1', 0, 0),
(189, 'Nippon Weatherbond Eksterior Coklat 20L', '17', 2, 1700000, 1870000, 0, 15, 5, 'Eksterior Jumbo', '1', 0, 0),
(190, 'Jotun Jotashield Eksterior Putih 2.5L', '5', 2, 290000, 319000, 0, 12, 3, 'Eksterior', '1', 0, 0),
(191, 'Jotun Jotashield Eksterior Putih 20L', '17', 2, 1850000, 2035000, 0, 21, 5, 'Eksterior Jumbo', '1', 0, 0),
(192, 'Jotun Jotashield Eksterior Abu-abu 2.5L', '5', 2, 290000, 319000, 0, 11, 3, 'Eksterior', '1', 0, 0),
(193, 'Jotun Jotashield Eksterior Abu-abu 20L', '17', 2, 1850000, 2035000, 0, 23, 5, 'Eksterior Jumbo', '1', 0, 0),
(194, 'Jotun Jotashield Eksterior Krem 2.5L', '5', 2, 290000, 319000, 0, 9, 3, 'Eksterior', '1', 0, 0),
(195, 'Jotun Jotashield Eksterior Krem 20L', '17', 2, 1850000, 2035000, 0, 23, 5, 'Eksterior Jumbo', '1', 0, 0),
(196, 'Jotun Jotashield Eksterior Merah Bata 2.5L', '5', 2, 290000, 319000, 0, 7, 3, 'Eksterior', '1', 0, 0),
(197, 'Jotun Jotashield Eksterior Merah Bata 20L', '17', 2, 1850000, 2035000, 0, 23, 5, 'Eksterior Jumbo', '1', 0, 0),
(198, 'Jotun Jotashield Eksterior Hijau Lumut 2.5L', '5', 2, 290000, 319000, 0, 15, 3, 'Eksterior', '1', 0, 0),
(199, 'Jotun Jotashield Eksterior Hijau Lumut 20L', '17', 2, 1850000, 2035000, 0, 12, 5, 'Eksterior Jumbo', '1', 0, 0),
(200, 'Jotun Jotashield Eksterior Coklat 2.5L', '5', 2, 290000, 319000, 0, 12, 3, 'Eksterior', '1', 0, 0),
(201, 'Jotun Jotashield Eksterior Coklat 20L', '17', 2, 1850000, 2035000, 0, 21, 5, 'Eksterior Jumbo', '1', 0, 0),
(202, 'No Drop Eksterior Putih 2.5L', '5', 2, 200000, 220000, 0, 19, 3, 'Eksterior', '1', 0, 0),
(203, 'No Drop Eksterior Putih 20L', '17', 2, 1200000, 1320000, 0, 16, 5, 'Eksterior Jumbo', '1', 0, 0),
(204, 'No Drop Eksterior Abu-abu 2.5L', '5', 2, 200000, 220000, 0, 17, 3, 'Eksterior', '1', 0, 0),
(205, 'No Drop Eksterior Abu-abu 20L', '17', 2, 1200000, 1320000, 0, 16, 5, 'Eksterior Jumbo', '1', 0, 0),
(206, 'No Drop Eksterior Krem 2.5L', '5', 2, 200000, 220000, 0, 11, 3, 'Eksterior', '1', 0, 0),
(207, 'No Drop Eksterior Krem 20L', '17', 2, 1200000, 1320000, 0, 14, 5, 'Eksterior Jumbo', '1', 0, 0),
(208, 'No Drop Eksterior Merah Bata 2.5L', '5', 2, 200000, 220000, 0, 12, 3, 'Eksterior', '1', 0, 0),
(209, 'No Drop Eksterior Merah Bata 20L', '17', 2, 1200000, 1320000, 0, 15, 5, 'Eksterior Jumbo', '1', 0, 0),
(210, 'No Drop Eksterior Hijau Lumut 2.5L', '5', 2, 200000, 220000, 0, 12, 3, 'Eksterior', '1', 0, 0),
(211, 'No Drop Eksterior Hijau Lumut 20L', '17', 2, 1200000, 1320000, 0, 11, 5, 'Eksterior Jumbo', '1', 0, 0),
(212, 'No Drop Eksterior Coklat 2.5L', '5', 2, 200000, 220000, 0, 21, 3, 'Eksterior', '1', 0, 0),
(213, 'No Drop Eksterior Coklat 20L', '17', 2, 1200000, 1320000, 0, 12, 5, 'Eksterior Jumbo', '1', 0, 0),
(214, 'Cat Kayu Avian Hitam 1kg', '5', 2, 65000, 74750, 0, 12, 3, 'Minyak', '1', 0, 0),
(215, 'Cat Kayu Avian Putih 1kg', '5', 2, 65000, 74750, 0, 12, 3, 'Minyak', '1', 0, 0),
(216, 'Cat Kayu Avian Merah 1kg', '5', 2, 65000, 74750, 0, 19, 3, 'Minyak', '1', 0, 0),
(217, 'Cat Kayu Avian Biru 1kg', '5', 2, 65000, 74750, 0, 12, 3, 'Minyak', '1', 0, 0),
(218, 'Cat Kayu Avian Coklat 1kg', '5', 2, 65000, 74750, 0, 21, 3, 'Minyak', '1', 0, 0),
(219, 'Cat Kayu Avian Emas 1kg', '5', 2, 65000, 74750, 0, 12, 3, 'Minyak', '1', 0, 0),
(220, 'Cat Kayu Emco Hitam 1kg', '5', 2, 75000, 86250, 0, 13, 3, 'Minyak', '1', 0, 0),
(221, 'Cat Kayu Emco Putih 1kg', '5', 2, 75000, 86250, 0, 19, 3, 'Minyak', '1', 0, 0),
(222, 'Cat Kayu Emco Merah 1kg', '5', 2, 75000, 86250, 0, 10, 3, 'Minyak', '1', 0, 0),
(223, 'Cat Kayu Emco Biru 1kg', '5', 2, 75000, 86250, 0, 9, 3, 'Minyak', '1', 0, 0),
(224, 'Cat Kayu Emco Coklat 1kg', '5', 2, 75000, 86250, 0, 20, 3, 'Minyak', '1', 0, 0),
(225, 'Cat Kayu Emco Emas 1kg', '5', 2, 75000, 86250, 0, 21, 3, 'Minyak', '1', 0, 0),
(226, 'Cat Kayu Ftalit Hitam 1kg', '5', 2, 80000, 92000, 0, 11, 3, 'Minyak', '1', 0, 0),
(227, 'Cat Kayu Ftalit Putih 1kg', '5', 2, 80000, 92000, 0, 21, 3, 'Minyak', '1', 0, 0),
(228, 'Cat Kayu Ftalit Merah 1kg', '5', 2, 80000, 92000, 0, 21, 3, 'Minyak', '1', 0, 0),
(229, 'Cat Kayu Ftalit Biru 1kg', '5', 2, 80000, 92000, 0, 11, 3, 'Minyak', '1', 0, 0),
(230, 'Cat Kayu Ftalit Coklat 1kg', '5', 2, 80000, 92000, 0, 20, 3, 'Minyak', '1', 0, 0),
(231, 'Cat Kayu Ftalit Emas 1kg', '5', 2, 80000, 92000, 0, 16, 3, 'Minyak', '1', 0, 0),
(232, 'Cat Kayu Seiv Hitam 1kg', '5', 2, 60000, 69000, 0, 17, 3, 'Minyak', '1', 0, 0),
(233, 'Cat Kayu Seiv Putih 1kg', '5', 2, 60000, 69000, 0, 19, 3, 'Minyak', '1', 0, 0),
(234, 'Cat Kayu Seiv Merah 1kg', '5', 2, 60000, 69000, 0, 18, 3, 'Minyak', '1', 0, 0),
(235, 'Cat Kayu Seiv Biru 1kg', '5', 2, 60000, 69000, 0, 18, 3, 'Minyak', '1', 0, 0),
(236, 'Cat Kayu Seiv Coklat 1kg', '5', 2, 60000, 69000, 0, 18, 3, 'Minyak', '1', 0, 0),
(237, 'Cat Kayu Seiv Emas 1kg', '5', 2, 60000, 69000, 0, 12, 3, 'Minyak', '1', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tblcustomer`
--

CREATE TABLE `tblcustomer` (
  `KodeCustomer` mediumint(9) NOT NULL,
  `NamaCustomer` varchar(255) NOT NULL DEFAULT '',
  `Alamat` tinytext NOT NULL,
  `Telp` varchar(50) NOT NULL DEFAULT '',
  `NamaSales` varchar(100) NOT NULL DEFAULT '',
  `TelpSales` varchar(50) NOT NULL DEFAULT '',
  `Aktif` char(1) NOT NULL DEFAULT '1',
  `Catatan` tinytext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblcustomer`
--

INSERT INTO `tblcustomer` (`KodeCustomer`, `NamaCustomer`, `Alamat`, `Telp`, `NamaSales`, `TelpSales`, `Aktif`, `Catatan`) VALUES
(1, 'Maju UD', 'surabaya', '08112312323', '', 'erwer', '1', 'werwerwerewr'),
(4, 'pak rt sumirto', 'jombang ', '081358899611', 'sumirto', '', '1', 'kendangan');

-- --------------------------------------------------------

--
-- Table structure for table `tblhakakses`
--

CREATE TABLE `tblhakakses` (
  `id` int(11) NOT NULL,
  `KodeKategori` tinyint(4) NOT NULL,
  `NamaFitur` varchar(50) NOT NULL,
  `AksesView` tinyint(1) DEFAULT 0,
  `AksesAdd` tinyint(1) DEFAULT 0,
  `AksesEdit` tinyint(1) DEFAULT 0,
  `AksesDelete` tinyint(1) DEFAULT 0,
  `AksesSpecial` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblhakakses`
--

INSERT INTO `tblhakakses` (`id`, `KodeKategori`, `NamaFitur`, `AksesView`, `AksesAdd`, `AksesEdit`, `AksesDelete`, `AksesSpecial`) VALUES
(1, 3, 'Barang', 1, 1, 1, 1, 1),
(2, 3, 'Supplier', 1, 1, 1, 1, 1),
(3, 3, 'Customer', 1, 1, 1, 1, 1),
(4, 3, 'User', 1, 1, 1, 1, 1),
(5, 3, 'PO', 1, 1, 1, 1, 1),
(6, 3, 'BL', 1, 1, 1, 1, 1),
(7, 3, 'SO', 1, 1, 1, 1, 1),
(8, 3, 'SJ', 1, 1, 1, 1, 1),
(9, 3, 'Kasir', 1, 1, 1, 1, 1),
(10, 3, 'Retur', 1, 1, 1, 1, 1),
(11, 3, 'Laporan', 1, 1, 1, 1, 1),
(12, 1, 'Barang', 1, 0, 0, 0, 0),
(13, 1, 'Supplier', 1, 0, 0, 0, 0),
(14, 1, 'Customer', 1, 0, 0, 0, 0),
(15, 1, 'User', 1, 0, 0, 0, 0),
(16, 1, 'PO', 1, 0, 0, 0, 0),
(17, 1, 'BL', 1, 0, 0, 0, 0),
(18, 1, 'SO', 1, 1, 1, 0, 0),
(19, 1, 'SJ', 1, 1, 1, 0, 0),
(20, 1, 'Kasir', 1, 1, 1, 1, 0),
(21, 1, 'Retur', 1, 1, 0, 0, 0),
(22, 1, 'Laporan', 0, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tblkartustok`
--

CREATE TABLE `tblkartustok` (
  `AutoNumKS` int(11) NOT NULL,
  `idBarang` smallint(6) NOT NULL DEFAULT 0,
  `Tanggal` date NOT NULL,
  `Masuk` int(11) NOT NULL DEFAULT 0,
  `Keluar` int(11) NOT NULL DEFAULT 0,
  `JenisTransaksi` varchar(50) NOT NULL,
  `idTransaksi` varchar(50) NOT NULL,
  `idDetailTransaksi` varchar(20) NOT NULL,
  `CatatanKS` tinytext NOT NULL,
  `Fisik` smallint(6) NOT NULL DEFAULT 0,
  `PO` smallint(6) NOT NULL DEFAULT 0,
  `SO` smallint(6) NOT NULL DEFAULT 0,
  `BL` smallint(6) NOT NULL DEFAULT 0,
  `SJ` smallint(6) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblkartustok`
--

INSERT INTO `tblkartustok` (`AutoNumKS`, `idBarang`, `Tanggal`, `Masuk`, `Keluar`, `JenisTransaksi`, `idTransaksi`, `idDetailTransaksi`, `CatatanKS`, `Fisik`, `PO`, `SO`, `BL`, `SJ`) VALUES
(1, 1, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(2, 2, '2025-11-29', 80, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 80, 0, 0, 0, 0),
(3, 3, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(4, 4, '2025-11-29', 80, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 80, 0, 0, 0, 0),
(5, 5, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(6, 6, '2025-11-29', 20, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 20, 0, 0, 0, 0),
(7, 7, '2025-11-29', 30, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 30, 0, 0, 0, 0),
(8, 8, '2025-11-29', 30, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 30, 0, 0, 0, 0),
(9, 9, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(10, 10, '2025-11-29', 15, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 15, 0, 0, 0, 0),
(11, 11, '2025-11-29', 500, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 500, 0, 0, 0, 0),
(12, 12, '2025-11-29', 20, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 20, 0, 0, 0, 0),
(13, 13, '2025-11-29', 15, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 15, 0, 0, 0, 0),
(14, 14, '2025-11-29', 40, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 40, 0, 0, 0, 0),
(15, 15, '2025-11-29', 40, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 40, 0, 0, 0, 0),
(16, 16, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(17, 17, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(18, 18, '2025-11-29', 10, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 10, 0, 0, 0, 0),
(19, 19, '2025-11-29', 30, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 30, 0, 0, 0, 0),
(20, 20, '2025-11-29', 30, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 30, 0, 0, 0, 0),
(21, 21, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(22, 22, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(23, 23, '2025-11-29', 40, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 40, 0, 0, 0, 0),
(24, 24, '2025-11-29', 20, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 20, 0, 0, 0, 0),
(25, 25, '2025-11-29', 40, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 40, 0, 0, 0, 0),
(26, 26, '2025-11-29', 20, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 20, 0, 0, 0, 0),
(27, 27, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(28, 28, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(29, 29, '2025-11-29', 80, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 80, 0, 0, 0, 0),
(30, 30, '2025-11-29', 40, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 40, 0, 0, 0, 0),
(31, 31, '2025-11-29', 30, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 30, 0, 0, 0, 0),
(32, 32, '2025-11-29', 60, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 60, 0, 0, 0, 0),
(33, 33, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(34, 34, '2025-11-29', 20, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 20, 0, 0, 0, 0),
(35, 35, '2025-11-29', 15, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 15, 0, 0, 0, 0),
(36, 36, '2025-11-29', 10, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 10, 0, 0, 0, 0),
(37, 37, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(38, 38, '2025-11-29', 80, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 80, 0, 0, 0, 0),
(39, 39, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(40, 40, '2025-11-29', 80, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 80, 0, 0, 0, 0),
(41, 41, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(42, 42, '2025-11-29', 40, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 40, 0, 0, 0, 0),
(43, 43, '2025-11-29', 60, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 60, 0, 0, 0, 0),
(44, 44, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(45, 45, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(46, 46, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(47, 47, '2025-11-29', 40, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 40, 0, 0, 0, 0),
(48, 48, '2025-11-29', 80, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 80, 0, 0, 0, 0),
(49, 49, '2025-11-29', 60, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 60, 0, 0, 0, 0),
(50, 50, '2025-11-29', 30, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 30, 0, 0, 0, 0),
(51, 51, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(52, 52, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(53, 53, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(54, 54, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(55, 55, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(56, 56, '2025-11-29', 20, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 20, 0, 0, 0, 0),
(57, 57, '2025-11-29', 20, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 20, 0, 0, 0, 0),
(58, 58, '2025-11-29', 200, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 200, 0, 0, 0, 0),
(59, 59, '2025-11-29', 200, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 200, 0, 0, 0, 0),
(60, 60, '2025-11-29', 150, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 150, 0, 0, 0, 0),
(61, 61, '2025-11-29', 150, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 150, 0, 0, 0, 0),
(62, 62, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(63, 63, '2025-11-29', 80, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 80, 0, 0, 0, 0),
(64, 64, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(65, 65, '2025-11-29', 300, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 300, 0, 0, 0, 0),
(66, 66, '2025-11-29', 150, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 150, 0, 0, 0, 0),
(67, 67, '2025-11-29', 300, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 300, 0, 0, 0, 0),
(68, 68, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(69, 69, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(70, 70, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(71, 71, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(72, 72, '2025-11-29', 60, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 60, 0, 0, 0, 0),
(73, 73, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(74, 74, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(75, 75, '2025-11-29', 40, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 40, 0, 0, 0, 0),
(76, 76, '2025-11-29', 30, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 30, 0, 0, 0, 0),
(77, 77, '2025-11-29', 40, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 40, 0, 0, 0, 0),
(78, 78, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(79, 79, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(80, 80, '2025-11-29', 30, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 30, 0, 0, 0, 0),
(81, 81, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(82, 82, '2025-11-29', 60, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 60, 0, 0, 0, 0),
(83, 83, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(84, 84, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(85, 85, '2025-11-29', 20, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 20, 0, 0, 0, 0),
(86, 86, '2025-11-29', 15, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 15, 0, 0, 0, 0),
(87, 87, '2025-11-29', 40, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 40, 0, 0, 0, 0),
(88, 88, '2025-11-29', 80, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 80, 0, 0, 0, 0),
(89, 89, '2025-11-29', 40, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 40, 0, 0, 0, 0),
(90, 90, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(91, 91, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(92, 92, '2025-11-29', 20, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 20, 0, 0, 0, 0),
(93, 93, '2025-11-29', 20, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 20, 0, 0, 0, 0),
(94, 94, '2025-11-29', 15, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 15, 0, 0, 0, 0),
(95, 95, '2025-11-29', 80, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 80, 0, 0, 0, 0),
(96, 96, '2025-11-29', 60, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 60, 0, 0, 0, 0),
(97, 97, '2025-11-29', 30, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 30, 0, 0, 0, 0),
(98, 98, '2025-11-29', 25, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 25, 0, 0, 0, 0),
(99, 99, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(100, 100, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(101, 101, '2025-11-29', 40, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 40, 0, 0, 0, 0),
(102, 102, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(103, 103, '2025-11-29', 60, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 60, 0, 0, 0, 0),
(104, 104, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(105, 105, '2025-11-29', 60, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 60, 0, 0, 0, 0),
(106, 106, '2025-11-29', 30, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 30, 0, 0, 0, 0),
(107, 107, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(108, 108, '2025-11-29', 60, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 60, 0, 0, 0, 0),
(109, 109, '2025-11-29', 60, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 60, 0, 0, 0, 0),
(110, 110, '2025-11-29', 40, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 40, 0, 0, 0, 0),
(111, 111, '2025-11-29', 30, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 30, 0, 0, 0, 0),
(112, 112, '2025-11-29', 100, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 100, 0, 0, 0, 0),
(113, 113, '2025-11-29', 200, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 200, 0, 0, 0, 0),
(114, 114, '2025-11-29', 500, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 500, 0, 0, 0, 0),
(115, 115, '2025-11-29', 20, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 20, 0, 0, 0, 0),
(116, 116, '2025-11-29', 50, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 50, 0, 0, 0, 0),
(117, 117, '2025-11-29', 60, 0, 'SA', 'OPNAME-INIT', '0', 'Stok Awal Toko', 60, 0, 0, 0, 0),
(128, 110, '2025-11-29', 0, 1, 'KASIR', 'POS-0001', '1', 'Penjualan Kasir', -1, 0, 0, 0, 0),
(129, 78, '2025-11-29', 0, 1, 'KASIR', 'POS-0002', '2', 'Penjualan Kasir', -1, 0, 0, 0, 0),
(130, 114, '2025-11-29', 0, 4, 'KASIR', 'POS-0003', '3', 'Penjualan Kasir', -4, 0, 0, 0, 0),
(131, 12, '2025-11-29', 0, 5, 'KASIR', 'POS-0003', '4', 'Penjualan Kasir', -5, 0, 0, 0, 0),
(132, 34, '2025-11-29', 0, 5, 'KASIR', 'POS-0003', '5', 'Penjualan Kasir', -5, 0, 0, 0, 0),
(133, 89, '2025-11-29', 0, 3, 'KASIR', 'POS-0003', '6', 'Penjualan Kasir', -3, 0, 0, 0, 0),
(134, 7, '2025-11-29', 0, 2, 'KASIR', 'POS-0003', '7', 'Penjualan Kasir', -2, 0, 0, 0, 0),
(135, 66, '2025-11-29', 0, 1, 'KASIR', 'POS-0004', '8', 'Penjualan Kasir', -1, 0, 0, 0, 0),
(136, 80, '2025-11-29', 0, 5, 'KASIR', 'POS-0004', '9', 'Penjualan Kasir', -5, 0, 0, 0, 0),
(137, 96, '2025-11-29', 0, 2, 'KASIR', 'POS-0004', '10', 'Penjualan Kasir', -2, 0, 0, 0, 0),
(138, 31, '2025-11-29', 0, 3, 'KASIR', 'POS-0004', '11', 'Penjualan Kasir', -3, 0, 0, 0, 0),
(139, 33, '2025-11-29', 0, 2, 'KASIR', 'POS-0005', '12', 'Penjualan Kasir', -2, 0, 0, 0, 0),
(140, 27, '2025-11-29', 0, 5, 'KASIR', 'POS-0005', '13', 'Penjualan Kasir', -5, 0, 0, 0, 0),
(141, 11, '2025-11-29', 0, 5, 'KASIR', 'POS-0006', '14', 'Penjualan Kasir', -5, 0, 0, 0, 0),
(142, 46, '2025-11-29', 0, 3, 'KASIR', 'POS-0006', '15', 'Penjualan Kasir', -3, 0, 0, 0, 0),
(143, 54, '2025-11-29', 0, 3, 'KASIR', 'POS-0006', '16', 'Penjualan Kasir', -3, 0, 0, 0, 0),
(144, 71, '2025-11-29', 0, 5, 'KASIR', 'POS-0006', '17', 'Penjualan Kasir', -5, 0, 0, 0, 0),
(145, 107, '2025-11-29', 0, 1, 'KASIR', 'POS-0006', '18', 'Penjualan Kasir', -1, 0, 0, 0, 0),
(146, 77, '2025-11-29', 0, 4, 'KASIR', 'POS-0007', '19', 'Penjualan Kasir', -4, 0, 0, 0, 0),
(147, 93, '2025-11-29', 0, 1, 'KASIR', 'POS-0007', '20', 'Penjualan Kasir', -1, 0, 0, 0, 0),
(148, 81, '2025-11-29', 0, 3, 'KASIR', 'POS-0007', '21', 'Penjualan Kasir', -3, 0, 0, 0, 0),
(149, 115, '2025-11-29', 0, 3, 'KASIR', 'POS-0007', '22', 'Penjualan Kasir', -3, 0, 0, 0, 0),
(150, 53, '2025-11-29', 0, 5, 'KASIR', 'POS-0007', '23', 'Penjualan Kasir', -5, 0, 0, 0, 0),
(151, 12, '2025-11-29', 0, 3, 'KASIR', 'POS-0008', '24', 'Penjualan Kasir', -3, 0, 0, 0, 0),
(152, 101, '2025-11-29', 0, 1, 'KASIR', 'POS-0009', '25', 'Penjualan Kasir', -1, 0, 0, 0, 0),
(153, 102, '2025-11-29', 0, 5, 'KASIR', 'POS-0009', '26', 'Penjualan Kasir', -5, 0, 0, 0, 0),
(154, 52, '2025-11-29', 0, 5, 'KASIR', 'POS-0010', '27', 'Penjualan Kasir', -5, 0, 0, 0, 0),
(155, 87, '2025-11-29', 0, 5, 'KASIR', 'POS-0011', '28', 'Penjualan Kasir', -5, 0, 0, 0, 0),
(156, 97, '2025-11-29', 0, 1, 'KASIR', 'POS-0011', '29', 'Penjualan Kasir', -1, 0, 0, 0, 0),
(157, 15, '2025-11-29', 0, 4, 'KASIR', 'POS-0011', '30', 'Penjualan Kasir', -4, 0, 0, 0, 0),
(158, 38, '2025-11-29', 0, 1, 'KASIR', 'POS-0011', '31', 'Penjualan Kasir', -1, 0, 0, 0, 0),
(159, 101, '2025-11-29', 0, 2, 'KASIR', 'POS-0011', '32', 'Penjualan Kasir', -2, 0, 0, 0, 0),
(160, 10, '2025-11-29', 0, 2, 'KASIR', 'POS-0012', '33', 'Penjualan Kasir', -2, 0, 0, 0, 0),
(161, 107, '2025-11-29', 0, 5, 'KASIR', 'POS-0012', '34', 'Penjualan Kasir', -5, 0, 0, 0, 0),
(162, 7, '2025-11-29', 0, 4, 'KASIR', 'POS-0012', '35', 'Penjualan Kasir', -4, 0, 0, 0, 0),
(163, 64, '2025-11-29', 0, 2, 'KASIR', 'POS-0012', '36', 'Penjualan Kasir', -2, 0, 0, 0, 0),
(164, 88, '2025-11-29', 0, 4, 'KASIR', 'POS-0012', '37', 'Penjualan Kasir', -4, 0, 0, 0, 0),
(165, 30, '2025-11-29', 0, 5, 'KASIR', 'POS-0013', '38', 'Penjualan Kasir', -5, 0, 0, 0, 0),
(166, 114, '2025-11-29', 0, 4, 'KASIR', 'POS-0013', '39', 'Penjualan Kasir', -4, 0, 0, 0, 0),
(167, 106, '2025-11-29', 0, 5, 'KASIR', 'POS-0013', '40', 'Penjualan Kasir', -5, 0, 0, 0, 0),
(168, 82, '2025-11-29', 0, 2, 'KASIR', 'POS-0014', '41', 'Penjualan Kasir', -2, 0, 0, 0, 0),
(169, 70, '2025-11-29', 0, 5, 'KASIR', 'POS-0014', '42', 'Penjualan Kasir', -5, 0, 0, 0, 0),
(170, 85, '2025-11-29', 0, 2, 'KASIR', 'POS-0015', '43', 'Penjualan Kasir', -2, 0, 0, 0, 0),
(171, 44, '2025-11-29', 0, 1, 'KASIR', 'POS-0015', '44', 'Penjualan Kasir', -1, 0, 0, 0, 0),
(172, 78, '2025-11-29', 0, 3, 'KASIR', 'POS-0015', '45', 'Penjualan Kasir', -3, 0, 0, 0, 0),
(177, 234, '2025-11-29', 0, 0, 'SO', 'SO-2025-000001', '1', 'Sales Order : SO-2025-000001, Customer : pak rt sumirto', 0, 0, 4, 0, 0),
(178, 234, '2025-11-29', 0, 4, 'SJ', 'SJ-2025-000001', '1', 'Surat Jalan : SJ-2025-000001, Customer : pak rt sumirto', -4, 0, -4, 0, 4),
(179, 105, '2025-11-29', 0, 0, 'SO', 'SO-2025-000001', '2', 'Sales Order : SO-2025-000001, Customer : pak rt sumirto', 0, 0, 1, 0, 0),
(180, 105, '2025-11-29', 0, 1, 'SJ', 'SJ-2025-000001', '2', 'Surat Jalan : SJ-2025-000001, Customer : pak rt sumirto', -1, 0, -1, 0, 1),
(181, 1, '2025-11-29', 0, 0, 'SO', 'SO-2025-000001', '3', 'Sales Order : SO-2025-000001, Customer : pak rt sumirto', 0, 0, 4, 0, 0),
(182, 1, '2025-11-29', 0, 4, 'SJ', 'SJ-2025-000001', '3', 'Surat Jalan : SJ-2025-000001, Customer : pak rt sumirto', -4, 0, -4, 0, 4),
(184, 73, '2025-11-29', 0, 3, 'KASIR', 'POS-2025-000001', '46', 'POS: POS-2025-000001', -3, 0, 0, 0, 0),
(190, 73, '2025-12-14', 0, 1, 'KASIR', 'POS-2025-000002', '47', 'POS: POS-2025-000002', -1, 0, 0, 0, 0),
(191, 81, '2025-12-14', 0, 38, 'KASIR', 'POS-2025-000003', '48', 'POS: POS-2025-000003', -38, 0, 0, 0, 0),
(192, 78, '2025-12-14', 0, 27, 'KASIR', 'POS-2025-000004', '49', 'POS: POS-2025-000004', -27, 0, 0, 0, 0),
(193, 50, '2025-12-14', 0, 23, 'KASIR', 'POS-2025-000005', '50', 'POS: POS-2025-000005', -23, 0, 0, 0, 0),
(194, 66, '2025-12-14', 0, 15, 'KASIR', 'POS-2025-000006', '51', 'POS: POS-2025-000006', -15, 0, 0, 0, 0),
(195, 64, '2025-12-14', 0, 15, 'KASIR', 'POS-2025-000006', '52', 'POS: POS-2025-000006', -15, 0, 0, 0, 0),
(196, 73, '2025-12-14', 0, 4, 'KASIR', 'POS-2025-000006', '53', 'POS: POS-2025-000006', -4, 0, 0, 0, 0),
(197, 108, '2025-12-14', 0, 15, 'KASIR', 'POS-2025-000006', '54', 'POS: POS-2025-000006', -15, 0, 0, 0, 0),
(198, 112, '2025-12-14', 0, 5, 'KASIR', 'POS-2025-000006', '55', 'POS: POS-2025-000006', -5, 0, 0, 0, 0),
(199, 113, '2025-12-14', 0, 2, 'KASIR', 'POS-2025-000006', '56', 'POS: POS-2025-000006', -2, 0, 0, 0, 0),
(200, 115, '2025-12-14', 0, 6, 'KASIR', 'POS-2025-000006', '57', 'POS: POS-2025-000006', -6, 0, 0, 0, 0),
(201, 153, '2025-12-14', 0, 18, 'KASIR', 'POS-2025-000007', '58', 'POS: POS-2025-000007', -18, 0, 0, 0, 0),
(202, 59, '2026-01-17', 0, 1, 'KASIR', 'POS-2026-000001', '59', 'POS: POS-2026-000001', -1, 0, 0, 0, 0),
(203, 63, '2026-01-17', 0, 1, 'KASIR', 'POS-2026-000001', '60', 'POS: POS-2026-000001', -1, 0, 0, 0, 0),
(204, 83, '2026-01-17', 0, 1, 'KASIR', 'POS-2026-000002', '61', 'POS: POS-2026-000002', -1, 0, 0, 0, 0),
(205, 112, '2026-01-18', 0, 1, 'KASIR', 'POS-2026-000003', '62', 'POS: POS-2026-000003', -1, 0, 0, 0, 0),
(206, 73, '2026-01-19', 0, 1, 'KASIR', 'POS-2026-000004', '63', 'POS: POS-2026-000004', -1, 0, 0, 0, 0),
(207, 68, '2026-01-19', 0, 1, 'KASIR', 'POS-2026-000004', '64', 'POS: POS-2026-000004', -1, 0, 0, 0, 0),
(208, 58, '2026-01-19', 0, 2, 'KASIR', 'POS-2026-000004', '65', 'POS: POS-2026-000004', -2, 0, 0, 0, 0),
(209, 59, '2026-01-19', 0, 1, 'KASIR', 'POS-2026-000004', '66', 'POS: POS-2026-000004', -1, 0, 0, 0, 0),
(210, 57, '2026-01-19', 0, 2, 'KASIR', 'POS-2026-000004', '67', 'POS: POS-2026-000004', -2, 0, 0, 0, 0),
(211, 153, '2026-01-19', 0, 1, 'KASIR', 'POS-2026-000005', '68', 'POS: POS-2026-000005', -1, 0, 0, 0, 0),
(212, 152, '2026-01-19', 0, 13, 'KASIR', 'POS-2026-000005', '69', 'POS: POS-2026-000005', -13, 0, 0, 0, 0),
(213, 128, '2026-01-19', 0, 2, 'KASIR', 'POS-2026-000005', '70', 'POS: POS-2026-000005', -2, 0, 0, 0, 0),
(214, 73, '2026-01-19', 0, 1, 'KASIR', 'POS-2026-000006', '71', 'POS: POS-2026-000006', -1, 0, 0, 0, 0),
(215, 73, '2026-01-19', 0, 10, 'KASIR', 'POS-2026-000007', '72', 'POS: POS-2026-000007', -10, 0, 0, 0, 0),
(216, 73, '2026-01-19', 0, 1, 'KASIR', 'POS-2026-000008', '73', 'POS: POS-2026-000008', -1, 0, 0, 0, 0),
(217, 80, '2026-01-19', 0, 1, 'KASIR', 'POS-2026-000009', '74', 'POS: POS-2026-000009', -1, 0, 0, 0, 0),
(218, 59, '2026-01-21', 0, 4, 'KASIR', 'POS-2026-000010', '75', 'POS: POS-2026-000010', -4, 0, 0, 0, 0),
(219, 57, '2026-01-21', 0, 2, 'KASIR', 'POS-2026-000010', '76', 'POS: POS-2026-000010', -2, 0, 0, 0, 0),
(220, 62, '2026-01-21', 0, 4, 'KASIR', 'POS-2026-000010', '77', 'POS: POS-2026-000010', -4, 0, 0, 0, 0),
(221, 66, '2026-01-21', 0, 3, 'KASIR', 'POS-2026-000010', '78', 'POS: POS-2026-000010', -3, 0, 0, 0, 0),
(222, 71, '2026-01-21', 0, 3, 'KASIR', 'POS-2026-000010', '79', 'POS: POS-2026-000010', -3, 0, 0, 0, 0),
(223, 108, '2026-01-26', 0, 2, 'KASIR', 'POS-2026-000011', '80', 'POS: POS-2026-000011', -2, 0, 0, 0, 0),
(224, 115, '2026-01-26', 0, 2, 'KASIR', 'POS-2026-000011', '81', 'POS: POS-2026-000011', -2, 0, 0, 0, 0),
(225, 112, '2026-01-26', 0, 2, 'KASIR', 'POS-2026-000011', '82', 'POS: POS-2026-000011', -2, 0, 0, 0, 0),
(226, 113, '2026-01-26', 0, 4, 'KASIR', 'POS-2026-000011', '83', 'POS: POS-2026-000011', -4, 0, 0, 0, 0),
(227, 114, '2026-01-26', 0, 4, 'KASIR', 'POS-2026-000011', '84', 'POS: POS-2026-000011', -4, 0, 0, 0, 0),
(228, 109, '2026-01-26', 0, 2, 'KASIR', 'POS-2026-000011', '85', 'POS: POS-2026-000011', -2, 0, 0, 0, 0),
(229, 111, '2026-01-26', 0, 3, 'KASIR', 'POS-2026-000011', '86', 'POS: POS-2026-000011', -3, 0, 0, 0, 0),
(230, 116, '2026-01-26', 0, 3, 'KASIR', 'POS-2026-000011', '87', 'POS: POS-2026-000011', -3, 0, 0, 0, 0),
(231, 110, '2026-01-26', 0, 3, 'KASIR', 'POS-2026-000011', '88', 'POS: POS-2026-000011', -3, 0, 0, 0, 0),
(232, 108, '2026-01-26', 0, 4, 'KASIR', 'POS-2026-000012', '89', 'POS: POS-2026-000012', -4, 0, 0, 0, 0),
(233, 109, '2026-01-26', 0, 2, 'KASIR', 'POS-2026-000012', '90', 'POS: POS-2026-000012', -2, 0, 0, 0, 0),
(234, 112, '2026-01-26', 0, 7, 'KASIR', 'POS-2026-000012', '91', 'POS: POS-2026-000012', -7, 0, 0, 0, 0),
(235, 113, '2026-01-26', 0, 2, 'KASIR', 'POS-2026-000012', '92', 'POS: POS-2026-000012', -2, 0, 0, 0, 0),
(236, 114, '2026-01-26', 0, 2, 'KASIR', 'POS-2026-000012', '93', 'POS: POS-2026-000012', -2, 0, 0, 0, 0),
(237, 115, '2026-01-26', 0, 2, 'KASIR', 'POS-2026-000012', '94', 'POS: POS-2026-000012', -2, 0, 0, 0, 0),
(238, 110, '2026-01-26', 0, 2, 'KASIR', 'POS-2026-000012', '95', 'POS: POS-2026-000012', -2, 0, 0, 0, 0),
(239, 116, '2026-01-26', 0, 2, 'KASIR', 'POS-2026-000012', '96', 'POS: POS-2026-000012', -2, 0, 0, 0, 0),
(240, 1, '2026-01-26', 0, 5, 'KASIR', 'POS-2026-000013', '97', 'POS: POS-2026-000013', -5, 0, 0, 0, 0),
(241, 73, '2026-02-02', 0, 6, 'KASIR', 'POS-2026-000014', '98', 'POS: POS-2026-000014', -6, 0, 0, 0, 0),
(242, 74, '2026-02-02', 10, 0, 'PO', 'PO-2026-000001', '4', 'Purchase Order : PO-2026-000001, Supplier : China Offshore Oil (Hongkong) International Trade Co., Ltd', 0, 10, 0, 0, 0),
(243, 116, '2026-02-02', 15, 0, 'PO', 'PO-2026-000001', '5', 'Purchase Order : PO-2026-000001, Supplier : China Offshore Oil (Hongkong) International Trade Co., Ltd', 0, 15, 0, 0, 0),
(244, 73, '2026-02-02', 2, 0, 'RETUR', '0', '7', '0', 2, 0, 0, 0, 0),
(245, 59, '2026-02-02', 0, 2, 'KASIR', 'POS-2026-000015', '99', 'POS: POS-2026-000015', -2, 0, 0, 0, 0),
(246, 73, '2026-02-02', 0, 1, 'KASIR', 'POS-2026-000015', '100', 'POS: POS-2026-000015', -1, 0, 0, 0, 0),
(247, 68, '2026-02-02', 0, 1, 'KASIR', 'POS-2026-000015', '101', 'POS: POS-2026-000015', -1, 0, 0, 0, 0),
(248, 57, '2026-02-02', 0, 1, 'KASIR', 'POS-2026-000015', '102', 'POS: POS-2026-000015', -1, 0, 0, 0, 0),
(249, 63, '2026-02-02', 0, 1, 'KASIR', 'POS-2026-000015', '103', 'POS: POS-2026-000015', -1, 0, 0, 0, 0),
(250, 67, '2026-02-02', 0, 1, 'KASIR', 'POS-2026-000015', '104', 'POS: POS-2026-000015', -1, 0, 0, 0, 0),
(251, 66, '2026-02-02', 0, 1, 'KASIR', 'POS-2026-000015', '105', 'POS: POS-2026-000015', -1, 0, 0, 0, 0),
(252, 62, '2026-02-02', 0, 2, 'KASIR', 'POS-2026-000015', '106', 'POS: POS-2026-000015', -2, 0, 0, 0, 0),
(253, 71, '2026-02-02', 0, 1, 'KASIR', 'POS-2026-000015', '107', 'POS: POS-2026-000015', -1, 0, 0, 0, 0),
(254, 72, '2026-02-02', 0, 1, 'KASIR', 'POS-2026-000015', '108', 'POS: POS-2026-000015', -1, 0, 0, 0, 0),
(255, 59, '2026-02-02', 1, 0, 'RETUR', '0', '8', '0', 1, 0, 0, 0, 0),
(256, 234, '2026-02-02', 1, 0, 'RETUR', '0', '9', '0', 1, 0, 0, 0, 0),
(257, 75, '2026-02-02', 0, 4, 'KASIR', 'POS-2026-000016', '109', 'POS: POS-2026-000016', -4, 0, 0, 0, 0),
(258, 73, '2026-02-02', 0, 4, 'KASIR', 'POS-2026-000017', '110', 'POS: POS-2026-000017', -4, 0, 0, 0, 0),
(259, 68, '2026-02-02', 0, 1, 'KASIR', 'POS-2026-000017', '111', 'POS: POS-2026-000017', -1, 0, 0, 0, 0),
(260, 59, '2026-02-02', 0, 1, 'KASIR', 'POS-2026-000017', '112', 'POS: POS-2026-000017', -1, 0, 0, 0, 0),
(261, 57, '2026-02-02', 0, 3, 'KASIR', 'POS-2026-000017', '113', 'POS: POS-2026-000017', -3, 0, 0, 0, 0),
(262, 73, '2026-02-03', 0, 1, 'KASIR', 'POS-2026-000018', '114', 'POS: POS-2026-000018', -1, 0, 0, 0, 0),
(263, 68, '2026-02-03', 0, 1, 'KASIR', 'POS-2026-000018', '115', 'POS: POS-2026-000018', -1, 0, 0, 0, 0),
(264, 59, '2026-02-03', 0, 1, 'KASIR', 'POS-2026-000018', '116', 'POS: POS-2026-000018', -1, 0, 0, 0, 0),
(265, 57, '2026-02-03', 0, 3, 'KASIR', 'POS-2026-000018', '117', 'POS: POS-2026-000018', -3, 0, 0, 0, 0),
(266, 57, '2026-02-03', 2, 0, 'RETUR', '0', '11', '0', 2, 0, 0, 0, 0),
(267, 73, '2026-02-03', 0, 3, 'KASIR', 'POS-2026-000019', '118', 'POS: POS-2026-000019', -3, 0, 0, 0, 0),
(268, 66, '2026-02-03', 0, 3, 'KASIR', 'POS-2026-000019', '119', 'POS: POS-2026-000019', -3, 0, 0, 0, 0),
(269, 62, '2026-02-03', 0, 1, 'KASIR', 'POS-2026-000019', '120', 'POS: POS-2026-000019', -1, 0, 0, 0, 0),
(270, 57, '2026-02-03', 0, 3, 'KASIR', 'POS-2026-000019', '121', 'POS: POS-2026-000019', -3, 0, 0, 0, 0),
(271, 73, '2026-02-03', 2, 0, 'RETUR', 'RJ-202602-000006', '', 'Retur Jual (Approved): RJ-202602-000006', 2, 0, 0, 0, 0),
(272, 66, '2026-02-03', 2, 0, 'RETUR', 'RJ-202602-000006', '', 'Retur Jual (Approved): RJ-202602-000006', 2, 0, 0, 0, 0),
(273, 57, '2026-02-03', 1, 0, 'RETUR', 'RJ-202602-000006', '', 'Retur Jual (Approved): RJ-202602-000006', 1, 0, 0, 0, 0),
(274, 59, '2026-02-03', 0, 9, 'KASIR', 'POS-2026-000020', '122', 'POS: POS-2026-000020', -9, 0, 0, 0, 0),
(275, 58, '2026-02-03', 0, 11, 'KASIR', 'POS-2026-000021', '123', 'POS: POS-2026-000021', -11, 0, 0, 0, 0),
(276, 112, '2026-02-03', 0, 10, 'KASIR', 'POS-2026-000022', '124', 'POS: POS-2026-000022', -10, 0, 0, 0, 0),
(277, 112, '2026-02-03', 2, 0, 'RETUR', 'RJ-202602-000008', '', 'Retur Jual (Approved): RJ-202602-000008', 2, 0, 0, 0, 0),
(278, 74, '2026-02-03', 10, 0, 'BL', 'BL-2026-000001', '1', 'Penerimaan Barang PO : BL-2026-000001, Supplier : China Offshore Oil (Hongkong) International Trade Co., Ltd', 10, -10, 0, 10, 0),
(279, 116, '2026-02-03', 15, 0, 'BL', 'BL-2026-000001', '2', 'Penerimaan Barang PO : BL-2026-000001, Supplier : China Offshore Oil (Hongkong) International Trade Co., Ltd', 15, -15, 0, 15, 0),
(281, 74, '2026-02-03', 0, 0, 'SO', 'SO-2026-000001', '8', 'Sales Order : SO-2026-000001, Customer : Maju UD', 0, 0, 20, 0, 0),
(282, 74, '2026-02-03', 0, 20, 'SJ', 'SJ-2026-000001', '4', 'Surat Jalan : SJ-2026-000001, Customer : Maju UD', -20, 0, -20, 0, 20),
(286, 112, '2025-12-09', 0, 0, 'SO', 'SO-2025-000003', '5', 'Sales Order : SO-2025-000003, Customer : Maju UD', 0, 0, 10, 0, 0),
(287, 112, '2026-02-03', 0, 10, 'SJ', 'SJ-2026-000002', '5', 'Surat Jalan : SJ-2026-000002, Customer : Maju UD', -10, 0, -10, 0, 10),
(288, 151, '2025-12-09', 0, 0, 'SO', 'SO-2025-000003', '6', 'Sales Order : SO-2025-000003, Customer : Maju UD', 0, 0, 10, 0, 0),
(289, 151, '2026-02-03', 0, 10, 'SJ', 'SJ-2026-000002', '6', 'Surat Jalan : SJ-2026-000002, Customer : Maju UD', -10, 0, -10, 0, 10),
(290, 173, '2025-12-09', 0, 0, 'SO', 'SO-2025-000003', '7', 'Sales Order : SO-2025-000003, Customer : Maju UD', 0, 0, 10, 0, 0),
(291, 173, '2026-02-03', 0, 10, 'SJ', 'SJ-2026-000002', '7', 'Surat Jalan : SJ-2026-000002, Customer : Maju UD', -10, 0, -10, 0, 10);

--
-- Triggers `tblkartustok`
--
DELIMITER $$
CREATE TRIGGER `trKSAfterInsert` AFTER INSERT ON `tblkartustok` FOR EACH ROW BEGIN
    -- Hanya update stok Fisik jika ada perubahan Fisik
    IF NEW.Fisik <> 0 THEN
        UPDATE tblbarang SET Jumlah = Jumlah + NEW.Fisik WHERE KodeBarang = NEW.idBarang;
    END IF;

    -- Hanya update stok PO jika ada perubahan PO
    IF NEW.PO <> 0 THEN
        UPDATE tblbarang SET PO = PO + NEW.PO WHERE KodeBarang = NEW.idBarang;
    END IF;

    -- Hanya update stok SO jika ada perubahan SO
    IF NEW.SO <> 0 THEN
        UPDATE tblbarang SET SO = SO + NEW.SO WHERE KodeBarang = NEW.idBarang;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trKSBeforeDelete` BEFORE DELETE ON `tblkartustok` FOR EACH ROW BEGIN
    -- Balikkan semua nilai saat data dihapus
    IF OLD.Fisik <> 0 THEN
        UPDATE tblbarang SET Jumlah = Jumlah - OLD.Fisik WHERE KodeBarang = OLD.idBarang;
    END IF;

    IF OLD.PO <> 0 THEN
        UPDATE tblbarang SET PO = PO - OLD.PO WHERE KodeBarang = OLD.idBarang;
    END IF;

    IF OLD.SO <> 0 THEN
        UPDATE tblbarang SET SO = SO - OLD.SO WHERE KodeBarang = OLD.idBarang;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tblkategoribarang`
--

CREATE TABLE `tblkategoribarang` (
  `KodeKategori` tinyint(4) NOT NULL,
  `NamaKategori` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblkategoribarang`
--

INSERT INTO `tblkategoribarang` (`KodeKategori`, `NamaKategori`) VALUES
(1, 'Material Dasar'),
(2, 'Cat & Finishing'),
(3, 'Pipa & Plumbing'),
(4, 'Besi & Baja Ringan'),
(5, 'Alat Listrik'),
(6, 'Keramik & Granit'),
(7, 'Atap & Plafon'),
(8, 'Perkakas & Tools'),
(9, 'Kimia & Pembersih'),
(10, 'Perlengkapan Proyek');

-- --------------------------------------------------------

--
-- Table structure for table `tblkategoriuser`
--

CREATE TABLE `tblkategoriuser` (
  `KodeKategori` tinyint(4) NOT NULL,
  `NamaKategoriUser` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblkategoriuser`
--

INSERT INTO `tblkategoriuser` (`KodeKategori`, `NamaKategoriUser`) VALUES
(1, 'kasir'),
(3, 'Administrator');

-- --------------------------------------------------------

--
-- Table structure for table `tbllogin`
--

CREATE TABLE `tbllogin` (
  `KodeUser` smallint(6) NOT NULL,
  `UserLogin` varchar(100) NOT NULL DEFAULT '',
  `PassLogin` varchar(255) NOT NULL DEFAULT '',
  `KategoriUser` varchar(100) NOT NULL DEFAULT '',
  `KodeKategori` tinyint(4) DEFAULT 2
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbllogin`
--

INSERT INTO `tbllogin` (`KodeUser`, `UserLogin`, `PassLogin`, `KategoriUser`, `KodeKategori`) VALUES
(1, 'Administrator', '$2y$10$k2FoVLav5vac6YTrOAkr9.gfX391g8SB2Vas3AJFYimYhQUUrnZ42', 'SuperAdmin', 3),
(2, 'Kasir', '$2y$10$mEby/McFI547WB044YaSBOsM3cBTp.2vgZoF1LfILkNlSvDOgD736', 'Normal', 1),
(5, 'kasir 2', '$2y$10$6T6GlRZofJw3IL77NvU2/e.4cIL2mb8oCKjJhow474oEUuaj0lHry', 'kasir', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tblpenerimaanbarang`
--

CREATE TABLE `tblpenerimaanbarang` (
  `AutoNum` int(11) NOT NULL,
  `KodePenerimaan` varchar(20) NOT NULL,
  `Tanggal` date NOT NULL,
  `idSupplier` int(11) NOT NULL DEFAULT 0,
  `TotalBarang` decimal(8,2) NOT NULL DEFAULT 0.00,
  `SubTotal` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `PPN` decimal(6,2) NOT NULL DEFAULT 0.00,
  `PPNRp` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `Diskon` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `GrandTotal` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `idPO` varchar(20) NOT NULL DEFAULT '0',
  `Catatan` tinytext NOT NULL,
  `Aktif` char(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblpenerimaanbarang`
--

INSERT INTO `tblpenerimaanbarang` (`AutoNum`, `KodePenerimaan`, `Tanggal`, `idSupplier`, `TotalBarang`, `SubTotal`, `PPN`, `PPNRp`, `Diskon`, `GrandTotal`, `idPO`, `Catatan`, `Aktif`) VALUES
(1, 'BL-2026-000001', '2026-02-03', 32, 25.00, 600000.0000, 11.00, 66000.0000, 0.0000, 666000.0000, 'PO-2026-000001', '', '1');

-- --------------------------------------------------------

--
-- Table structure for table `tblpenerimaanbarangdetailbarang`
--

CREATE TABLE `tblpenerimaanbarangdetailbarang` (
  `AutoNum` int(11) NOT NULL,
  `idPenerimaan` varchar(20) NOT NULL DEFAULT '',
  `idBarang` smallint(6) NOT NULL DEFAULT 0,
  `Jumlah` smallint(6) NOT NULL DEFAULT 0,
  `HargaBeli` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `TotalHarga` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `idDetailPO` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblpenerimaanbarangdetailbarang`
--

INSERT INTO `tblpenerimaanbarangdetailbarang` (`AutoNum`, `idPenerimaan`, `idBarang`, `Jumlah`, `HargaBeli`, `TotalHarga`, `idDetailPO`) VALUES
(1, 'BL-2026-000001', 74, 10, 48000.0000, 480000.0000, 4),
(2, 'BL-2026-000001', 116, 15, 8000.0000, 120000.0000, 5);

--
-- Triggers `tblpenerimaanbarangdetailbarang`
--
DELIMITER $$
CREATE TRIGGER `trBLDetailAfterDelete` AFTER DELETE ON `tblpenerimaanbarangdetailbarang` FOR EACH ROW BEGIN
    -- Kurangi Stok Barang (Karena barangnya batal masuk)
    UPDATE tblbarang 
    SET Jumlah = Jumlah - OLD.Jumlah
    WHERE KodeBarang = OLD.idBarang;
    
    -- Kembalikan Status PO (Sisa PO bertambah lagi)
    UPDATE tblpodetailbarang 
    SET JumlahDiterima = JumlahDiterima - OLD.Jumlah 
    WHERE AutoNumDetPO = OLD.idDetailPO;

    -- Catat di Kartu Stok (Keluar)
    INSERT INTO tblkartustok (idBarang, Tanggal, Masuk, Keluar, Saldo, JenisTransaksi, NoBukti, idDetailTransaksi, Keterangan)
    SELECT 
        OLD.idBarang, 
        NOW(), 
        0, 
        OLD.Jumlah, -- Keluar
        (b.Jumlah), 
        'BATAL-BL', 
        OLD.idPenerimaan, 
        OLD.AutoNum, 
        'Pembatalan Penerimaan Barang'
    FROM tblbarang b WHERE b.KodeBarang = OLD.idBarang;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trBLDetailAfterInsert` AFTER INSERT ON `tblpenerimaanbarangdetailbarang` FOR EACH ROW BEGIN
    DECLARE Tanggal DATE;
    DECLARE NamaSupplier VARCHAR(100);
    DECLARE Catatan TEXT;
    DECLARE Keterangan TEXT;

    SELECT tblpenerimaanbarang.Tanggal, tblsupplier.NamaSupplier, tblpenerimaanbarang.Catatan
    INTO Tanggal, NamaSupplier, Catatan
    FROM tblpenerimaanbarang 
    INNER JOIN tblsupplier ON idSupplier=KodeSupplier 
    WHERE KodePenerimaan=NEW.idPenerimaan;

    SET Keterangan = IF(
        TRIM(Catatan) = '',
        CONCAT('Penerimaan Barang PO : ', NEW.idPenerimaan, ', Supplier : ', NamaSupplier),
        CONCAT('Penerimaan Barang PO : ', NEW.idPenerimaan, ', Supplier : ', NamaSupplier, ', Keterangan : ', Catatan)
    );

    UPDATE tblpodetailbarang 
    SET tblpodetailbarang.JumlahDiterima = tblpodetailbarang.JumlahDiterima + NEW.Jumlah 
    WHERE tblpodetailbarang.AutoNumDetPO = NEW.idDetailPO;

    -- MEMANGGIL PROSEDUR TERPUSAT (bukan INSERT manual lagi)
    CALL spInsertKS(
        NEW.idBarang, 
        Tanggal, 
        NEW.Jumlah, 
        'BL', 
        NEW.idPenerimaan, 
        NEW.AutoNum, 
        Keterangan
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tblpenjualankasir`
--

CREATE TABLE `tblpenjualankasir` (
  `AutoNum` int(11) NOT NULL,
  `NoNota` varchar(20) NOT NULL,
  `Tanggal` date NOT NULL,
  `Jam` time NOT NULL,
  `SubTotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `Diskon` decimal(18,2) NOT NULL DEFAULT 0.00,
  `GrandTotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `Bayar` decimal(18,2) NOT NULL DEFAULT 0.00,
  `Kembali` decimal(18,2) NOT NULL DEFAULT 0.00,
  `KodeUser` smallint(6) NOT NULL,
  `Catatan` tinytext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblpenjualankasir`
--

INSERT INTO `tblpenjualankasir` (`AutoNum`, `NoNota`, `Tanggal`, `Jam`, `SubTotal`, `Diskon`, `GrandTotal`, `Bayar`, `Kembali`, `KodeUser`, `Catatan`) VALUES
(1, 'POS-0001', '2025-11-29', '18:17:00', 35000.00, 0.00, 35000.00, 40000.00, 5000.00, 1, NULL),
(2, 'POS-0002', '2025-11-29', '15:39:00', 165000.00, 0.00, 165000.00, 170000.00, 5000.00, 1, NULL),
(3, 'POS-0003', '2025-11-29', '17:46:00', 2122000.00, 0.00, 2122000.00, 2125000.00, 3000.00, 1, NULL),
(4, 'POS-0004', '2025-11-29', '18:43:00', 1028500.00, 0.00, 1028500.00, 1030000.00, 1500.00, 1, NULL),
(5, 'POS-0005', '2025-11-29', '12:15:00', 140000.00, 0.00, 140000.00, 145000.00, 5000.00, 1, NULL),
(6, 'POS-0006', '2025-11-29', '20:34:00', 230000.00, 0.00, 230000.00, 235000.00, 5000.00, 1, NULL),
(7, 'POS-0007', '2025-11-29', '12:54:00', 750000.00, 0.00, 750000.00, 755000.00, 5000.00, 1, NULL),
(8, 'POS-0008', '2025-11-29', '14:02:00', 630000.00, 0.00, 630000.00, 635000.00, 5000.00, 1, NULL),
(9, 'POS-0009', '2025-11-29', '14:09:00', 135000.00, 0.00, 135000.00, 140000.00, 5000.00, 1, NULL),
(10, 'POS-0010', '2025-11-29', '11:44:00', 210000.00, 0.00, 210000.00, 215000.00, 5000.00, 1, NULL),
(11, 'POS-0011', '2025-11-29', '13:22:00', 725000.00, 0.00, 725000.00, 730000.00, 5000.00, 1, NULL),
(12, 'POS-0012', '2025-11-29', '11:26:00', 1254000.00, 0.00, 1254000.00, 1255000.00, 1000.00, 1, NULL),
(13, 'POS-0013', '2025-11-29', '18:14:00', 662000.00, 0.00, 662000.00, 665000.00, 3000.00, 1, NULL),
(14, 'POS-0014', '2025-11-29', '17:50:00', 261000.00, 0.00, 261000.00, 265000.00, 4000.00, 1, NULL),
(15, 'POS-0015', '2025-11-29', '12:14:00', 570000.00, 0.00, 570000.00, 575000.00, 5000.00, 1, NULL),
(16, 'POS-2025-000001', '2025-11-29', '11:24:41', 21000.00, 0.00, 21000.00, 21000.00, 0.00, 1, NULL),
(17, 'POS-2025-000002', '2025-12-14', '22:51:03', 7000.00, 0.00, 7000.00, 8000.00, 1000.00, 1, NULL),
(18, 'POS-2025-000003', '2025-12-14', '22:52:26', 2850000.00, 0.00, 2850000.00, 5000000.00, 2150000.00, 1, NULL),
(19, 'POS-2025-000004', '2025-12-14', '22:53:02', 4455000.00, 0.00, 4455000.00, 5000000.00, 545000.00, 1, NULL),
(20, 'POS-2025-000005', '2025-12-14', '22:53:35', 2990000.00, 0.00, 2990000.00, 3000000.00, 10000.00, 1, NULL),
(21, 'POS-2025-000006', '2025-12-14', '22:54:31', 886500.00, 0.00, 886500.00, 1000000.00, 113500.00, 1, NULL),
(22, 'POS-2025-000007', '2025-12-14', '22:55:19', 9504000.00, 0.00, 9504000.00, 10000000.00, 496000.00, 1, NULL),
(23, 'POS-2026-000001', '2026-01-17', '03:08:06', 26000.00, 0.00, 26000.00, 50000.00, 24000.00, 1, NULL),
(24, 'POS-2026-000002', '2026-01-17', '03:09:11', 55000.00, 0.00, 55000.00, 55000.00, 0.00, 1, NULL),
(25, 'POS-2026-000003', '2026-01-18', '19:05:22', 5000.00, 0.00, 5000.00, 5000.00, 0.00, 1, NULL),
(26, 'POS-2026-000004', '2026-01-19', '02:41:02', 1090000.00, 0.00, 1090000.00, 1100000.00, 10000.00, 1, NULL),
(27, 'POS-2026-000005', '2026-01-19', '08:36:05', 2398000.00, 0.00, 2398000.00, 2400000.00, 2000.00, 1, NULL),
(28, 'POS-2026-000006', '2026-01-19', '08:52:03', 7000.00, 0.00, 7000.00, 7500.00, 500.00, 1, NULL),
(29, 'POS-2026-000007', '2026-01-19', '08:52:18', 70000.00, 0.00, 70000.00, 100000.00, 30000.00, 1, NULL),
(30, 'POS-2026-000008', '2026-01-19', '10:06:13', 7000.00, 0.00, 7000.00, 10000.00, 3000.00, 1, NULL),
(31, 'POS-2026-000009', '2026-01-19', '10:06:30', 115000.00, 0.00, 115000.00, 120000.00, 5000.00, 1, NULL),
(32, 'POS-2026-000010', '2026-01-21', '07:37:28', 1209500.00, 0.00, 1209500.00, 1210000.00, 500.00, 1, NULL),
(33, 'POS-2026-000011', '2026-01-26', '03:10:52', 435000.00, 0.00, 435000.00, 500000.00, 65000.00, 1, NULL),
(34, 'POS-2026-000012', '2026-01-26', '03:11:21', 341000.00, 0.00, 341000.00, 350000.00, 9000.00, 1, NULL),
(35, 'POS-2026-000013', '2026-01-26', '03:21:14', 265000.00, 0.00, 265000.00, 280000.00, 15000.00, 1, NULL),
(36, 'POS-2026-000014', '2026-02-02', '17:21:12', 42000.00, 0.00, 42000.00, 50000.00, 8000.00, 1, NULL),
(37, 'POS-2026-000015', '2026-02-02', '19:43:33', 648000.00, 0.00, 648000.00, 700000.00, 52000.00, 1, NULL),
(38, 'POS-2026-000016', '2026-02-02', '19:48:05', 260000.00, 0.00, 260000.00, 300000.00, 40000.00, 5, NULL),
(39, 'POS-2026-000017', '2026-02-02', '23:21:33', 1631000.00, 0.00, 1631000.00, 1700000.00, 69000.00, 1, NULL),
(40, 'POS-2026-000018', '2026-02-03', '00:18:42', 1610000.00, 0.00, 1610000.00, 1700000.00, 90000.00, 1, NULL),
(41, 'POS-2026-000019', '2026-02-03', '00:21:43', 1647000.00, 0.00, 1647000.00, 1700000.00, 53000.00, 5, NULL),
(42, 'POS-2026-000020', '2026-02-03', '00:23:27', 63000.00, 0.00, 63000.00, 70000.00, 7000.00, 1, NULL),
(43, 'POS-2026-000021', '2026-02-03', '00:24:22', 55000.00, 0.00, 55000.00, 55000.00, 0.00, 1, NULL),
(44, 'POS-2026-000022', '2026-02-03', '00:37:58', 50000.00, 0.00, 50000.00, 50000.00, 0.00, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tblpenjualankasirdetail`
--

CREATE TABLE `tblpenjualankasirdetail` (
  `AutoNum` int(11) NOT NULL,
  `NoNota` varchar(20) NOT NULL,
  `idBarang` smallint(6) NOT NULL,
  `Jumlah` smallint(6) NOT NULL,
  `HargaSatuan` decimal(18,2) NOT NULL,
  `TotalHarga` decimal(18,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblpenjualankasirdetail`
--

INSERT INTO `tblpenjualankasirdetail` (`AutoNum`, `NoNota`, `idBarang`, `Jumlah`, `HargaSatuan`, `TotalHarga`) VALUES
(1, 'POS-0001', 110, 1, 35000.00, 35000.00),
(2, 'POS-0002', 78, 1, 165000.00, 165000.00),
(3, 'POS-0003', 114, 4, 3000.00, 12000.00),
(4, 'POS-0003', 12, 5, 210000.00, 1050000.00),
(5, 'POS-0003', 34, 5, 140000.00, 700000.00),
(6, 'POS-0003', 89, 3, 30000.00, 90000.00),
(7, 'POS-0003', 7, 2, 135000.00, 270000.00),
(8, 'POS-0004', 66, 1, 7500.00, 7500.00),
(9, 'POS-0004', 80, 5, 115000.00, 575000.00),
(10, 'POS-0004', 96, 2, 28000.00, 56000.00),
(11, 'POS-0004', 31, 3, 130000.00, 390000.00),
(12, 'POS-0005', 33, 2, 50000.00, 100000.00),
(13, 'POS-0005', 27, 5, 8000.00, 40000.00),
(14, 'POS-0006', 11, 5, 1200.00, 6000.00),
(15, 'POS-0006', 46, 3, 15000.00, 45000.00),
(16, 'POS-0006', 54, 3, 32000.00, 96000.00),
(17, 'POS-0006', 71, 5, 15000.00, 75000.00),
(18, 'POS-0006', 107, 1, 8000.00, 8000.00),
(19, 'POS-0007', 77, 4, 60000.00, 240000.00),
(20, 'POS-0007', 93, 1, 60000.00, 60000.00),
(21, 'POS-0007', 81, 3, 75000.00, 225000.00),
(22, 'POS-0007', 115, 3, 35000.00, 105000.00),
(23, 'POS-0007', 53, 5, 24000.00, 120000.00),
(24, 'POS-0008', 12, 3, 210000.00, 630000.00),
(25, 'POS-0009', 101, 1, 20000.00, 20000.00),
(26, 'POS-0009', 102, 5, 23000.00, 115000.00),
(27, 'POS-0010', 52, 5, 42000.00, 210000.00),
(28, 'POS-0011', 87, 5, 25000.00, 125000.00),
(29, 'POS-0011', 97, 1, 35000.00, 35000.00),
(30, 'POS-0011', 15, 4, 130000.00, 520000.00),
(31, 'POS-0011', 38, 1, 5000.00, 5000.00),
(32, 'POS-0011', 101, 2, 20000.00, 40000.00),
(33, 'POS-0012', 10, 2, 300000.00, 600000.00),
(34, 'POS-0012', 107, 5, 8000.00, 40000.00),
(35, 'POS-0012', 7, 4, 135000.00, 540000.00),
(36, 'POS-0012', 64, 2, 13000.00, 26000.00),
(37, 'POS-0012', 88, 4, 12000.00, 48000.00),
(38, 'POS-0013', 30, 5, 100000.00, 500000.00),
(39, 'POS-0013', 114, 4, 3000.00, 12000.00),
(40, 'POS-0013', 106, 5, 30000.00, 150000.00),
(41, 'POS-0014', 82, 2, 68000.00, 136000.00),
(42, 'POS-0014', 70, 5, 25000.00, 125000.00),
(43, 'POS-0015', 85, 2, 35000.00, 70000.00),
(44, 'POS-0015', 44, 1, 5000.00, 5000.00),
(45, 'POS-0015', 78, 3, 165000.00, 495000.00),
(46, 'POS-2025-000001', 73, 3, 7000.00, 21000.00),
(47, 'POS-2025-000002', 73, 1, 7000.00, 7000.00),
(48, 'POS-2025-000003', 81, 38, 75000.00, 2850000.00),
(49, 'POS-2025-000004', 78, 27, 165000.00, 4455000.00),
(50, 'POS-2025-000005', 50, 23, 130000.00, 2990000.00),
(51, 'POS-2025-000006', 66, 15, 7500.00, 112500.00),
(52, 'POS-2025-000006', 64, 15, 13000.00, 195000.00),
(53, 'POS-2025-000006', 73, 4, 7000.00, 28000.00),
(54, 'POS-2025-000006', 108, 15, 20000.00, 300000.00),
(55, 'POS-2025-000006', 112, 5, 5000.00, 25000.00),
(56, 'POS-2025-000006', 113, 2, 8000.00, 16000.00),
(57, 'POS-2025-000006', 115, 6, 35000.00, 210000.00),
(58, 'POS-2025-000007', 153, 18, 528000.00, 9504000.00),
(59, 'POS-2026-000001', 59, 1, 7000.00, 7000.00),
(60, 'POS-2026-000001', 63, 1, 19000.00, 19000.00),
(61, 'POS-2026-000002', 83, 1, 55000.00, 55000.00),
(62, 'POS-2026-000003', 112, 1, 5000.00, 5000.00),
(63, 'POS-2026-000004', 73, 1, 7000.00, 7000.00),
(64, 'POS-2026-000004', 68, 1, 6000.00, 6000.00),
(65, 'POS-2026-000004', 58, 2, 5000.00, 10000.00),
(66, 'POS-2026-000004', 59, 1, 7000.00, 7000.00),
(67, 'POS-2026-000004', 57, 2, 530000.00, 1060000.00),
(68, 'POS-2026-000005', 153, 1, 528000.00, 528000.00),
(69, 'POS-2026-000005', 152, 13, 121000.00, 1573000.00),
(70, 'POS-2026-000005', 128, 2, 148500.00, 297000.00),
(71, 'POS-2026-000006', 73, 1, 7000.00, 7000.00),
(72, 'POS-2026-000007', 73, 10, 7000.00, 70000.00),
(73, 'POS-2026-000008', 73, 1, 7000.00, 7000.00),
(74, 'POS-2026-000009', 80, 1, 115000.00, 115000.00),
(75, 'POS-2026-000010', 59, 4, 7000.00, 28000.00),
(76, 'POS-2026-000010', 57, 2, 530000.00, 1060000.00),
(77, 'POS-2026-000010', 62, 4, 13500.00, 54000.00),
(78, 'POS-2026-000010', 66, 3, 7500.00, 22500.00),
(79, 'POS-2026-000010', 71, 3, 15000.00, 45000.00),
(80, 'POS-2026-000011', 108, 2, 20000.00, 40000.00),
(81, 'POS-2026-000011', 115, 2, 35000.00, 70000.00),
(82, 'POS-2026-000011', 112, 2, 5000.00, 10000.00),
(83, 'POS-2026-000011', 113, 4, 8000.00, 32000.00),
(84, 'POS-2026-000011', 114, 4, 3000.00, 12000.00),
(85, 'POS-2026-000011', 109, 2, 20000.00, 40000.00),
(86, 'POS-2026-000011', 111, 3, 30000.00, 90000.00),
(87, 'POS-2026-000011', 116, 3, 12000.00, 36000.00),
(88, 'POS-2026-000011', 110, 3, 35000.00, 105000.00),
(89, 'POS-2026-000012', 108, 4, 20000.00, 80000.00),
(90, 'POS-2026-000012', 109, 2, 20000.00, 40000.00),
(91, 'POS-2026-000012', 112, 7, 5000.00, 35000.00),
(92, 'POS-2026-000012', 113, 2, 8000.00, 16000.00),
(93, 'POS-2026-000012', 114, 2, 3000.00, 6000.00),
(94, 'POS-2026-000012', 115, 2, 35000.00, 70000.00),
(95, 'POS-2026-000012', 110, 2, 35000.00, 70000.00),
(96, 'POS-2026-000012', 116, 2, 12000.00, 24000.00),
(97, 'POS-2026-000013', 1, 5, 53000.00, 265000.00),
(98, 'POS-2026-000014', 73, 6, 7000.00, 42000.00),
(99, 'POS-2026-000015', 59, 2, 7000.00, 14000.00),
(100, 'POS-2026-000015', 73, 1, 7000.00, 7000.00),
(101, 'POS-2026-000015', 68, 1, 6000.00, 6000.00),
(102, 'POS-2026-000015', 57, 1, 530000.00, 530000.00),
(103, 'POS-2026-000015', 63, 1, 19000.00, 19000.00),
(104, 'POS-2026-000015', 67, 1, 6500.00, 6500.00),
(105, 'POS-2026-000015', 66, 1, 7500.00, 7500.00),
(106, 'POS-2026-000015', 62, 2, 13500.00, 27000.00),
(107, 'POS-2026-000015', 71, 1, 15000.00, 15000.00),
(108, 'POS-2026-000015', 72, 1, 16000.00, 16000.00),
(109, 'POS-2026-000016', 75, 4, 65000.00, 260000.00),
(110, 'POS-2026-000017', 73, 4, 7000.00, 28000.00),
(111, 'POS-2026-000017', 68, 1, 6000.00, 6000.00),
(112, 'POS-2026-000017', 59, 1, 7000.00, 7000.00),
(113, 'POS-2026-000017', 57, 3, 530000.00, 1590000.00),
(114, 'POS-2026-000018', 73, 1, 7000.00, 7000.00),
(115, 'POS-2026-000018', 68, 1, 6000.00, 6000.00),
(116, 'POS-2026-000018', 59, 1, 7000.00, 7000.00),
(117, 'POS-2026-000018', 57, 3, 530000.00, 1590000.00),
(118, 'POS-2026-000019', 73, 3, 7000.00, 21000.00),
(119, 'POS-2026-000019', 66, 3, 7500.00, 22500.00),
(120, 'POS-2026-000019', 62, 1, 13500.00, 13500.00),
(121, 'POS-2026-000019', 57, 3, 530000.00, 1590000.00),
(122, 'POS-2026-000020', 59, 9, 7000.00, 63000.00),
(123, 'POS-2026-000021', 58, 11, 5000.00, 55000.00),
(124, 'POS-2026-000022', 112, 10, 5000.00, 50000.00);

--
-- Triggers `tblpenjualankasirdetail`
--
DELIMITER $$
CREATE TRIGGER `trKasirDetailAfterDelete` AFTER DELETE ON `tblpenjualankasirdetail` FOR EACH ROW BEGIN
    -- Kembalikan Stok Barang
    UPDATE tblbarang 
    SET Jumlah = Jumlah + OLD.Jumlah
    WHERE KodeBarang = OLD.idBarang;

    -- Catat di Kartu Stok (Masuk kembali)
    -- Note: Kita gunakan 'BATAL' agar terlacak di laporan
    INSERT INTO tblkartustok (idBarang, Tanggal, Masuk, Keluar, Saldo, JenisTransaksi, NoBukti, idDetailTransaksi, Keterangan)
    SELECT 
        OLD.idBarang, 
        NOW(), 
        OLD.Jumlah, -- Masuk
        0, 
        (b.Jumlah), -- Saldo akhir
        'BATAL-POS', 
        OLD.NoNota, 
        OLD.AutoNum, 
        'Pembatalan Transaksi Kasir'
    FROM tblbarang b WHERE b.KodeBarang = OLD.idBarang;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tblpo`
--

CREATE TABLE `tblpo` (
  `AutoNum` int(11) NOT NULL,
  `KodePO` varchar(30) NOT NULL,
  `Tanggal` date NOT NULL,
  `idSupplier` smallint(6) NOT NULL,
  `TotalBarang` smallint(6) NOT NULL,
  `SubTotal` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `PPN` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `PPNRp` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `Diskon` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `GrandTotal` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `Catatan` tinytext NOT NULL,
  `Aktif` char(50) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblpo`
--

INSERT INTO `tblpo` (`AutoNum`, `KodePO`, `Tanggal`, `idSupplier`, `TotalBarang`, `SubTotal`, `PPN`, `PPNRp`, `Diskon`, `GrandTotal`, `Catatan`, `Aktif`) VALUES
(1, 'PO-2025-000001', '2025-11-29', 3, 10, 25000.000000, 11.000000, 2750.000000, 0.000000, 27750.000000, '', '0'),
(2, 'PO-2025-000002', '2025-12-01', 19, 30, 4850000.000000, 11.000000, 533500.000000, 0.000000, 5383500.000000, '', '0'),
(3, 'PO-2026-000001', '2026-02-02', 32, 25, 600000.000000, 11.000000, 66000.000000, 0.000000, 666000.000000, '', '1');

--
-- Triggers `tblpo`
--
DELIMITER $$
CREATE TRIGGER `trPOAfterUpdate` AFTER UPDATE ON `tblpo` FOR EACH ROW BEGIN
	IF NEW.Aktif='0' THEN
		DELETE FROM tblkartustok WHERE JenisTransaksi='PO' AND idTransaksi=OLD.KodePO;
	END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tblpodetailbarang`
--

CREATE TABLE `tblpodetailbarang` (
  `AutoNumDetPO` int(11) NOT NULL,
  `idBarang` smallint(6) NOT NULL,
  `idPO` varchar(30) NOT NULL DEFAULT '',
  `JumlahBeli` smallint(6) NOT NULL,
  `HargaBeliDetPO` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `TotalHarga` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `JumlahDiterima` smallint(6) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblpodetailbarang`
--

INSERT INTO `tblpodetailbarang` (`AutoNumDetPO`, `idBarang`, `idPO`, `JumlahBeli`, `HargaBeliDetPO`, `TotalHarga`, `JumlahDiterima`) VALUES
(1, 112, 'PO-2025-000001', 10, 2500.000000, 25000.000000, 0),
(2, 112, 'PO-2025-000002', 20, 2500.000000, 50000.000000, 0),
(3, 153, 'PO-2025-000002', 10, 480000.000000, 4800000.000000, 0),
(4, 74, 'PO-2026-000001', 10, 48000.000000, 480000.000000, 10),
(5, 116, 'PO-2026-000001', 15, 8000.000000, 120000.000000, 15);

--
-- Triggers `tblpodetailbarang`
--
DELIMITER $$
CREATE TRIGGER `trPODetailAfterInsert` AFTER INSERT ON `tblpodetailbarang` FOR EACH ROW BEGIN
    DECLARE Tanggal DATE;
    DECLARE NamaSupplier VARCHAR(100);
    DECLARE Catatan TEXT;
    DECLARE Keterangan TEXT;

    SELECT TblPO.Tanggal, tblsupplier.NamaSupplier, TblPO.Catatan
    INTO Tanggal, NamaSupplier, Catatan
    FROM TblPO
    INNER JOIN tblsupplier ON TblPO.idSupplier = tblsupplier.KodeSupplier
    WHERE TblPO.KodePO = NEW.idPO;

    SET Keterangan = IF(
        TRIM(Catatan) = '',
        CONCAT('Purchase Order : ', NEW.idPO, ', Supplier : ', NamaSupplier),
        CONCAT('Purchase Order : ', NEW.idPO, ', Supplier : ', NamaSupplier, ', Keterangan : ', Catatan)
    );

    -- MEMANGGIL PROSEDUR TERPUSAT (bukan INSERT manual lagi)
    CALL spInsertKS(
        NEW.idBarang, 
        Tanggal, 
        NEW.JumlahBeli, 
        'PO', 
        NEW.idPO, 
        NEW.AutoNumDetPO, 
        Keterangan
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trPODetailAfterUpdate` AFTER UPDATE ON `tblpodetailbarang` FOR EACH ROW BEGIN	
	DECLARE Tanggal DATE;
	DECLARE NamaSupplier VARCHAR(100);
	DECLARE Catatan TEXT;
	DECLARE Keterangan TEXT;
	IF OLD.idBarang<>NEW.idBarang AND OLD.JumlahBeli<>NEW.JumlahBeli THEN	
		-- Ambil data mentah dulu
		SELECT TblPO.Tanggal, tblsupplier.NamaSupplier, TblPO.Catatan
		INTO Tanggal, NamaSupplier, Catatan
		FROM TblPO
		INNER JOIN tblsupplier ON TblPO.idSupplier = tblsupplier.KodeSupplier
		WHERE TblPO.KodePO = NEW.idPO;
		
		-- Olah keterangan
		SET Keterangan = IF(
		 TRIM(Catatan) = '',
		 CONCAT('Purchase Order : ', NEW.idPO, ', Supplier : ', NamaSupplier),
		 CONCAT('Purchase Order : ', NEW.idPO, ', Supplier : ', NamaSupplier, ', Keterangan : ', Catatan)
		);
		
		-- Insert ke kartu stok
		INSERT INTO tblkartustok (
		 idBarang, Tanggal, Masuk, JenisTransaksi, idTransaksi,
		 idDetailTransaksi, CatatanKS, Fisik, PO, BL
		) VALUES (
		 NEW.idBarang, Tanggal, NEW.JumlahBeli, 'PO', NEW.idPO,
		 NEW.AutoNumDetPO, Keterangan, 0, NEW.JumlahBeli, 0
		);
	END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trPODetailBeforeDelete` BEFORE DELETE ON `tblpodetailbarang` FOR EACH ROW BEGIN
	DELETE FROM tblkartustok WHERE JenisTransaksi='PO' AND idDetailTransaksi=OLD.AutoNumDetPO;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trPODetailBeforeUpdate` BEFORE UPDATE ON `tblpodetailbarang` FOR EACH ROW BEGIN
	IF OLD.idBarang<>NEW.idBarang AND OLD.JumlahBeli<>NEW.JumlahBeli THEN
		DELETE FROM tblkartustok WHERE JenisTransaksi='PO' AND idDetailTransaksi=OLD.AutoNumDetPO;
	END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tblreturpenjualan`
--

CREATE TABLE `tblreturpenjualan` (
  `NoRetur` varchar(20) NOT NULL,
  `Tanggal` date NOT NULL,
  `NoFaktur` varchar(50) DEFAULT NULL,
  `idCustomer` varchar(20) DEFAULT NULL,
  `Catatan` tinytext DEFAULT NULL,
  `Status` varchar(20) DEFAULT 'APPROVED',
  `ApprovedBy` int(11) DEFAULT NULL,
  `TotalRetur` decimal(18,2) DEFAULT 0.00,
  `KodeUser` int(11) NOT NULL,
  `WaktuInput` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblreturpenjualan`
--

INSERT INTO `tblreturpenjualan` (`NoRetur`, `Tanggal`, `NoFaktur`, `idCustomer`, `Catatan`, `Status`, `ApprovedBy`, `TotalRetur`, `KodeUser`, `WaktuInput`) VALUES
('RJ-2026-000001', '2026-01-19', NULL, 'UMUM', 'POS-2026-000007 (Pelanggan Kasir (Umum)). ', 'REJECTED', 1, 21000.00, 1, '2026-01-19 16:06:00'),
('RJ-202602-000005', '2026-02-03', '	POS-2026-000018', 'UMUM', 'rusak', 'APPROVED', 1, 1060000.00, 1, '2026-02-03 06:19:14'),
('RJ-202602-000006', '2026-02-03', '	POS-2026-000019', 'UMUM', 'rusak', 'APPROVED', 1, 559000.00, 1, '2026-02-03 06:22:30'),
('RJ-202602-000007', '2026-02-03', '	POS-2026-000022', 'UMUM', '', 'REJECTED', 1, 10000.00, 1, '2026-02-03 06:39:58'),
('RJ-202602-000008', '2026-02-03', '	POS-2026-000022', 'UMUM', '', 'APPROVED', 1, 10000.00, 1, '2026-02-03 06:40:06');

-- --------------------------------------------------------

--
-- Table structure for table `tblreturpenjualandetail`
--

CREATE TABLE `tblreturpenjualandetail` (
  `AutoNum` int(11) NOT NULL,
  `NoRetur` varchar(20) NOT NULL,
  `idBarang` varchar(20) NOT NULL,
  `Jumlah` int(11) NOT NULL,
  `Alasan` varchar(100) DEFAULT 'Rusak'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblreturpenjualandetail`
--

INSERT INTO `tblreturpenjualandetail` (`AutoNum`, `NoRetur`, `idBarang`, `Jumlah`, `Alasan`) VALUES
(1, 'RJ-2026-000001', '73', 3, 'POS-2026-000007 (Pelanggan Kasir (Umum)). '),
(7, 'RJ-202602-000001', '73', 2, 'POS-2026-000014 (Pelanggan Kasir (Umum)). rusak'),
(8, 'RJ-202602-000002', '59', 1, 'POS-2026-000015 (Pelanggan Kasir (Umum)). rusak'),
(9, 'RJ-202602-000003', '234', 1, 'SJ-2025-000001 (pak rt sumirto). rusak'),
(10, 'RJ-202602-000004', '75', 2, '	POS-2026-000016 (Pelanggan Kasir (Umum)). rusak'),
(11, 'RJ-202602-000005', '57', 2, 'rusak'),
(12, 'RJ-202602-000006', '73', 2, 'rusak'),
(13, 'RJ-202602-000006', '66', 2, 'rusak'),
(14, 'RJ-202602-000006', '57', 1, 'rusak'),
(15, 'RJ-202602-000007', '112', 2, ''),
(16, 'RJ-202602-000008', '112', 2, '');

--
-- Triggers `tblreturpenjualandetail`
--
DELIMITER $$
CREATE TRIGGER `trReturDetailAfterInsert` AFTER INSERT ON `tblreturpenjualandetail` FOR EACH ROW BEGIN
    -- 1. Tambah Stok Barang (Barang masuk kembali ke gudang)
    UPDATE tblbarang 
    SET Jumlah = Jumlah + NEW.Jumlah
    WHERE KodeBarang = NEW.idBarang;

    -- 2. Catat Kartu Stok via SP yang sudah ada
    -- Parameter: idBarang, Tanggal, Jumlah, JenisTrans, NoBukti, idDetail, Keterangan
    CALL spInsertKS(NEW.idBarang, CURDATE(), NEW.Jumlah, 'RETUR', NEW.NoRetur, NEW.AutoNum, NEW.Alasan);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tblsatuan`
--

CREATE TABLE `tblsatuan` (
  `KodeSatuan` tinyint(4) NOT NULL,
  `NamaSatuan` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblsatuan`
--

INSERT INTO `tblsatuan` (`KodeSatuan`, `NamaSatuan`) VALUES
(2, 'Bks'),
(7, 'Btg'),
(12, 'Btl'),
(10, 'Dos'),
(17, 'ember'),
(13, 'Kg'),
(5, 'Klg'),
(11, 'Lbr'),
(3, 'M3'),
(9, 'Meter'),
(6, 'Pail'),
(4, 'Pcs'),
(8, 'Roll'),
(1, 'Sak');

-- --------------------------------------------------------

--
-- Table structure for table `tblso`
--

CREATE TABLE `tblso` (
  `AutoNum` int(11) NOT NULL,
  `KodeSO` varchar(20) NOT NULL DEFAULT '0',
  `Tanggal` date NOT NULL,
  `idCustomer` smallint(6) NOT NULL DEFAULT 0,
  `JumlahBarang` decimal(6,2) NOT NULL DEFAULT 0.00,
  `SubTotal` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `PPN` decimal(6,2) NOT NULL DEFAULT 0.00,
  `PPNRp` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `Diskon` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `GrandTotal` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `Aktif` char(1) NOT NULL DEFAULT '1',
  `Catatan` tinytext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblso`
--

INSERT INTO `tblso` (`AutoNum`, `KodeSO`, `Tanggal`, `idCustomer`, `JumlahBarang`, `SubTotal`, `PPN`, `PPNRp`, `Diskon`, `GrandTotal`, `Aktif`, `Catatan`) VALUES
(1, 'SO-2025-000001', '2025-11-29', 4, 9.00, 500000.0000, 11.00, 55000.0000, 0.0000, 555000.0000, '1', ''),
(2, 'SO-2025-000002', '2025-11-29', 1, 3.00, 15000.0000, 11.00, 1650.0000, 0.0000, 16650.0000, '0', ''),
(3, 'SO-2025-000003', '2025-12-09', 1, 30.00, 25130000.0000, 11.00, 2764300.0000, 0.0000, 27894300.0000, '1', ''),
(4, 'SO-2026-000001', '2026-02-03', 1, 20.00, 1100000.0000, 11.00, 121000.0000, 0.0000, 1221000.0000, '1', '');

-- --------------------------------------------------------

--
-- Table structure for table `tblsodetailbarang`
--

CREATE TABLE `tblsodetailbarang` (
  `AutoNum` int(11) NOT NULL,
  `idSO` varchar(20) NOT NULL,
  `idBarang` smallint(6) NOT NULL DEFAULT 0,
  `HargaJual` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `JumlahDetSO` smallint(6) NOT NULL DEFAULT 0,
  `TotalHarga` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `JumlahSJ` smallint(6) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblsodetailbarang`
--

INSERT INTO `tblsodetailbarang` (`AutoNum`, `idSO`, `idBarang`, `HargaJual`, `JumlahDetSO`, `TotalHarga`, `JumlahSJ`) VALUES
(1, 'SO-2025-000001', 234, 69000.0000, 4, 276000.0000, 4),
(2, 'SO-2025-000001', 105, 12000.0000, 1, 12000.0000, 1),
(3, 'SO-2025-000001', 1, 53000.0000, 4, 212000.0000, 4),
(5, 'SO-2025-000003', 112, 5000.0000, 10, 50000.0000, 10),
(6, 'SO-2025-000003', 151, 528000.0000, 10, 5280000.0000, 10),
(7, 'SO-2025-000003', 173, 1980000.0000, 10, 19800000.0000, 10),
(8, 'SO-2026-000001', 74, 55000.0000, 20, 1100000.0000, 20);

--
-- Triggers `tblsodetailbarang`
--
DELIMITER $$
CREATE TRIGGER `trSODetailAfterDelete` AFTER DELETE ON `tblsodetailbarang` FOR EACH ROW BEGIN
	CALL spDeleteKS ('SO',OLD.idSO,OLD.AutoNum);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trSODetailAfterInsert` AFTER INSERT ON `tblsodetailbarang` FOR EACH ROW BEGIN
	DECLARE Tanggal DATE;
	DECLARE NamaCustomer VARCHAR(100);
	DECLARE Catatan TEXT;
  	DECLARE Keterangan TEXT;
  	
	-- Ambil data mentah dulu
	SELECT TblSO.Tanggal, TblCustomer.NamaCustomer, TblSO.Catatan
	INTO Tanggal, NamaCustomer, Catatan
	FROM TblSO INNER JOIN TblCustomer ON tblso.idCustomer = TblCustomer.KodeCustomer
	WHERE TblSO.KodeSO = NEW.idSO;
	
	-- Olah keterangan
	SET Keterangan = IF(
	TRIM(Catatan) = '',
	CONCAT('Sales Order : ', NEW.idSO, ', Customer : ', NamaCustomer),
	CONCAT('Sales Order : ', NEW.idSO, ', Customer : ', NamaCustomer, ', Keterangan : ', Catatan)
	);

	CALL spInsertKS(NEW.idBarang,Tanggal,NEW.JumlahDetSO,'SO',NEW.idSO,NEW.AutoNum,Keterangan);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trSODetailAfterUpdate` AFTER UPDATE ON `tblsodetailbarang` FOR EACH ROW BEGIN
	DECLARE Tanggal DATE;
	DECLARE NamaCustomer VARCHAR(100);
	DECLARE Catatan TEXT;
  	DECLARE Keterangan TEXT;
--  	CALL spDeleteKS('SO',OLD.idSO,OLD.AutoNum);
		-- Ambil data mentah dulu
	SELECT TblSO.Tanggal, TblCustomer.NamaCustomer, TblSO.Catatan
	INTO Tanggal, NamaCustomer, Catatan
	FROM TblSO INNER JOIN TblCustomer ON tblso.idCustomer = TblCustomer.KodeCustomer
	WHERE TblSO.KodeSO = NEW.idSO;
	
	-- Olah keterangan
	SET Keterangan = IF(
	TRIM(Catatan) = '',
	CONCAT('Sales Order : ', NEW.idSO, ', Customer : ', NamaCustomer),
	CONCAT('Sales Order : ', NEW.idSO, ', Customer : ', NamaCustomer, ', Keterangan : ', Catatan)
	);

	CALL spInsertKS(NEW.idBarang,Tanggal,NEW.JumlahDetSO,'SO',NEW.idSO,NEW.AutoNum,Keterangan);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trSODetailBeforeUpdate` BEFORE UPDATE ON `tblsodetailbarang` FOR EACH ROW BEGIN
	DELETE FROM tblkartustok WHERE JenisTransaksi='SO' AND idTransaksi=OLD.idSO AND idDetailTransaksi=OLD.AutoNum;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tblsupplier`
--

CREATE TABLE `tblsupplier` (
  `KodeSupplier` mediumint(9) NOT NULL,
  `NamaSupplier` varchar(100) NOT NULL,
  `Alamat` varchar(255) NOT NULL,
  `Telp` varchar(50) NOT NULL,
  `NamaSales` varchar(100) NOT NULL,
  `TelpSales` varchar(50) NOT NULL,
  `Aktif` char(1) NOT NULL DEFAULT '1',
  `Catatan` tinytext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblsupplier`
--

INSERT INTO `tblsupplier` (`KodeSupplier`, `NamaSupplier`, `Alamat`, `Telp`, `NamaSales`, `TelpSales`, `Aktif`, `Catatan`) VALUES
(1, '53, UD.  ', 'Dukuh - Surabaya', '031-3558507/3523712', 'Ko Ci Wan', '', '1', ''),
(3, 'Agung Mas, UD. ', 'Bongkaran 16A, Surabaya.', '(031) 3551481', 'Joelianto/Dewi', '', '1', ''),
(4, 'Agung Sukses Abadi, PT. ', 'Jl Raya Romokalisari Industri 2/8, Surabaya', '', '', '', '1', ''),
(5, 'Ahmad Jaya, UD. ', 'Kertopaten 48', '08112312312', '', '', '1', ''),
(6, 'Aihua Dingzhou City Import and Export Trading Co., Ltd', 'Liusu Industrial Zone, Dingzhou, Hebei, China', '+86 311 80979856', 'Michael Liu Peng', '', '1', ''),
(7, 'Alam Abadi Aman, PT. ', 'Bongkaran 60, Surabaya', '(031) 3520414-3551361', 'Chun Hong', '', '1', ''),
(8, 'Alim, UD. ', 'Bongkaran, Surabaya', '(031) 3522217', 'Lulu/SIU, Aldi', '', '1', ''),
(9, 'Amanah Jaya', 'Jl. Semarang no 21. Sby', '083857058449', '', '', '1', ''),
(10, 'Andaka Jaya, UD. ', 'Karang Tembok. Sby', '71074124, 087855166624', 'pak yusup', '', '1', ''),
(11, 'Angka Baru, UD. ', 'Bongkaran, Sby', '', 'Evi', '', '1', ''),
(12, 'Anping County Tianze Metal Products Co., Ltd', 'East of the Anping County, Zhengrao Rd North Kilometers, Hengshui City, Hebei, China', '+86 10 52288186', 'Salina', '', '1', ''),
(13, 'Anping Hongda Wire Mesh Co, Ltd', '2-301 Beiyuanwangjiao Building, 44 xisanzhuangjie, Shijiazhuang, China.', '+8631186959338-9', 'John Woo Xue Nong', '', '1', ''),
(14, 'Austeel Indonesia Metal, PT', 'Gedung Graha Pena Lt 12 Ruang 1206, Jl Ahmad Yani no 88, Gayungan, Surabaya, Jawa Timur', '031-8202082', 'Iwan', '', '1', ''),
(15, 'BAKRI/AZIZ, UD. ', 'KERTOPATEN 52', '3574701/0818528998', '', '', '1', ''),
(16, 'Beijing Anping Hongxing International Trade Co., Ltd', '13F, no 4 Building, no 2 Dajiaoting Middle Road, Chaoyang District, Beijing, China', '+86 10 87952177', 'Salina', '', '1', ''),
(17, 'Beijing Xinyiborui Chemical Plant', 'No 511 Dahuichang Fengtai District, Beijing China', '+8641181153157', 'Ms Bonniea', '', '1', ''),
(18, 'BERKAT JAYA MAKMUR, TK. ', 'Surabaya', '', 'JAMES', '', '1', ''),
(19, 'Berkat Raja, UD. ', 'Sby', '031-60703181', 'WENAS (WEWE)', '', '1', ''),
(20, 'BHUSHAN POWER & STEEL LTD', '4th Floor, Tolstoy House, 15-17 Tolstoy Marg Connaught Place, New Delhi 110001', '+911130451000', '', '', '1', ''),
(21, 'Binamasa Adikerja, PT.', 'Jl. Raya Sukomanunggal Jaya Blok E-22, Surabaya', '031-7383388, 7345629', 'Adi/Yudhi', '', '1', ''),
(23, 'Bintang Terang, UD. ', 'Surabaya', '031-5981310', 'Santoso', '', '1', ''),
(24, 'Bisma Narendra, PT. ', 'Bekasi Fajar Ind Est, kawasan MM 2100, Jl. Sumba, Industri III, kav A3, Cibitung Bekasi 17520.', '(021) 8980950, 2520081', 'Bpk Agus Halim', '', '1', ''),
(25, 'BROMO PANULUH STEEL, PT. ', 'JL SONGOYUDAN 57. SBY (KANTOR). JL WRINGINANOM KM 3 3,6', '031-60645788, 8983127', 'KO IHIN, ANTON (PAJAK), penagihan 7325968', '', '1', ''),
(26, 'Bungursari, UD. ', 'Surabaya', '031-3552122', 'Adi', '', '1', ''),
(27, 'Cahaya Benteng Mas, PT. ', 'Rajawali 42, Surabaya 60175. Margomulyo Permai 32C, Greges, Asemrowo, Surabaya.', '031-7493585', 'Pak Eko/Hadi/Dwi', '', '1', ''),
(28, 'Caijian, He', 'No 49, East Jiefang Road, Huaian, Jiangshu, China', '+8651784925927', 'Caijian, He (Denise)', '', '1', ''),
(29, 'Calvari, PT. ', 'Semarang', '(031) 8533392', 'Titin/Fei Siak', '', '1', ''),
(30, 'Catur Mitra Sukses Makmur, PT. ', 'JL Rotan, Kawasan Industri Delta Silicon 3 Blok F27 No. 36-37, Bekasi, Jawa Barat, Indonesia', ' 021 52900015 ', 'Edward', '', '1', ''),
(31, 'China Machine Building International Hebei Co.,Ltd', '2-301 BEIYUANWANGJIAO BUILDING, 44 XISANZHUANGJIE, SHIJIAZHUANG, CHINA', '+8631186959338', 'John Wu', '', '1', ''),
(32, 'China Offshore Oil (Hongkong) International Trade Co., Ltd', 'Rm 907 JQD041 Wing Tuck Comm Ctr 177-183 Wing Lok St Hk', '+8613475424105', 'Zhang Qiang', '', '1', ''),
(33, 'China Steel Roll Technology Com Limited', 'Unit E, 15/F, Cheuk Nang Plaza 250, Henessy Rd, Wanchai, Hongkong.', '+852-31757377', 'Eric', '', '1', ''),
(34, 'CIPTO PERKASA, UD. ', 'L.I.K GG4 NO.111\r\nSEMARANG', '024-6581004/6581283', 'Cipto', '', '1', ''),
(35, 'Citra Logam, CV.', 'Ruko Surya Inti Permata Jemur, jl Jemur Andayani 50 Blok B31-32, Surabaya', '031-8496037', 'Hadinoto Ongko (Ko Jiang)', '', '1', ''),
(36, 'Dalco, PT. ', 'Tambak Langon Indah A17, Surabaya. Danau Agung. Kali Agung 9.', '031-3538704, 3534939, 3529950', 'Cik Tris, Ko Heri', '', '1', ''),
(37, 'Depo Baja Prima, PT. ', 'Ruko Celebration Grand Wisata AA no 11/12, Lambangsari, Tambun Selatan, Bekasi, Jawa Barat.\r\nKantor Cabang:\r\nRuko Anggrek Mas blok C-39 Sidoarjo, Jawa Timur\r\nOffice : 031 – 99702178', '021 29567976', 'Ryan', '+62 812-9677-6771', '1', ''),
(38, 'Dian Sentosa, PT. ', 'Rungkut Megah Raya Blok E/18-19, Kalirungkut, Rungkut, Surabaya', '031-8720041', 'Hendra', '', '1', ''),
(39, 'Dingsheng Metal Indonesia, PT.', 'Dusun Glatik RT020, RW005, Watesnegoro, Ngoro, Mojokerto, Jawa Timur', '', 'Shou Jie', '', '1', ''),
(40, 'DingzhouYuanda Metal & Wire Meshes Co., Ltd', 'Liusu Village, Liqingu Town, Dingzhou City, Hebei Province, China.', '+86-311-87652322', 'Mr Peng Liu', '', '1', ''),
(41, 'Dipanegara, CV. ', 'Jl Ngagel Timur no 49, Surabaya', '031 3525353', 'Hok Siong', '', '1', ''),
(42, 'Essar Indonesia (PI), PT. ', 'Bekasi Fajar Industrial Estate, Industri 3 Area kav #B1 Cinitung, Bekasi 17520, Jabar, Indonesia.', '021-8980152', 'SANJAY SHARMA, SOWMIYA', '', '1', ''),
(43, 'Essar Indonesia, PT. ', 'Bekasi Fajar Industrial Estate, Industri 3 Area kav #B1 Cinitung, Bekasi 17520, Jabar, Indonesia.          Hypermart: Jl. Parang Barong No. 18. RT. 010 / RW. 001. Kec. Krembangan Kel. Kemayoran Ph. / Fax. (62-31) 3537530\r\n', '(021) 8980152/4, 89982814', 'SANJAY SHARMA', '', '1', ''),
(44, 'Famili, UD', 'Jl. Semarang 118 Pojok Dekat Lampu Merah. Sby', '081703880290, 085101667478', 'Arif', '', '1', ''),
(45, 'Fausan', 'sby', '081553205556, 081357999935', '', '', '1', ''),
(46, 'Fumira, PT.', 'Jl Setiabudi 104, Semarang 50269', '(024) 7474447', 'Roy Antonius', '089680108296', '1', ''),
(47, 'Gajah Mas, UD. ', 'Simpang Drm Prm utr 3-9\r\nSurabaya', '(031) 7319577', 'Kiat Seng', '', '1', ''),
(48, 'Ganti Barang', 'Agung', '', '', '', '1', ''),
(49, 'Gaomi Maoyuan Hardware Products Co., Ltd', 'Youfang Resident Committee, Chaoyang subdistrict office, Gaomi, Shandong, China', '+86-536-2572689', 'Michael Liu Peng', '', '1', ''),
(51, 'Gillian Trading, Tk. ', 'Kapasan 166-168. Sby', '031- 3766362', '', '', '1', ''),
(52, 'Golden Buana Jaya, PT. ', 'Jl Raya Krikilan KM28, Driyorejo, Gresik Tel ', '7591616, 082245666369', 'Hindarto Gautama, Ci Ming', '', '1', ''),
(53, 'Golden Indowood, CV ', 'Kenjeran 303 - 2', '031-51501948(leni), 70991951 (ko Sugimin)', 'Sugimin', '0811330293', '1', ''),
(54, 'Graha Pilar Sentosa, PT.', 'Dumpiagung, Kel Dumpiagung Kec Kembangbahu, Lamongan, Jawa Timur', '+62 851-0221-2186', 'Irwan', '+62 851-0221-2186', '1', ''),
(55, 'Great Fortune,PT. ', 'Jl Raya Pakal no 1A, Benowo, Surabaya', '031-7422241-3', '', '', '1', ''),
(56, 'GUAN SENG, TK. ', 'A YANI 299. JOMBANG', '(0321) 861619, (031) 60503098', 'KA LIONG', '', '1', ''),
(57, 'Gunung Gahapi Bahara, PT. ', 'Jl. Imam Bonjol 4, Warung Bongkok, Desa Sukadanau, Cikarang Barat, Bekasi 17520', '021-89838180', 'Julius/Freddy', '', '1', ''),
(58, 'Gunung Garuda, CV. ', 'Bintoro Kecil Blok 3 no 13-G, Pandean Lamper, Semarang', '', 'Cik Hong', '', '1', ''),
(59, 'Gunung Mas Baru, UD.', 'Kembang Jepun, Sby. \r\nPERGUDANGAN ANGTROPOLIS. MARGOMULYO 31 BLOK B NO1', '(031) 3522623', 'Tek Fuk, rudi', '', '1', ''),
(60, 'Gunung Rajapaksi, PT. ', 'Cikarang Barat, Bekasi, Jakarta 17520.', '021-89838180/2', 'Rane', '', '1', ''),
(61, 'Gunung Subur, UD. ', 'Bongkaran, Surabaya', '(031) 3550761', 'Ko Wen Sin', '', '1', ''),
(62, 'Hanjaya, PT. ', 'Semarang', '(024) 3588177-189-190', 'Cik Hong', '', '1', ''),
(63, 'Hans Forever Trade, PT. ', 'Osowilangon, Surabaya', '031-7482196, 7498102', 'Ping2', '', '1', ''),
(64, 'Hanshi Saudara Jaya, PT. ', 'Romokalisari Blok E no 3, Surabaya', '031-99001428/9', 'Zhong Qian Biao', '', '1', ''),
(65, 'Hanshiputra Perkasa, PT. ', 'Pergudangan Osowilangon Permai Kav D-23, Surabaya', '031-7498080, 7434563, 7494563', 'A jing', '', '1', ''),
(66, 'Hanwa Indonesia, PT', 'Gd Midplaza 1 Lt9, Jl Jend. Sudirman Kav 10-11, Kel Karet Tengsin, Tanah Abang, Jakarta Pusat, DKI Jakarta', '021 57853033', 'Dita', '0811-1532-191', '1', ''),
(68, 'Subur Makmur UD', 'Surabaya', '0923423434', 'AABB', '', '1', ''),
(71, 'Bangun Persada PT', 'Semarang', '77126313123', 'KJJDAD', '12333123123123', '1', ''),
(72, 'erwer', 'werewr`', 'erewr', 'erwer', 'erwer', '1', 'werwerwerewr'),
(74, 'Bintang Timur CV', 'surabaya', '0923424234', '', '', '1', ''),
(75, 'sujitman', 'jombang', '081358899611', 'admin21', '12121', '1', 'uji coba'),
(77, 'aziz', 'dolopo madiun', '0812212121', '', '', '1', 'suplier rumput jepang'),
(78, 'aziz', 'jombang', '0812212121', '', '', '1', '');

-- --------------------------------------------------------

--
-- Table structure for table `tblsuratjalan`
--

CREATE TABLE `tblsuratjalan` (
  `AutoNum` int(11) NOT NULL,
  `KodeSJ` varchar(20) NOT NULL,
  `idCustomer` smallint(6) NOT NULL DEFAULT 0,
  `Tanggal` date NOT NULL,
  `JumlahBarang` decimal(8,2) NOT NULL DEFAULT 0.00,
  `SubTotal` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `PPN` decimal(6,2) NOT NULL DEFAULT 0.00,
  `PPNRp` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `Diskon` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `GrandTotal` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `Aktif` char(1) NOT NULL DEFAULT '1',
  `Catatan` tinytext NOT NULL,
  `idSO` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblsuratjalan`
--

INSERT INTO `tblsuratjalan` (`AutoNum`, `KodeSJ`, `idCustomer`, `Tanggal`, `JumlahBarang`, `SubTotal`, `PPN`, `PPNRp`, `Diskon`, `GrandTotal`, `Aktif`, `Catatan`, `idSO`) VALUES
(1, 'SJ-2025-000001', 4, '2025-11-29', 9.00, 500000.0000, 11.00, 55000.0000, 0.0000, 555000.0000, '1', '', 'SO-2025-000001'),
(2, 'SJ-2026-000001', 1, '2026-02-03', 20.00, 1100000.0000, 11.00, 121000.0000, 0.0000, 1221000.0000, '1', '', 'SO-2026-000001'),
(3, 'SJ-2026-000002', 1, '2026-02-03', 30.00, 25130000.0000, 11.00, 2764300.0000, 0.0000, 27894300.0000, '1', '', 'SO-2025-000003');

-- --------------------------------------------------------

--
-- Table structure for table `tblsuratjalandetailbarang`
--

CREATE TABLE `tblsuratjalandetailbarang` (
  `AutoNum` int(11) NOT NULL,
  `idBarang` smallint(6) NOT NULL,
  `idSJ` varchar(20) NOT NULL DEFAULT '',
  `Jumlah` decimal(8,2) NOT NULL DEFAULT 0.00,
  `HargaJual` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `TotalHarga` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `idDetailSO` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblsuratjalandetailbarang`
--

INSERT INTO `tblsuratjalandetailbarang` (`AutoNum`, `idBarang`, `idSJ`, `Jumlah`, `HargaJual`, `TotalHarga`, `idDetailSO`) VALUES
(1, 234, 'SJ-2025-000001', 4.00, 69000.0000, 276000.0000, 1),
(2, 105, 'SJ-2025-000001', 1.00, 12000.0000, 12000.0000, 2),
(3, 1, 'SJ-2025-000001', 4.00, 53000.0000, 212000.0000, 3),
(4, 74, 'SJ-2026-000001', 20.00, 55000.0000, 1100000.0000, 8),
(5, 112, 'SJ-2026-000002', 10.00, 5000.0000, 50000.0000, 5),
(6, 151, 'SJ-2026-000002', 10.00, 528000.0000, 5280000.0000, 6),
(7, 173, 'SJ-2026-000002', 10.00, 1980000.0000, 19800000.0000, 7);

--
-- Triggers `tblsuratjalandetailbarang`
--
DELIMITER $$
CREATE TRIGGER `trSJDetailAfterDelete` AFTER DELETE ON `tblsuratjalandetailbarang` FOR EACH ROW BEGIN
	UPDATE tblsodetailbarang SET tblsodetailbarang.JumlahSJ=tblsodetailbarang.JumlahSJ-OLD.Jumlah;
	CALL spDeleteKS('SJ',OLD.idSJ,OLD.AutoNum);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trSJDetailAfterInsert` AFTER INSERT ON `tblsuratjalandetailbarang` FOR EACH ROW BEGIN
	DECLARE Tanggal DATE;
	DECLARE NamaCustomer VARCHAR(100);
	DECLARE Catatan TEXT;
	DECLARE Keterangan TEXT;
  
  
	SELECT TblSuratJalan.Tanggal,TblCustomer.NamaCustomer,TblSuratJalan.Catatan
	INTO Tanggal,NamaCustomer,Catatan 
	FROM TblSuratJalan INNER JOIN TblCustomer ON idCustomer=KodeCustomer WHERE KodeSJ=NEW.idSJ;

	SET Keterangan = IF(
	TRIM(Catatan) = '',
	CONCAT('Surat Jalan : ', NEW.idSJ, ', Customer : ', NamaCustomer),
	CONCAT('Surat Jalan : ', NEW.idSJ, ', Customer : ', NamaCustomer, ', Keterangan : ', Catatan)
	);
	UPDATE TblSODetailBarang SET TblSODetailBarang.JumlahSJ=TblSODetailbarang.JumlahSJ+NEW.Jumlah WHERE tblsodetailbarang.AutoNum=NEW.idDetailSO;
	CALL spInsertKS(NEW.idBarang,Tanggal,NEW.Jumlah,'SJ',NEW.idSJ,NEW.AutoNum,Keterangan);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tbltest1`
--

CREATE TABLE `tbltest1` (
  `Col1` varchar(100) DEFAULT NULL,
  `Col2` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbltest1`
--

INSERT INTO `tbltest1` (`Col1`, `Col2`) VALUES
('SO-2025-000006', '2025-09-13 16:30:54');

-- --------------------------------------------------------

--
-- Stand-in structure for view `viewbarang`
-- (See below for the actual view)
--
CREATE TABLE `viewbarang` (
`KodeBarang` smallint(6)
,`HarusOrder` char(1)
,`NamaBarang` varchar(255)
,`Satuan` varchar(100)
,`idKategori` tinyint(4)
,`HargaBeli` decimal(20,0)
,`HargaJual` decimal(20,0)
,`HPP` decimal(20,0)
,`Jumlah` smallint(6)
,`LamaOrder` tinyint(4)
,`Catatan` tinytext
,`Aktif` char(1)
,`PO` smallint(6)
,`SO` smallint(6)
,`NamaKategori` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `viewdaftarbl`
-- (See below for the actual view)
--
CREATE TABLE `viewdaftarbl` (
`AutoNum` int(11)
,`NamaSupplier` varchar(100)
,`KodePenerimaan` varchar(20)
,`Tanggal` date
,`idSupplier` int(11)
,`TotalBarang` decimal(8,2)
,`SubTotal` decimal(18,4)
,`PPN` decimal(6,2)
,`PPNRp` decimal(10,4)
,`Diskon` decimal(18,4)
,`GrandTotal` decimal(18,4)
,`idPO` varchar(20)
,`Catatan` tinytext
,`Aktif` char(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `viewdaftarbldetailbarang`
-- (See below for the actual view)
--
CREATE TABLE `viewdaftarbldetailbarang` (
`AutoNum` int(11)
,`idPenerimaan` varchar(20)
,`idBarang` smallint(6)
,`Jumlah` smallint(6)
,`HargaBeli` decimal(18,4)
,`TotalHarga` decimal(18,4)
,`idDetailPO` int(11)
,`NamaBarang` varchar(255)
,`Satuan` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `viewdaftarpo`
-- (See below for the actual view)
--
CREATE TABLE `viewdaftarpo` (
`AutoNum` int(11)
,`KodePO` varchar(30)
,`Tanggal` date
,`idSupplier` smallint(6)
,`TotalBarang` smallint(6)
,`SubTotal` decimal(20,6)
,`PPN` decimal(20,6)
,`PPNRp` decimal(20,6)
,`Diskon` decimal(20,6)
,`GrandTotal` decimal(20,6)
,`Catatan` tinytext
,`Aktif` char(50)
,`NamaSupplier` varchar(100)
,`JumlahBL` smallint(6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `viewdaftarpodetailbarang`
-- (See below for the actual view)
--
CREATE TABLE `viewdaftarpodetailbarang` (
`AutoNumDetPO` int(11)
,`idBarang` smallint(6)
,`idPO` varchar(30)
,`JumlahBeli` smallint(6)
,`HargaBeliDetPO` decimal(20,6)
,`TotalHarga` decimal(20,6)
,`JumlahDiterima` smallint(6)
,`NamaBarang` varchar(255)
,`Satuan` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `viewdaftarsj`
-- (See below for the actual view)
--
CREATE TABLE `viewdaftarsj` (
`AutoNum` int(11)
,`KodeSJ` varchar(20)
,`Tanggal` date
,`idCustomer` smallint(6)
,`JumlahBarang` decimal(8,2)
,`SubTotal` decimal(18,4)
,`PPN` decimal(6,2)
,`PPNRp` decimal(18,4)
,`Diskon` decimal(18,4)
,`GrandTotal` decimal(18,4)
,`Aktif` char(1)
,`Catatan` tinytext
,`idSO` varchar(20)
,`NamaCustomer` varchar(255)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `viewdaftarsjdetailbarang`
-- (See below for the actual view)
--
CREATE TABLE `viewdaftarsjdetailbarang` (
`AutoNum` int(11)
,`idBarang` smallint(6)
,`idSJ` varchar(20)
,`Jumlah` decimal(8,2)
,`HargaJual` decimal(18,4)
,`TotalHarga` decimal(18,4)
,`idDetailSO` int(11)
,`NamaBarang` varchar(255)
,`Satuan` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `viewdaftarso`
-- (See below for the actual view)
--
CREATE TABLE `viewdaftarso` (
`AutoNum` int(11)
,`KodeSO` varchar(20)
,`Tanggal` date
,`idCustomer` smallint(6)
,`JumlahBarang` decimal(6,2)
,`SubTotal` decimal(18,4)
,`PPN` decimal(6,2)
,`PPNRp` decimal(18,4)
,`Diskon` decimal(18,4)
,`GrandTotal` decimal(18,4)
,`Aktif` char(1)
,`Catatan` tinytext
,`NamaCustomer` varchar(255)
,`JumlahKirim` smallint(6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `viewdaftarsodetailbarang`
-- (See below for the actual view)
--
CREATE TABLE `viewdaftarsodetailbarang` (
`AutoNum` int(11)
,`idSO` varchar(20)
,`idBarang` smallint(6)
,`JumlahDetSo` smallint(6)
,`HargaJual` decimal(18,4)
,`TotalHarga` decimal(18,4)
,`JumlahSJ` smallint(6)
,`NamaBarang` varchar(255)
,`Satuan` varchar(100)
);

-- --------------------------------------------------------

--
-- Structure for view `viewbarang`
--
DROP TABLE IF EXISTS `viewbarang`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `viewbarang`  AS SELECT `tblbarang`.`KodeBarang` AS `KodeBarang`, `FuncIsBarangHarusOrder`(`tblbarang`.`KodeBarang`,`tblbarang`.`Jumlah`,`tblbarang`.`LamaOrder`) AS `HarusOrder`, `tblbarang`.`NamaBarang` AS `NamaBarang`, `tblbarang`.`Satuan` AS `Satuan`, `tblbarang`.`idKategori` AS `idKategori`, `tblbarang`.`HargaBeli` AS `HargaBeli`, `tblbarang`.`HargaJual` AS `HargaJual`, `tblbarang`.`HPP` AS `HPP`, `tblbarang`.`Jumlah` AS `Jumlah`, `tblbarang`.`LamaOrder` AS `LamaOrder`, `tblbarang`.`Catatan` AS `Catatan`, `tblbarang`.`Aktif` AS `Aktif`, `tblbarang`.`PO` AS `PO`, `tblbarang`.`SO` AS `SO`, `tblkategoribarang`.`NamaKategori` AS `NamaKategori` FROM (`tblbarang` join `tblkategoribarang` on(`tblbarang`.`idKategori` = `tblkategoribarang`.`KodeKategori`)) ;

-- --------------------------------------------------------

--
-- Structure for view `viewdaftarbl`
--
DROP TABLE IF EXISTS `viewdaftarbl`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `viewdaftarbl`  AS SELECT `tblpenerimaanbarang`.`AutoNum` AS `AutoNum`, `tblsupplier`.`NamaSupplier` AS `NamaSupplier`, `tblpenerimaanbarang`.`KodePenerimaan` AS `KodePenerimaan`, `tblpenerimaanbarang`.`Tanggal` AS `Tanggal`, `tblpenerimaanbarang`.`idSupplier` AS `idSupplier`, `tblpenerimaanbarang`.`TotalBarang` AS `TotalBarang`, `tblpenerimaanbarang`.`SubTotal` AS `SubTotal`, `tblpenerimaanbarang`.`PPN` AS `PPN`, `tblpenerimaanbarang`.`PPNRp` AS `PPNRp`, `tblpenerimaanbarang`.`Diskon` AS `Diskon`, `tblpenerimaanbarang`.`GrandTotal` AS `GrandTotal`, `tblpenerimaanbarang`.`idPO` AS `idPO`, `tblpenerimaanbarang`.`Catatan` AS `Catatan`, `tblpenerimaanbarang`.`Aktif` AS `Aktif` FROM (`tblpenerimaanbarang` join `tblsupplier` on(`tblpenerimaanbarang`.`idSupplier` = `tblsupplier`.`KodeSupplier`)) ;

-- --------------------------------------------------------

--
-- Structure for view `viewdaftarbldetailbarang`
--
DROP TABLE IF EXISTS `viewdaftarbldetailbarang`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `viewdaftarbldetailbarang`  AS SELECT `tblpenerimaanbarangdetailbarang`.`AutoNum` AS `AutoNum`, `tblpenerimaanbarangdetailbarang`.`idPenerimaan` AS `idPenerimaan`, `tblpenerimaanbarangdetailbarang`.`idBarang` AS `idBarang`, `tblpenerimaanbarangdetailbarang`.`Jumlah` AS `Jumlah`, `tblpenerimaanbarangdetailbarang`.`HargaBeli` AS `HargaBeli`, `tblpenerimaanbarangdetailbarang`.`TotalHarga` AS `TotalHarga`, `tblpenerimaanbarangdetailbarang`.`idDetailPO` AS `idDetailPO`, `tblbarang`.`NamaBarang` AS `NamaBarang`, `tblbarang`.`Satuan` AS `Satuan` FROM (`tblpenerimaanbarangdetailbarang` join `tblbarang` on(`tblpenerimaanbarangdetailbarang`.`idBarang` = `tblbarang`.`KodeBarang`)) ;

-- --------------------------------------------------------

--
-- Structure for view `viewdaftarpo`
--
DROP TABLE IF EXISTS `viewdaftarpo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `viewdaftarpo`  AS SELECT `tblpo`.`AutoNum` AS `AutoNum`, `tblpo`.`KodePO` AS `KodePO`, `tblpo`.`Tanggal` AS `Tanggal`, `tblpo`.`idSupplier` AS `idSupplier`, `tblpo`.`TotalBarang` AS `TotalBarang`, `tblpo`.`SubTotal` AS `SubTotal`, `tblpo`.`PPN` AS `PPN`, `tblpo`.`PPNRp` AS `PPNRp`, `tblpo`.`Diskon` AS `Diskon`, `tblpo`.`GrandTotal` AS `GrandTotal`, `tblpo`.`Catatan` AS `Catatan`, `tblpo`.`Aktif` AS `Aktif`, `tblsupplier`.`NamaSupplier` AS `NamaSupplier`, `FuncPOGetTotalBL`(`tblpo`.`KodePO`) AS `JumlahBL` FROM (`tblpo` join `tblsupplier` on(`tblpo`.`idSupplier` = `tblsupplier`.`KodeSupplier`)) ;

-- --------------------------------------------------------

--
-- Structure for view `viewdaftarpodetailbarang`
--
DROP TABLE IF EXISTS `viewdaftarpodetailbarang`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `viewdaftarpodetailbarang`  AS SELECT `tblpodetailbarang`.`AutoNumDetPO` AS `AutoNumDetPO`, `tblpodetailbarang`.`idBarang` AS `idBarang`, `tblpodetailbarang`.`idPO` AS `idPO`, `tblpodetailbarang`.`JumlahBeli` AS `JumlahBeli`, `tblpodetailbarang`.`HargaBeliDetPO` AS `HargaBeliDetPO`, `tblpodetailbarang`.`TotalHarga` AS `TotalHarga`, `tblpodetailbarang`.`JumlahDiterima` AS `JumlahDiterima`, `tblbarang`.`NamaBarang` AS `NamaBarang`, `tblbarang`.`Satuan` AS `Satuan` FROM (`tblpodetailbarang` join `tblbarang` on(`tblpodetailbarang`.`idBarang` = `tblbarang`.`KodeBarang`)) ;

-- --------------------------------------------------------

--
-- Structure for view `viewdaftarsj`
--
DROP TABLE IF EXISTS `viewdaftarsj`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `viewdaftarsj`  AS SELECT `tblsuratjalan`.`AutoNum` AS `AutoNum`, `tblsuratjalan`.`KodeSJ` AS `KodeSJ`, `tblsuratjalan`.`Tanggal` AS `Tanggal`, `tblsuratjalan`.`idCustomer` AS `idCustomer`, `tblsuratjalan`.`JumlahBarang` AS `JumlahBarang`, `tblsuratjalan`.`SubTotal` AS `SubTotal`, `tblsuratjalan`.`PPN` AS `PPN`, `tblsuratjalan`.`PPNRp` AS `PPNRp`, `tblsuratjalan`.`Diskon` AS `Diskon`, `tblsuratjalan`.`GrandTotal` AS `GrandTotal`, `tblsuratjalan`.`Aktif` AS `Aktif`, `tblsuratjalan`.`Catatan` AS `Catatan`, `tblsuratjalan`.`idSO` AS `idSO`, `tblcustomer`.`NamaCustomer` AS `NamaCustomer` FROM (`tblsuratjalan` join `tblcustomer` on(`tblsuratjalan`.`idCustomer` = `tblcustomer`.`KodeCustomer`)) ;

-- --------------------------------------------------------

--
-- Structure for view `viewdaftarsjdetailbarang`
--
DROP TABLE IF EXISTS `viewdaftarsjdetailbarang`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `viewdaftarsjdetailbarang`  AS SELECT `tblsuratjalandetailbarang`.`AutoNum` AS `AutoNum`, `tblsuratjalandetailbarang`.`idBarang` AS `idBarang`, `tblsuratjalandetailbarang`.`idSJ` AS `idSJ`, `tblsuratjalandetailbarang`.`Jumlah` AS `Jumlah`, `tblsuratjalandetailbarang`.`HargaJual` AS `HargaJual`, `tblsuratjalandetailbarang`.`TotalHarga` AS `TotalHarga`, `tblsuratjalandetailbarang`.`idDetailSO` AS `idDetailSO`, `tblbarang`.`NamaBarang` AS `NamaBarang`, `tblbarang`.`Satuan` AS `Satuan` FROM (`tblsuratjalandetailbarang` join `tblbarang` on(`tblsuratjalandetailbarang`.`idBarang` = `tblbarang`.`KodeBarang`)) ;

-- --------------------------------------------------------

--
-- Structure for view `viewdaftarso`
--
DROP TABLE IF EXISTS `viewdaftarso`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `viewdaftarso`  AS SELECT `tblso`.`AutoNum` AS `AutoNum`, `tblso`.`KodeSO` AS `KodeSO`, `tblso`.`Tanggal` AS `Tanggal`, `tblso`.`idCustomer` AS `idCustomer`, `tblso`.`JumlahBarang` AS `JumlahBarang`, `tblso`.`SubTotal` AS `SubTotal`, `tblso`.`PPN` AS `PPN`, `tblso`.`PPNRp` AS `PPNRp`, `tblso`.`Diskon` AS `Diskon`, `tblso`.`GrandTotal` AS `GrandTotal`, `tblso`.`Aktif` AS `Aktif`, `tblso`.`Catatan` AS `Catatan`, `tblcustomer`.`NamaCustomer` AS `NamaCustomer`, `FuncSOGetTotalSJ`(`tblso`.`KodeSO`) AS `JumlahKirim` FROM (`tblso` join `tblcustomer` on(`tblso`.`idCustomer` = `tblcustomer`.`KodeCustomer`)) ;

-- --------------------------------------------------------

--
-- Structure for view `viewdaftarsodetailbarang`
--
DROP TABLE IF EXISTS `viewdaftarsodetailbarang`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `viewdaftarsodetailbarang`  AS SELECT `tblsodetailbarang`.`AutoNum` AS `AutoNum`, `tblsodetailbarang`.`idSO` AS `idSO`, `tblsodetailbarang`.`idBarang` AS `idBarang`, `tblsodetailbarang`.`JumlahDetSO` AS `JumlahDetSo`, `tblsodetailbarang`.`HargaJual` AS `HargaJual`, `tblsodetailbarang`.`TotalHarga` AS `TotalHarga`, `tblsodetailbarang`.`JumlahSJ` AS `JumlahSJ`, `tblbarang`.`NamaBarang` AS `NamaBarang`, `tblbarang`.`Satuan` AS `Satuan` FROM (`tblsodetailbarang` join `tblbarang` on(`tblsodetailbarang`.`idBarang` = `tblbarang`.`KodeBarang`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tblbarang`
--
ALTER TABLE `tblbarang`
  ADD PRIMARY KEY (`KodeBarang`),
  ADD UNIQUE KEY `NamaBarang` (`NamaBarang`);

--
-- Indexes for table `tblcustomer`
--
ALTER TABLE `tblcustomer`
  ADD PRIMARY KEY (`KodeCustomer`);

--
-- Indexes for table `tblhakakses`
--
ALTER TABLE `tblhakakses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `KodeKategori` (`KodeKategori`);

--
-- Indexes for table `tblkartustok`
--
ALTER TABLE `tblkartustok`
  ADD UNIQUE KEY `AutoNumKS` (`AutoNumKS`),
  ADD KEY `idBarang` (`idBarang`),
  ADD KEY `Tanggal` (`Tanggal`),
  ADD KEY `idDetailTransaksi` (`idDetailTransaksi`),
  ADD KEY `idTransaksi` (`idTransaksi`);

--
-- Indexes for table `tblkategoribarang`
--
ALTER TABLE `tblkategoribarang`
  ADD PRIMARY KEY (`KodeKategori`);

--
-- Indexes for table `tblkategoriuser`
--
ALTER TABLE `tblkategoriuser`
  ADD PRIMARY KEY (`KodeKategori`);

--
-- Indexes for table `tbllogin`
--
ALTER TABLE `tbllogin`
  ADD PRIMARY KEY (`KodeUser`),
  ADD UNIQUE KEY `UserLogin` (`UserLogin`);

--
-- Indexes for table `tblpenerimaanbarang`
--
ALTER TABLE `tblpenerimaanbarang`
  ADD PRIMARY KEY (`AutoNum`),
  ADD UNIQUE KEY `KodePenerimaan` (`KodePenerimaan`),
  ADD KEY `Tanggal` (`Tanggal`),
  ADD KEY `idSupplier` (`idSupplier`);

--
-- Indexes for table `tblpenerimaanbarangdetailbarang`
--
ALTER TABLE `tblpenerimaanbarangdetailbarang`
  ADD PRIMARY KEY (`AutoNum`),
  ADD KEY `idPenerimaan` (`idPenerimaan`),
  ADD KEY `idBarang` (`idBarang`),
  ADD KEY `idDetailPO` (`idDetailPO`);

--
-- Indexes for table `tblpenjualankasir`
--
ALTER TABLE `tblpenjualankasir`
  ADD PRIMARY KEY (`AutoNum`),
  ADD UNIQUE KEY `NoNota` (`NoNota`);

--
-- Indexes for table `tblpenjualankasirdetail`
--
ALTER TABLE `tblpenjualankasirdetail`
  ADD PRIMARY KEY (`AutoNum`),
  ADD KEY `NoNota` (`NoNota`),
  ADD KEY `idBarang` (`idBarang`);

--
-- Indexes for table `tblpo`
--
ALTER TABLE `tblpo`
  ADD PRIMARY KEY (`AutoNum`),
  ADD UNIQUE KEY `KodePO` (`KodePO`);

--
-- Indexes for table `tblpodetailbarang`
--
ALTER TABLE `tblpodetailbarang`
  ADD PRIMARY KEY (`AutoNumDetPO`);

--
-- Indexes for table `tblreturpenjualan`
--
ALTER TABLE `tblreturpenjualan`
  ADD PRIMARY KEY (`NoRetur`);

--
-- Indexes for table `tblreturpenjualandetail`
--
ALTER TABLE `tblreturpenjualandetail`
  ADD PRIMARY KEY (`AutoNum`),
  ADD KEY `NoRetur` (`NoRetur`),
  ADD KEY `idBarang` (`idBarang`);

--
-- Indexes for table `tblsatuan`
--
ALTER TABLE `tblsatuan`
  ADD PRIMARY KEY (`KodeSatuan`),
  ADD UNIQUE KEY `NamaSatuan` (`NamaSatuan`);

--
-- Indexes for table `tblso`
--
ALTER TABLE `tblso`
  ADD PRIMARY KEY (`AutoNum`),
  ADD UNIQUE KEY `KodeSO` (`KodeSO`);

--
-- Indexes for table `tblsodetailbarang`
--
ALTER TABLE `tblsodetailbarang`
  ADD PRIMARY KEY (`AutoNum`),
  ADD KEY `idSO` (`idSO`),
  ADD KEY `idBarang` (`idBarang`);

--
-- Indexes for table `tblsupplier`
--
ALTER TABLE `tblsupplier`
  ADD PRIMARY KEY (`KodeSupplier`);

--
-- Indexes for table `tblsuratjalan`
--
ALTER TABLE `tblsuratjalan`
  ADD PRIMARY KEY (`AutoNum`),
  ADD UNIQUE KEY `KodeSJ` (`KodeSJ`);

--
-- Indexes for table `tblsuratjalandetailbarang`
--
ALTER TABLE `tblsuratjalandetailbarang`
  ADD PRIMARY KEY (`AutoNum`),
  ADD KEY `idBarang` (`idBarang`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tblbarang`
--
ALTER TABLE `tblbarang`
  MODIFY `KodeBarang` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=238;

--
-- AUTO_INCREMENT for table `tblcustomer`
--
ALTER TABLE `tblcustomer`
  MODIFY `KodeCustomer` mediumint(9) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tblhakakses`
--
ALTER TABLE `tblhakakses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `tblkartustok`
--
ALTER TABLE `tblkartustok`
  MODIFY `AutoNumKS` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=292;

--
-- AUTO_INCREMENT for table `tblkategoribarang`
--
ALTER TABLE `tblkategoribarang`
  MODIFY `KodeKategori` tinyint(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tblkategoriuser`
--
ALTER TABLE `tblkategoriuser`
  MODIFY `KodeKategori` tinyint(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbllogin`
--
ALTER TABLE `tbllogin`
  MODIFY `KodeUser` smallint(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tblpenerimaanbarang`
--
ALTER TABLE `tblpenerimaanbarang`
  MODIFY `AutoNum` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tblpenerimaanbarangdetailbarang`
--
ALTER TABLE `tblpenerimaanbarangdetailbarang`
  MODIFY `AutoNum` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tblpenjualankasir`
--
ALTER TABLE `tblpenjualankasir`
  MODIFY `AutoNum` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `tblpenjualankasirdetail`
--
ALTER TABLE `tblpenjualankasirdetail`
  MODIFY `AutoNum` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=125;

--
-- AUTO_INCREMENT for table `tblpo`
--
ALTER TABLE `tblpo`
  MODIFY `AutoNum` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tblpodetailbarang`
--
ALTER TABLE `tblpodetailbarang`
  MODIFY `AutoNumDetPO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tblreturpenjualandetail`
--
ALTER TABLE `tblreturpenjualandetail`
  MODIFY `AutoNum` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `tblsatuan`
--
ALTER TABLE `tblsatuan`
  MODIFY `KodeSatuan` tinyint(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `tblso`
--
ALTER TABLE `tblso`
  MODIFY `AutoNum` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tblsodetailbarang`
--
ALTER TABLE `tblsodetailbarang`
  MODIFY `AutoNum` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tblsupplier`
--
ALTER TABLE `tblsupplier`
  MODIFY `KodeSupplier` mediumint(9) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `tblsuratjalan`
--
ALTER TABLE `tblsuratjalan`
  MODIFY `AutoNum` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tblsuratjalandetailbarang`
--
ALTER TABLE `tblsuratjalandetailbarang`
  MODIFY `AutoNum` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

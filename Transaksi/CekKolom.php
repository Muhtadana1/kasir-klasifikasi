<?php
include __DIR__ . '/../Database/DBConnection.php';
echo "<h3>Daftar Kolom Tabel Retur Detail:</h3><ul>";
$result = $conn->query("SHOW COLUMNS FROM tblreturpenjualandetail");
while($row = $result->fetch_assoc()){
    echo "<li>" . $row['Field'] . "</li>";
}
echo "</ul>";
?>
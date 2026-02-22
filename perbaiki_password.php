<?php
// Password plaintext yang kita inginkan
$password_admin = 'admin12345';

// Hasilkan hash yang aman
$hash_admin = password_hash($password_admin, PASSWORD_DEFAULT);

echo "Hash BARU untuk Administrator (admin12345):<br>";
echo "<textarea rows='3' cols='70' readonly>" . htmlspecialchars($hash_admin) . "</textarea>";
?>
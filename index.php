<?php
include 'Database/DBConnection.php';
include 'Database/UserLoginAuth.php';
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $auth = new Auth($conn);
    $username = $_POST['username'];
    $password = $_POST['password'];
    $message = $auth->login($username, $password);

}
if (isset($_GET['sessout'])) {
    $message = "Anda telah keluar dari sistem.";
}
if (isset($_GET['logout'])) {
     session_unset();
     session_destroy();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Form</title>
    <link href="bootstrap/bootstrap.min.css" rel="stylesheet">
    <script src="bootstrap/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card p-4 shadow" style="width: 25rem;">
            <h3 class="text-center">Login</h3>
             <?php if (!empty($message)): ?>
                <div class="alert alert-info text-center"><?= $message; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan Username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan Password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>          
        </div>
    </div>
</body>
</html>
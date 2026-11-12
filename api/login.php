<?php
require 'koneksi.php';

// Cek jika cookie login sudah ada, langsung arahkan ke laporan
if (isset($_COOKIE['admin_logged_in']) && $_COOKIE['admin_logged_in'] === 'true') {
    header("Location: laporan.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password_hash'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = :user");
        $stmt->execute([':user' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            // Simpan status login di Cookie Browser selama 1 hari (86400 detik)
            setcookie('admin_logged_in', 'true', time() + 86400, "/");
            setcookie('admin_username', $admin['username'], time() + 86400, "/");

            header("Location: laporan.php");
            exit;
        } else {
            $error = "Username atau password yang Anda masukkan salah!";
        }
    } catch (PDOException $e) {
        $error = "Terjadi kesalahan sistem: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Toko Gilang</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f4f7f6; }
        .login-box { background: white; padding: 35px 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 340px; border-top: 4px solid #24b47e; }
        .login-box h2 { margin: 0 0 20px; text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 14px; color: #555; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 14px; }
        button { width: 100%; padding: 11px; background-color: #24b47e; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: bold; margin-top: 5px; }
        button:hover { background-color: #1c8c61; }
        .error-msg { background-color: #ffe6e6; color: #d93025; padding: 10px; border-radius: 5px; font-size: 13px; margin-bottom: 15px; text-align: center; }
        .back-link { text-align: center; margin-top: 20px; font-size: 13px; }
        .back-link a { color: #555; text-decoration: none; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Login Admin</h2>
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Masukkan username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Masukkan password" required>
            </div>
            <button type="submit">Masuk</button>
        </form>
        <div class="back-link">
            <a href="index.php">&laquo; Kembali ke Katalog Produk</a>
        </div>
    </div>
</body>
</html>

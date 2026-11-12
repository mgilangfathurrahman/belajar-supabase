<?php
// Jika sudah login, langsung arahkan ke halaman laporan
if (isset($_COOKIE['admin_logged_in']) && $_COOKIE['admin_logged_in'] === 'true') {
    header("Location: laporan.php");
    exit;
}

require 'koneksi.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Username dan password wajib diisi!";
    } else {
        try {
            // Cek username dan pastikan status akun aktif = true
            $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username AND aktif = true LIMIT 1");
            $stmt->execute([':username' => $username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $masa_berlaku = time() + 86400; // Berlaku 1 hari (24 jam)

                setcookie('admin_logged_in', 'true', $masa_berlaku, "/");
                setcookie('admin_username', $admin['username'], $masa_berlaku, "/");
                setcookie('admin_nama', $admin['nama'] ?? $admin['username'], $masa_berlaku, "/");
                setcookie('admin_role', $admin['role'] ?? 'admin', $masa_berlaku, "/");

                header("Location: laporan.php");
                exit;
            } else {
                $error = "Username tidak ditemukan, password salah, atau akun dinonaktifkan!";
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan koneksi database: " . $e->getMessage();
        }
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
        body {
            font-family: sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-box {
            background: #ffffff;
            padding: 35px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 360px;
        }
        .login-box h2 {
            margin: 0 0 8px;
            color: #222;
            text-align: center;
        }
        .login-box p.subtitle {
            margin: 0 0 22px;
            color: #666;
            font-size: 13px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: bold;
            color: #444;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .form-group input:focus {
            border-color: #24b47e;
            outline: none;
        }
        .btn-login {
            width: 100%;
            padding: 11px;
            background: #24b47e;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 5px;
        }
        .btn-login:hover {
            background: #1c8c61;
        }
        .alert-err {
            background: #ffe6e6;
            color: #d93025;
            padding: 10px;
            border-radius: 5px;
            font-size: 13px;
            margin-bottom: 15px;
            text-align: center;
        }
        .alert-ok {
            background: #e6f7f0;
            color: #1c8c61;
            padding: 10px;
            border-radius: 5px;
            font-size: 13px;
            margin-bottom: 15px;
            text-align: center;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 18px;
            font-size: 13px;
            color: #24b47e;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Login Pengelola</h2>
        <p class="subtitle">Toko Gilang — Sistem Manajemen</p>

        <?php if (isset($_GET['status']) && $_GET['status'] === 'logout'): ?>
            <div class="alert-ok">Anda telah berhasil keluar dari sesi admin.</div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert-err"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Masukkan username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="btn-login">Masuk ke Dasbor</button>
        </form>

        <a href="index.php" class="back-link">← Kembali ke Katalog Toko</a>
    </div>
</body>
</html>

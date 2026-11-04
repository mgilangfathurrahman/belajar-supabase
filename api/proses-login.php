<?php
session_start();
require 'koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        // Ambil data user berdasarkan username dari database Supabase
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Cek apakah user ditemukan dan verifikasi passwordnya cocok
        if ($user && password_verify($password, $user['password'])) {
            // Jika benar, set session login
            $_SESSION['login'] = true;
            $_SESSION['username'] = $user['username'];
            header("Location: tampil-data.php");
            exit;
        } else {
            // Jika salah, kembalikan ke halaman login dengan pesan error
            header("Location: login.php?error=1");
            exit;
        }

    } catch (PDOException $e) {
        echo "Terjadi kesalahan sistem: " . $e->getMessage();
    }
} else {
    header("Location: login.php");
    exit;
}
?>

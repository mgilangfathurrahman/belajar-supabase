<?php
session_start();

// Contoh sederhana (bisa diganti pengecekan ke database Supabase tabel 'users')
$admin_user = "admin";
$admin_pass = "12345"; // Ganti password sesuai keinginan Anda

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validasi login
    if ($username === $admin_user && $password === $admin_pass) {
        $_SESSION['login'] = true;
        $_SESSION['username'] = $username;
        header("Location: tampil-data.php");
        exit;
    } else {
        header("Location: login.php?error=1");
        exit;
    }
}
?>

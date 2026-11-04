<?php
require 'koneksi.php';

// Tentukan username dan password admin baru
$username = 'admin';
$password_asli = '12345'; // Password yang nanti diketik saat login

// Enkripsi password agar aman
$password_hashed = password_hash($password_asli, PASSWORD_DEFAULT);

try {
    $sql = "INSERT INTO users (username, password) VALUES (:username, :password)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':username' => $username,
        ':password' => $password_hashed
    ]);

    echo "<h3>User admin berhasil ditambahkan ke database Supabase!</h3>";
    echo "<p>Silakan hapus file <b>tambah-user.php</b> ini demi keamanan setelah digunakan.</p>";
    echo "<a href='login.php'>Ke Halaman Login</a>";

} catch (PDOException $e) {
    echo "Gagal menambah user (mungkin username sudah ada): " . $e->getMessage();
}
?>

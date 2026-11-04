<?php
require 'koneksi.php';
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Tangkap data baru dari form edit
    $id          = $_POST['id'];
    $nama_produk = $_POST['nama_produk'];
    $harga       = $_POST['harga'];
    $stok        = $_POST['stok'];

    try {
        // Tulis Query UPDATE PostgreSQL
        $sql = "UPDATE produk SET nama_produk = :nama, harga = :harga, stok = :stok WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        
        // Eksekusi query
        $stmt->execute([
            ':nama'   => $nama_produk,
            ':harga'  => $harga,
            ':stok'   => $stok,
            ':id'     => $id
        ]);

        // Setelah sukses mengedit, otomatis kembali ke halaman tampil-data.php
        header("Location: tampil-data.php");
        exit;

    } catch (PDOException $e) {
        echo "<h1>Gagal Mengubah Data!</h1>";
        echo "<p>Penyebab error: " . $e->getMessage() . "</p>";
    }
} else {
    header("Location: tampil-data.php");
    exit;
}
?>

<?php
// 1. Hubungkan dengan file koneksi
require 'koneksi.php';

// 2. Pastikan file ini diakses setelah user menekan tombol submit (metode POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 3. Tangkap data dari form HTML
    $nama_produk = $_POST['nama_produk'];
    $harga       = $_POST['harga'];
    $stok        = $_POST['stok'];

    try {
        // 4. Siapkan Query INSERT dengan placeholder
        $sql = "INSERT INTO produk (nama_produk, harga, stok) VALUES (:nama, :harga, :stok)";
        $stmt = $pdo->prepare($sql);
        
        // 5. Eksekusi query dengan mengikat data asli
        $stmt->execute([
            ':nama'  => $nama_produk,
            ':harga' => $harga,
            ':stok'  => $stok
        ]);

        // Jika berhasil, tampilkan pesan dan berikan tombol kembali
        echo "<h1>Data Berhasil Disimpan!</h1>";
        echo "<p>Produk <strong>$nama_produk</strong> telah ditambahkan ke database Supabase.</p>";
        echo "<a href='form-tambah.php'>&laquo; Kembali ke Form</a>";

    } catch (PDOException $e) {
        echo "<h1>Gagal Menyimpan Data!</h1>";
        echo "<p>Penyebab error: " . $e->getMessage() . "</p>";
    }

} else {
    // Jika file ini diakses langsung tanpa lewat form, arahkan kembali ke form
    header("Location: form-tambah.php");
    exit;
}
?>
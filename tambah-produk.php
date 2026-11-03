<?php
// 1. Hubungkan kembali dengan file koneksi
require 'koneksi.php';

try {
    // 2. Siapkan data produk yang ingin dimasukkan
    $nama_produk = "Laptop ASUS Vivobook";
    $harga       = 8500000;
    $stok        = 10;

    // 3. Tulis Query INSERT dengan placeholder (? atau :nama) demi keamanan
    $sql = "INSERT INTO produk (nama_produk, harga, stok) VALUES (:nama, :harga, :stok)";
    
    // 4. Siapkan statement (Prepare)
    $stmt = $pdo->prepare($sql);
    
    // 5. Eksekusi dengan memasukkan data asli
    $stmt->execute([
        ':nama'  => $nama_produk,
        ':harga' => $harga,
        ':stok'  => $stok
    ]);

    echo "<h1>Sukses Menambahkan Data!</h1>";
    echo "<p>Produk <strong>$nama_produk</strong> berhasil disimpan ke cloud database Supabase.</p>";

} catch (PDOException $e) {
    echo "<h1>Gagal Menambahkan Data!</h1>";
    echo "<p>Penyebab error: " . $e->getMessage() . "</p>";
}
?>
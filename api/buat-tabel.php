<?php
// 1. Hubungkan dengan file koneksi yang sudah sukses sebelumnya
require 'koneksi.php';

try {
    // 2. Tulis Query SQL PostgreSQL untuk membuat tabel 'produk'
    // Menggunakan SERIAL agar ID otomatis bertambah (Auto Increment)
    $sql = "CREATE TABLE IF NOT EXISTS produk (
        id SERIAL PRIMARY KEY,
        nama_produk VARCHAR(100) NOT NULL,
        harga INT NOT NULL,
        stok INT DEFAULT 0,
        dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    // 3. Eksekusi query menggunakan method exec() karena tidak mengembalikan data (result set)
    $pdo->exec($sql);
    
    echo "<h1>Hebat! Tabel 'produk' Berhasil Dibuat.</h1>";
    echo "<p>Tabel baru sekarang sudah tersedia di database Supabase Anda.</p>";

} catch (PDOException $e) {
    echo "<h1>Gagal Membuat Tabel!</h1>";
    echo "<p>Penyebab error: " . $e->getMessage() . "</p>";
}
?>

<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Produk Baru</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"] { width: 300px; padding: 8px; }
        button { padding: 10px 15px; background-color: #24b47e; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #1c8c61; }
    </style>
</head>
<body>

    <h2>Form Tambah Produk Supabase</h2>
    
    <!-- Form ini akan mengirimkan data ke file proses-tambah.php menggunakan metode POST -->
    <form action="proses-tambah.php" method="POST">
        
        <div class="form-group">
            <label for="nama_produk">Nama Produk</label>
            <input type="text" id="nama_produk" name="nama_produk" required placeholder="Contoh: Mouse Logitech">
        </div>

        <div class="form-group">
            <label for="harga">Harga (Rupiah)</label>
            <input type="number" id="harga" name="harga" required placeholder="Contoh: 150000">
        </div>

        <div class="form-group">
            <label for="stok">Jumlah Stok</label>
            <input type="number" id="stok" name="stok" required placeholder="Contoh: 25">
        </div>

        <button type="submit">Simpan Produk</button>
    </form>

</body>
</html>

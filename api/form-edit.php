<?php
require 'koneksi.php';

// Pastikan ada parameter ID di URL
if (!isset($_GET['id'])) {
    header("Location: tampil-data.php");
    exit;
}

$id = $_GET['id'];

try {
    // Ambil data produk yang sesuai dengan ID tersebut
    $sql = "SELECT * FROM produk WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $produk = $stmt->fetch();

    // Jika produk tidak ditemukan di database
    if (!$produk) {
        echo "Produk tidak ditemukan!";
        exit;
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Produk</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"] { width: 300px; padding: 8px; }
        button { padding: 10px 15px; background-color: #f0ad4e; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #ec971f; }
        .kembali { margin-left: 10px; color: #555; text-decoration: none; }
    </style>
</head>
<body>

    <h2>Form Edit Produk</h2>
    
    <form action="proses-edit.php" method="POST">
        <!-- Input ID disembunyikan (hidden) karena dibutuhkan untuk query UPDATE di file proses -->
        <input type="hidden" name="id" value="<?= $produk['id']; ?>">
        
        <div class="form-group">
            <label for="nama_produk">Nama Produk</label>
            <input type="text" id="nama_produk" name="nama_produk" value="<?= htmlspecialchars($produk['nama_produk']); ?>" required>
        </div>

        <div class="form-group">
            <label for="harga">Harga (Rupiah)</label>
            <input type="number" id="harga" name="harga" value="<?= $produk['harga']; ?>" required>
        </div>

        <div class="form-group">
            <label for="stok">Jumlah Stok</label>
            <input type="number" id="stok" name="stok" value="<?= $produk['stok']; ?>" required>
        </div>

        <button type="submit">Update Produk</button>
        <a href="tampil-data.php" class="kembali">Batal</a>
    </form>

</body>
</html>

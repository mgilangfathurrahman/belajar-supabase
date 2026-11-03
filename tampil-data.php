<?php
// 1. Hubungkan dengan file koneksi
require 'koneksi.php';

try {
    // 2. Tulis query SELECT untuk mengambil semua data produk, diurutkan dari yang terbaru
    $sql = "SELECT * FROM produk ORDER BY id DESC";
    $stmt = $pdo->query($sql);
    
    // 3. Ambil semua baris data
    $semua_produk = $stmt->fetchAll();

} catch (PDOException $e) {
    echo "Gagal mengambil data: " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Produk</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #24b47e; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .btn { padding: 6px 12px; text-decoration: none; color: white; border-radius: 4px; font-size: 14px; }
        .btn-tambah { background-color: #24b47e; padding: 10px 15px; }
        .btn-edit { background-color: #f0ad4e; }
    </style>
</head>
<body>

    <h2>Daftar Produk di Supabase</h2>
    <a href="form-tambah.php" class="btn btn-tambah">Tambah Produk Baru</a>
    <br><br>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Produk</th>
                <th>Harga</th>
                <th>Stok</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($semua_produk) > 0): ?>
                <?php foreach ($semua_produk as $produk): ?>
                    <tr>
                        <td><?= $produk['id']; ?></td>
                        <td><?= htmlspecialchars($produk['nama_produk']); ?></td>
                        <td>Rp <?= number_format($produk['harga'], 0, ',', '.'); ?></td>
                        <td><?= $produk['stok']; ?></td>
                        <td>
                            <!-- Mengirimkan ID produk lewat URL ke form edit -->
                            <a href="form-edit.php?id=<?= $produk['id']; ?>" class="btn btn-edit">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center;">Belum ada data produk.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
<?php
session_start();
require 'koneksi.php';

try {
    $stmt = $pdo->query("SELECT * FROM produk ORDER BY id DESC");
    $produk_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        .btn { padding: 6px 12px; text-decoration: none; color: white; border-radius: 4px; font-size: 14px; display: inline-block; margin-right: 4px; }
        .btn-tambah { background-color: #24b47e; padding: 10px 15px; }
        .btn-edit { background-color: #f0ad4e; }
        .btn-hapus { background-color: #d9534f; }
        .btn-auth { background-color: #333; float: right; }
    </style>
</head>
<body>
<a href="login.php" class="btn btn-auth" style="background-color: #2980b9;">Login Admin</a>
    <!-- Tombol Login / Logout di sudut kanan atas -->
    <?php if (isset($_SESSION['login'])): ?>
        <a href="logout.php" class="btn btn-auth">Logout (Admin)</a>
    <?php else: ?>
        <a href="login.php" class="btn btn-auth" style="background-color: #2980b9;">Login Admin</a>
    <?php endif; ?>

    <h2>Daftar Produk di Supabase</h2>

    <!-- Tombol Tambah hanya muncul jika sudah login -->
    <?php if (isset($_SESSION['login'])): ?>
        <a href="form-tambah.php" class="btn btn-tambah">Tambah Produk Baru</a>
        <br><br>
    <?php else: ?>
        <p style="color: #666; font-style: italic;">*Anda sedang berada dalam mode lihat (guest). Login sebagai admin untuk menambah/mengubah data.</p>
    <?php endif; ?>

<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Nama Produk</th>
            <th>Harga</th>
            <th>Stok</th>
            <?php if (isset($_SESSION['login'])): ?>
                <th>Aksi</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1; 
        foreach ($produk_list as $row): 
        ?>
        <tr>
            <td><?php echo $no++; ?></td> 
            <td><?php echo htmlspecialchars($row['nama_produk']); ?></td>
            <td>Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
            <td><?php echo $row['stok']; ?></td>
            
            <!-- Kolom Aksi hanya muncul jika sudah login -->
            <?php if (isset($_SESSION['login'])): ?>
            <td>
                <a href="form-edit.php?id=<?php echo $row['id']; ?>" class="btn-edit btn">Edit</a>
                <a href="hapus-data.php?id=<?php echo $row['id']; ?>" 
                   class="btn-hapus btn" 
                   onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">Hapus</a>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>

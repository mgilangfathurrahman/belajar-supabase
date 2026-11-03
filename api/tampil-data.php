<?php
// 1. Hubungkan dengan file koneksi
require 'koneksi.php';

try {
    // 2. Tulis query SELECT untuk mengambil semua data produk, diurutkan dari yang terbaru
$stmt = $pdo->query("SELECT * FROM produk ORDER BY id DESC");
$produk_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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

<table border="1" cellpadding="10" cellspacing="0">
    <thead>
        <tr>
            <th>No</th> <!-- Kita ubah label judul kolomnya menjadi No atau tetap ID versi nomor urut -->
            <th>Nama Produk</th>
            <th>Harga</th>
            <th>Stok</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1; // 1. Mulai nomor urut dari angka 1
        foreach ($produk_list as $row): 
        ?>
        <tr>
            <!-- 2. Tampilkan variabel $no, lalu naikkan nilainya dengan $no++ -->
            <td><?php echo $no++; ?></td> 
            <td><?php echo htmlspecialchars($row['nama_produk']); ?></td>
            <td>Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
            <td><?php echo $row['stok']; ?></td>
            <td>
                <a href="form-edit.php?id=<?php echo $row['id']; ?>" class="btn-edit">Edit</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>

<?php
if (!isset($_COOKIE['admin_logged_in']) || $_COOKIE['admin_logged_in'] !== 'true') {
    header("Location: login.php");
    exit;
}

require 'koneksi.php';
require_once __DIR__ . '/export_helpers.php';

$admin_nama = $_COOKIE['admin_nama'] ?? ($_COOKIE['admin_username'] ?? 'Admin');
$admin_role = $_COOKIE['admin_role'] ?? 'admin';
$is_super   = ($admin_role === 'superadmin');

$pesan = "";
$edit_data = null;

// 1. Proses Hapus Produk (Khusus Superadmin)
if (isset($_GET['hapus'])) {
    if (!$is_super) {
        $pesan = "Akses ditolak! Hanya Superadmin yang dapat menghapus produk.";
    } else {
        $id_hapus = (int) $_GET['hapus'];
        try {
            $stmt = $pdo->prepare("DELETE FROM produk WHERE id = :id");
            $stmt->execute([':id' => $id_hapus]);
            header("Location: produk.php?status=dihapus");
            exit;
        } catch (PDOException $e) {
            $pesan = "Gagal menghapus produk (sudah memiliki riwayat transaksi): " . $e->getMessage();
        }
    }
}

// 2. Ambil Data Jika Sedang Mode Edit
if (isset($_GET['edit'])) {
    $id_edit = (int) $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM produk WHERE id = :id");
    $stmt->execute([':id' => $id_edit]);
    $edit_data = $stmt->fetch();
}

// 3. Proses Simpan (Tambah Baru atau Update Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_produk = trim($_POST['nama_produk']);
    $harga       = (int) $_POST['harga'];
    $stok        = (int) $_POST['stok'];

    try {
        if (!empty($_POST['id_edit'])) {
            $id_edit = (int) $_POST['id_edit'];
            $stmt = $pdo->prepare("UPDATE produk SET nama_produk = :nama, harga = :harga, stok = :stok WHERE id = :id");
            $stmt->execute([
                ':nama'  => $nama_produk,
                ':harga' => $harga,
                ':stok'  => $stok,
                ':id'    => $id_edit
            ]);
            header("Location: produk.php?status=diubah");
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO produk (nama_produk, harga, stok, dibuat_oleh) VALUES (:nama, :harga, :stok, :oleh)");
            $stmt->execute([
                ':nama'  => $nama_produk,
                ':harga' => $harga,
                ':stok'  => $stok,
                ':oleh'  => $admin_nama
            ]);
            header("Location: produk.php?status=sukses");
            exit;
        }
    } catch (PDOException $e) {
        $pesan = "Terjadi kesalahan: " . $e->getMessage();
    }
}

$produk_list = $pdo->query("SELECT * FROM produk ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - Toko Gilang</title>
    <style>
        body { font-family: sans-serif; margin: 20px 40px; }
        nav { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        nav a { margin-right: 15px; text-decoration: none; font-weight: bold; color: #24b47e; }
        .user-badge { background: #e6f7f0; color: #1c8c61; padding: 5px 10px; border-radius: 5px; font-size: 13px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #24b47e; color: white; }
        form input, button { padding: 8px; margin-right: 5px; }
        button { background: #24b47e; color: white; border: none; cursor: pointer; font-weight: bold; border-radius: 4px; }
        .btn-batal { background: #888; color: white; padding: 8px 12px; text-decoration: none; font-size: 13px; border-radius: 4px; }
        .btn-aksi { padding: 5px 10px; text-decoration: none; color: white; border-radius: 4px; font-size: 12px; margin-right: 4px; }
        .btn-edit { background: #f0ad4e; }
        .btn-hapus { background: #d9534f; }
        .alert-ok { background: #e6f7f0; color: #24b47e; padding: 10px; margin: 15px 0; border-radius: 5px; }
        .alert-err { background: #ffe6e6; color: #d93025; padding: 10px; margin: 15px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <nav>
        <div>
            <a href="laporan.php">Laporan Real-Time</a>
            <a href="produk.php">Produk</a>
            <a href="pesanan.php">Pesanan</a>
            <?php if ($is_super): ?>
                <a href="kelola-admin.php">Kelola Admin</a>
            <?php endif; ?>
            <a href="index.php" target="_blank">Lihat Katalog</a>
            <a href="logout.php" style="color:red;">Logout</a>
        </div>
        <div class="user-badge">👤 <?= htmlspecialchars($admin_nama) ?> (<?= strtoupper(htmlspecialchars($admin_role)) ?>)</div>
    </nav>
    <hr>

    <?php if (isset($_GET['status'])): ?>
        <div class="alert-ok">
            <?php
                if ($_GET['status'] === 'sukses') echo "Produk baru berhasil ditambahkan!";
                if ($_GET['status'] === 'diubah') echo "Data produk berhasil diperbarui!";
                if ($_GET['status'] === 'dihapus') echo "Produk berhasil dihapus!";
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($pesan)): ?>
        <div class="alert-err"><?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>

    <form method="POST" action="produk.php">
        <input type="hidden" name="id_edit" value="<?= $edit_data ? $edit_data['id'] : '' ?>">
        <input type="text" name="nama_produk" placeholder="Nama Produk" value="<?= $edit_data ? htmlspecialchars($edit_data['nama_produk']) : '' ?>" required>
        <input type="number" name="harga" placeholder="Harga (Rp)" min="0" value="<?= $edit_data ? $edit_data['harga'] : '' ?>" required>
        <input type="number" name="stok" placeholder="Stok" min="0" value="<?= $edit_data ? $edit_data['stok'] : '' ?>" required>
        <button type="submit" style="<?= $edit_data ? 'background:#f0ad4e;' : '' ?>">
            <?= $edit_data ? 'Simpan Perubahan' : 'Tambah Produk' ?>
        </button>
        <?php if ($edit_data): ?>
            <a href="produk.php" class="btn-batal">Batal</a>
        <?php endif; ?>
    </form>

     <h3>Daftar Stok Produk</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Produk</th>
                <th>Harga</th>
                <th>Stok</th>
                <th>Dibuat Oleh</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($produk_list) > 0): ?>
                <?php $no = 1; ?>
                <?php foreach ($produk_list as $p): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($p['nama_produk']) ?></td>
                    <td>Rp <?= number_format($p['harga'], 0, ',', '.') ?></td>
                    <td><?= $p['stok'] ?></td>
                    <td><strong><?= htmlspecialchars($p['dibuat_oleh'] ?? '-') ?></strong></td>
                    <td>
                        <a href="produk.php?edit=<?= $p['id'] ?>" class="btn-aksi btn-edit">Edit</a>
                        <?php if ($is_super): ?>
                            <a href="produk.php?hapus=<?= $p['id'] ?>" class="btn-aksi btn-hapus" onclick="return confirm('Yakin ingin menghapus produk ini?')">Hapus</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center;">Belum ada data produk.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>

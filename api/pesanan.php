<?php
if (!isset($_COOKIE['admin_logged_in']) || $_COOKIE['admin_logged_in'] !== 'true') {
    header("Location: login.php");
    exit;
}

require 'koneksi.php';

$pesan = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pembeli = trim($_POST['nama_pembeli']);
    $produk_id    = (int) $_POST['produk_id'];
    $jumlah       = (int) $_POST['jumlah'];

    try {
        $stmt = $pdo->prepare("SELECT harga, stok FROM produk WHERE id = :id");
        $stmt->execute([':id' => $produk_id]);
        $produk = $stmt->fetch();

        if ($produk && $produk['stok'] >= $jumlah && $jumlah > 0) {
            $total_harga = $produk['harga'] * $jumlah;

            $ins = $pdo->prepare("INSERT INTO pesanan (nama_pembeli, produk_id, jumlah, total_harga) VALUES (:pembeli, :pid, :jml, :total)");
            $ins->execute([
                ':pembeli' => $nama_pembeli,
                ':pid'     => $produk_id,
                ':jml'     => $jumlah,
                ':total'   => $total_harga
            ]);

            $upd = $pdo->prepare("UPDATE produk SET stok = stok - :jml WHERE id = :pid");
            $upd->execute([
                ':jml' => $jumlah,
                ':pid' => $produk_id
            ]);

            header("Location: pesanan.php?status=sukses");
            exit;
        } else {
            $pesan = "Stok tidak mencukupi atau jumlah tidak valid!";
        }
    } catch (PDOException $e) {
        $pesan = "Gagal menyimpan pesanan: " . $e->getMessage();
    }
}

try {
    $produk_tersedia = $pdo->query("SELECT * FROM produk WHERE stok > 0 ORDER BY nama_produk ASC")->fetchAll();
    
    $sql_pesanan = "SELECT pesanan.id, pesanan.nama_pembeli, produk.nama_produk, pesanan.jumlah, pesanan.total_harga, pesanan.tanggal 
                    FROM pesanan 
                    JOIN produk ON pesanan.produk_id = produk.id 
                    ORDER BY pesanan.id DESC";
    $pesanan_list = $pdo->query($sql_pesanan)->fetchAll();
} catch (PDOException $e) {
    die("Error Query Database: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - Toko Gilang</title>
    <style>
        body { font-family: sans-serif; margin: 20px 40px; }
        nav a { margin-right: 15px; text-decoration: none; font-weight: bold; color: #24b47e; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #24b47e; color: white; }
        form input, select, button { padding: 8px; margin-right: 5px; }
        button { background: #24b47e; color: white; border: none; cursor: pointer; font-weight: bold; }
        .alert-ok { background: #e6f7f0; color: #24b47e; padding: 10px; margin: 15px 0; border-radius: 5px; }
        .alert-err { background: #ffe6e6; color: #d93025; padding: 10px; margin: 15px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <nav>
        <a href="laporan.php">Laporan Real-Time</a>
        <a href="produk.php">Produk</a>
        <a href="pesanan.php">Pesanan</a>
        <a href="logout.php" style="color:red;">Logout</a>
    </nav>
    <hr>

    <h2>Catat Pesanan Baru</h2>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'sukses'): ?>
        <div class="alert-ok">Pesanan berhasil dicatat dan stok produk otomatis berkurang!</div>
    <?php endif; ?>

    <?php if (!empty($pesan)): ?>
        <div class="alert-err"><?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="text" name="nama_pembeli" placeholder="Nama Pembeli" required>
        <select name="produk_id" required>
            <option value="">-- Pilih Produk --</option>
            <?php foreach ($produk_tersedia as $pt): ?>
                <option value="<?= $pt['id'] ?>">
                    <?= htmlspecialchars($pt['nama_produk']) ?> - Rp <?= number_format($pt['harga'], 0, ',', '.') ?> (Stok: <?= $pt['stok'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="jumlah" placeholder="Jumlah" min="1" required style="width: 100px;">
        <button type="submit">Simpan Pesanan</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Pembeli</th>
                <th>Produk</th>
                <th>Jumlah</th>
                <th>Total Harga</th>
                <th>Waktu</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($pesanan_list) > 0): ?>
                <?php foreach ($pesanan_list as $ps): ?>
                <tr>
                    <td><?= $ps['id'] ?></td>
                    <td><?= htmlspecialchars($ps['nama_pembeli']) ?></td>
                    <td><?= htmlspecialchars($ps['nama_produk']) ?></td>
                    <td><?= $ps['jumlah'] ?></td>
                    <td>Rp <?= number_format($ps['total_harga'], 0, ',', '.') ?></td>
                    <td><?= $ps['tanggal'] ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center;">Belum ada riwayat pesanan.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>

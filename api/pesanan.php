<?php
if (!isset($_COOKIE['admin_logged_in']) || $_COOKIE['admin_logged_in'] !== 'true') {
    header("Location: login.php");
    exit;
}

require 'koneksi.php';

$pesan = "";

// 1. Proses Batalkan / Hapus Pesanan & Kembalikan Stok
if (isset($_GET['batal'])) {
    $id_pesanan = (int) $_GET['batal'];
    try {
        $pdo->beginTransaction();

        // Ambil detail produk dan jumlah dari pesanan yang akan dibatalkan
        $stmt = $pdo->prepare("SELECT produk_id, jumlah FROM pesanan WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $id_pesanan]);
        $data_pesanan = $stmt->fetch();

        if ($data_pesanan) {
            // Kembalikan stok ke tabel produk
            $upd = $pdo->prepare("UPDATE produk SET stok = stok + :jml WHERE id = :pid");
            $upd->execute([
                ':jml' => $data_pesanan['jumlah'],
                ':pid' => $data_pesanan['produk_id']
            ]);

            // Hapus data dari tabel pesanan
            $del = $pdo->prepare("DELETE FROM pesanan WHERE id = :id");
            $del->execute([':id' => $id_pesanan]);

            $pdo->commit();
            header("Location: pesanan.php?status=batal");
            exit;
        } else {
            $pdo->rollBack();
            $pesan = "Data pesanan tidak ditemukan.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pesan = "Gagal membatalkan pesanan: " . $e->getMessage();
    }
}

// 2. Proses Catat Pesanan Baru oleh Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pembeli = trim($_POST['nama_pembeli']);
    $no_telp      = trim($_POST['no_telp'] ?? '-');
    $email        = trim($_POST['email'] ?? '-');
    $alamat       = trim($_POST['alamat'] ?? '-');
    $produk_id    = (int) $_POST['produk_id'];
    $jumlah       = (int) $_POST['jumlah'];

    try {
        $stmt = $pdo->prepare("SELECT harga, stok FROM produk WHERE id = :id");
        $stmt->execute([':id' => $produk_id]);
        $produk = $stmt->fetch();

        if ($produk && $produk['stok'] >= $jumlah && $jumlah > 0) {
            $total_harga = $produk['harga'] * $jumlah;

            $ins = $pdo->prepare("INSERT INTO pesanan (nama_pembeli, no_telp, email, alamat, produk_id, jumlah, total_harga) 
                                  VALUES (:pembeli, :telp, :email, :alamat, :pid, :jml, :total)");
            $ins->execute([
                ':pembeli' => $nama_pembeli,
                ':telp'    => $no_telp,
                ':email'   => $email,
                ':alamat'  => $alamat,
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

// 3. Ambil Daftar Produk & Riwayat Pesanan
try {
    $produk_tersedia = $pdo->query("SELECT * FROM produk WHERE stok > 0 ORDER BY nama_produk ASC")->fetchAll();
    
    $sql_pesanan = "SELECT pesanan.id, pesanan.nama_pembeli, pesanan.no_telp, pesanan.email, pesanan.alamat, 
                           produk.nama_produk, pesanan.jumlah, pesanan.total_harga, pesanan.tanggal 
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
        .table-wrapper { overflow-x: auto; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; font-size: 14px; }
        th { background: #24b47e; color: white; }
        .form-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
        .form-grid input, .form-grid select, .form-grid button { padding: 8px; font-size: 14px; }
        button { background: #24b47e; color: white; border: none; cursor: pointer; font-weight: bold; border-radius: 4px; padding: 8px 15px; }
        button:hover { background: #1c8c61; }
        .btn-batal { background: #d9534f; color: white; padding: 6px 10px; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; }
        .btn-batal:hover { background: #c9302c; }
        .alert-ok { background: #e6f7f0; color: #24b47e; padding: 10px; margin: 15px 0; border-radius: 5px; font-weight: bold; }
        .alert-err { background: #ffe6e6; color: #d93025; padding: 10px; margin: 15px 0; border-radius: 5px; }
        .kontak-info { font-size: 12px; color: #555; margin-top: 4px; }
    </style>
</head>
<body>
    <nav>
        <a href="laporan.php">Laporan Real-Time</a>
        <a href="produk.php">Produk</a>
        <a href="pesanan.php">Pesanan</a>
        <a href="index.php" target="_blank">Lihat Katalog</a>
        <a href="logout.php" style="color:red;">Logout</a>
    </nav>
    <hr>

    <h2>Catat Pesanan Manual (Admin)</h2>

    <?php if (isset($_GET['status'])): ?>
        <div class="alert-ok">
            <?php
                if ($_GET['status'] === 'sukses') echo "Pesanan berhasil dicatat dan stok produk otomatis berkurang!";
                if ($_GET['status'] === 'batal') echo "Pesanan berhasil dibatalkan dan stok produk telah dikembalikan!";
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($pesan)): ?>
        <div class="alert-err"><?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-grid">
            <input type="text" name="nama_pembeli" placeholder="Nama Pembeli" required>
            <input type="text" name="no_telp" placeholder="No. Telp / WA">
            <input type="email" name="email" placeholder="Email">
            <input type="text" name="alamat" placeholder="Alamat Pengiriman" style="width: 250px;">
        </div>
        <div class="form-grid">
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
        </div>
    </form>

    <h3>Daftar Semua Pesanan Masuk</h3>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Data Pembeli & Kontak</th>
                    <th>Alamat Pengiriman</th>
                    <th>Produk</th>
                    <th>Qty</th>
                    <th>Total Harga</th>
                    <th>Waktu</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($pesanan_list) > 0): ?>
                    <?php foreach ($pesanan_list as $ps): ?>
                    <tr>
                        <td><?= $ps['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($ps['nama_pembeli']) ?></strong>
                            <div class="kontak-info">
                                📞 <?= htmlspecialchars($ps['no_telp'] ?: '-') ?><br>
                                ✉️ <?= htmlspecialchars($ps['email'] ?: '-') ?>
                            </div>
                        </td>
                        <td><?= nl2br(htmlspecialchars($ps['alamat'] ?: '-')) ?></td>
                        <td><?= htmlspecialchars($ps['nama_produk']) ?></td>
                        <td><?= $ps['jumlah'] ?></td>
                        <td><strong>Rp <?= number_format($ps['total_harga'], 0, ',', '.') ?></strong></td>
                        <td><?= $ps['tanggal'] ?></td>
                        <td>
                            <a href="pesanan.php?batal=<?= $ps['id'] ?>" class="btn-batal" onclick="return confirm('Batalkan pesanan ini? Stok produk akan otomatis dikembalikan.')">Batalkan</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;">Belum ada riwayat pesanan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

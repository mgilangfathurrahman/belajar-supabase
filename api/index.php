<?php
require 'koneksi.php';

$pesan_sukses = "";
$pesan_error  = "";

// Proses Pemesanan dari Pengunjung/Pembeli
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pembeli = trim($_POST['nama_pembeli']);
    $produk_id    = (int) $_POST['produk_id'];
    $jumlah       = (int) $_POST['jumlah'];

    try {
        $stmt = $pdo->prepare("SELECT nama_produk, harga, stok FROM produk WHERE id = :id");
        $stmt->execute([':id' => $produk_id]);
        $produk = $stmt->fetch();

        if ($produk && $produk['stok'] >= $jumlah && $jumlah > 0) {
            $total_harga = $produk['harga'] * $jumlah;

            // Simpan ke tabel pesanan
            $ins = $pdo->prepare("INSERT INTO pesanan (nama_pembeli, produk_id, jumlah, total_harga) VALUES (:pembeli, :pid, :jml, :total)");
            $ins->execute([
                ':pembeli' => $nama_pembeli,
                ':pid'     => $produk_id,
                ':jml'     => $jumlah,
                ':total'   => $total_harga
            ]);

            // Kurangi stok produk
            $upd = $pdo->prepare("UPDATE produk SET stok = stok - :jml WHERE id = :pid");
            $upd->execute([
                ':jml' => $jumlah,
                ':pid' => $produk_id
            ]);

            $pesan_sukses = "Terima kasih $nama_pembeli! Pesanan {$produk['nama_produk']} ($jumlah unit) senilai Rp " . number_format($total_harga, 0, ',', '.') . " berhasil dibuat.";
        } else {
            $pesan_error = "Maaf, stok produk tidak mencukupi untuk jumlah yang diminta.";
        }
    } catch (PDOException $e) {
        $pesan_error = "Terjadi kesalahan sistem: " . $e->getMessage();
    }
}

// Ambil produk yang stoknya masih tersedia
$produk_tersedia = $pdo->query("SELECT * FROM produk WHERE stok > 0 ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Toko Gilang</title>
    <style>
        body { font-family: sans-serif; margin: 20px 40px; background: #f9fafb; }
        header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn-login { background: #24b47e; color: white; padding: 10px 18px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .btn-login:hover { background: #1c8c61; }
        .alert-ok { background: #e6f7f0; color: #1c8c61; padding: 15px; border-radius: 6px; margin-top: 20px; border-left: 5px solid #24b47e; font-weight: bold; }
        .alert-err { background: #ffe6e6; color: #d93025; padding: 15px; border-radius: 6px; margin-top: 20px; border-left: 5px solid #d93025; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; margin-top: 25px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.08); border-top: 4px solid #24b47e; display: flex; flex-direction: column; justify-content: space-between; }
        .card h3 { margin: 0 0 8px; color: #333; }
        .harga { font-size: 18px; font-weight: bold; color: #24b47e; margin: 8px 0; }
        .stok { font-size: 13px; color: #666; background: #eee; padding: 4px 8px; border-radius: 4px; display: inline-block; margin-bottom: 15px; }
        .form-beli input { width: 100%; padding: 8px; margin-bottom: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 13px; }
        .btn-beli { width: 100%; padding: 9px; background: #24b47e; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px; }
        .btn-beli:hover { background: #1c8c61; }
    </style>
</head>
<body>

    <header>
        <h2>Katalog Toko Gilang</h2>
        <?php if (isset($_COOKIE['admin_logged_in']) && $_COOKIE['admin_logged_in'] === 'true'): ?>
            <a href="laporan.php" class="btn-login">Ke Dashboard Admin</a>
        <?php else: ?>
            <a href="login.php" class="btn-login">Login Admin</a>
        <?php endif; ?>
    </header>

    <?php if (!empty($pesan_sukses)): ?>
        <div class="alert-ok"><?= htmlspecialchars($pesan_sukses) ?></div>
    <?php endif; ?>

    <?php if (!empty($pesan_error)): ?>
        <div class="alert-err"><?= htmlspecialchars($pesan_error) ?></div>
    <?php endif; ?>

    <div class="grid">
        <?php if (count($produk_tersedia) > 0): ?>
            <?php foreach ($produk_tersedia as $p): ?>
                <div class="card">
                    <div>
                        <h3><?= htmlspecialchars($p['nama_produk']) ?></h3>
                        <div class="harga">Rp <?= number_format($p['harga'], 0, ',', '.') ?></div>
                        <div class="stok">Tersedia: <?= $p['stok'] ?> unit</div>
                    </div>

                    <form method="POST" action="index.php" class="form-beli">
                        <input type="hidden" name="produk_id" value="<?= $p['id'] ?>">
                        <input type="text" name="nama_pembeli" placeholder="Nama Anda" required>
                        <input type="number" name="jumlah" placeholder="Jumlah Beli" min="1" max="<?= $p['stok'] ?>" required>
                        <button type="submit" class="btn-beli">Beli Sekarang</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Maaf, saat ini belum ada produk yang tersedia.</p>
        <?php endif; ?>
    </div>

</body>
</html>

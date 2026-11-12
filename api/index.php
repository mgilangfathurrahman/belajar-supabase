<?php
require 'koneksi.php';

$pesan_sukses = "";
$pesan_error  = "";

// Proses Pemesanan Multi-Produk
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pembeli = trim($_POST['nama_pembeli']);
    $alamat       = trim($_POST['alamat']);
    $email        = trim($_POST['email']);
    $no_telp      = trim($_POST['no_telp']);
    
    $produk_dipilih = $_POST['pilih_produk'] ?? [];
    $jumlah_beli    = $_POST['jumlah'] ?? [];

    if (empty($produk_dipilih)) {
        $pesan_error = "Silakan centang minimal satu produk yang ingin dipesan!";
    } else {
        try {
            $pdo->beginTransaction();
            $total_seluruh_belanja = 0;
            $item_berhasil = 0;

            foreach ($produk_dipilih as $pid) {
                $pid = (int) $pid;
                $jml = isset($jumlah_beli[$pid]) ? (int) $jumlah_beli[$pid] : 0;

                if ($jml > 0) {
                    $stmt = $pdo->prepare("SELECT nama_produk, harga, stok FROM produk WHERE id = :id FOR UPDATE");
                    $stmt->execute([':id' => $pid]);
                    $produk = $stmt->fetch();

                    if ($produk && $produk['stok'] >= $jml) {
                        $subtotal = $produk['harga'] * $jml;
                        $total_seluruh_belanja += $subtotal;

                        // Simpan detail pesanan ke database
                        $ins = $pdo->prepare("INSERT INTO pesanan (nama_pembeli, alamat, email, no_telp, produk_id, jumlah, total_harga) 
                                              VALUES (:nama, :alamat, :email, :telp, :pid, :jml, :total)");
                        $ins->execute([
                            ':nama'   => $nama_pembeli,
                            ':alamat' => $alamat,
                            ':email'  => $email,
                            ':telp'   => $no_telp,
                            ':pid'    => $pid,
                            ':jml'    => $jml,
                            ':total'  => $subtotal
                        ]);

                        // Kurangi stok produk
                        $upd = $pdo->prepare("UPDATE produk SET stok = stok - :jml WHERE id = :pid");
                        $upd->execute([':jml' => $jml, ':pid' => $pid]);

                        $item_berhasil++;
                    } else {
                        throw new Exception("Stok untuk produk " . ($produk['nama_produk'] ?? '') . " tidak mencukupi.");
                    }
                }
            }

            if ($item_berhasil > 0) {
                $pdo->commit();
                $pesan_sukses = "Terima kasih $nama_pembeli! Pesanan Anda ($item_berhasil jenis produk) dengan total Rp " . number_format($total_seluruh_belanja, 0, ',', '.') . " berhasil diproses.";
            } else {
                $pdo->rollBack();
                $pesan_error = "Jumlah pembelian produk yang dicentang tidak boleh 0.";
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pesan_error = "Gagal memproses pesanan: " . $e->getMessage();
        }
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
        body { font-family: sans-serif; margin: 20px auto; max-width: 850px; padding: 0 15px; background: #f4f7f6; }
        header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .btn-login { background: #24b47e; color: white; padding: 10px 18px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px; }
        .btn-login:hover { background: #1c8c61; }
        
        .alert-ok { background: #e6f7f0; color: #1c8c61; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 5px solid #24b47e; font-weight: bold; }
        .alert-err { background: #ffe6e6; color: #d93025; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 5px solid #d93025; }

        .section-box { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; }
        .section-box h3 { margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; color: #333; }

        /* Styling List Produk */
        .product-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 10px; border-bottom: 1px solid #eee; }
        .product-item:last-child { border-bottom: none; }
        .product-left { display: flex; align-items: center; gap: 15px; }
        .product-left input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; accent-color: #24b47e; }
        .product-info h4 { margin: 0 0 5px; font-size: 16px; color: #222; }
        .product-meta { font-size: 13px; color: #666; }
        .product-price { font-weight: bold; color: #24b47e; }

        /* Kontrol Jumlah (- 0 +) */
        .qty-control { display: none; align-items: center; gap: 8px; }
        .qty-control.active { display: flex; }
        .btn-qty { width: 32px; height: 32px; background: #24b47e; color: white; border: none; border-radius: 4px; font-size: 18px; font-weight: bold; cursor: pointer; }
        .btn-qty:hover { background: #1c8c61; }
        .input-qty { width: 50px; text-align: center; padding: 6px; border: 1px solid #ccc; border-radius: 4px; font-weight: bold; font-size: 15px; }

        /* Total Estimasi */
        .total-bar { display: flex; justify-content: space-between; align-items: center; background: #e6f7f0; padding: 15px 20px; border-radius: 6px; margin-top: 15px; font-size: 18px; font-weight: bold; color: #1c8c61; }

        /* Form Pembeli */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; font-size: 14px; color: #444; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 14px; font-family: inherit; }
        .btn-submit { width: 100%; padding: 14px; background: #24b47e; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .btn-submit:hover { background: #1c8c61; }
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

    <form method="POST" action="index.php">
        <!-- Bagian 1: List Pilihan Produk -->
        <div class="section-box">
            <h3>1. Pilih Produk yang Ingin Dibeli</h3>
            
            <?php if (count($produk_tersedia) > 0): ?>
                <?php foreach ($produk_tersedia as $p): ?>
                    <div class="product-item">
                        <div class="product-left">
                            <input type="checkbox" name="pilih_produk[]" value="<?= $p['id'] ?>" id="chk_<?= $p['id'] ?>" onchange="toggleQty(<?= $p['id'] ?>)">
                            <label for="chk_<?= $p['id'] ?>" class="product-info" style="cursor:pointer;">
                                <h4><?= htmlspecialchars($p['nama_produk']) ?></h4>
                                <div class="product-meta">
                                    <span class="product-price" data-harga="<?= $p['harga'] ?>" id="harga_<?= $p['id'] ?>">
                                        Rp <?= number_format($p['harga'], 0, ',', '.') ?>
                                    </span> 
                                    &bull; Tersedia: <?= $p['stok'] ?> unit
                                </div>
                            </label>
                        </div>

                        <!-- Kontrol Jumlah (Muncul saat dicentang) -->
                        <div class="qty-control" id="qty_box_<?= $p['id'] ?>">
                            <button type="button" class="btn-qty" onclick="ubahJumlah(<?= $p['id'] ?>, -1, <?= $p['stok'] ?>)">-</button>
                            <input type="number" name="jumlah[<?= $p['id'] ?>]" id="qty_<?= $p['id'] ?>" class="input-qty" value="0" min="0" max="<?= $p['stok'] ?>" readonly>
                            <button type="button" class="btn-qty" onclick="ubahJumlah(<?= $p['id'] ?>, 1, <?= $p['stok'] ?>)">+</button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="total-bar">
                    <span>Total Estimasi Pembayaran:</span>
                    <span id="total_estimasi">Rp 0</span>
                </div>
            <?php else: ?>
                <p>Maaf, saat ini belum ada produk yang tersedia.</p>
            <?php endif; ?>
        </div>

        <!-- Bagian 2: Form Data Pembeli -->
        <?php if (count($produk_tersedia) > 0): ?>
        <div class="section-box">
            <h3>2. Data Lengkap Pembeli</h3>
            
            <div class="form-group">
                <label for="nama_pembeli">Nama Lengkap</label>
                <input type="text" id="nama_pembeli" name="nama_pembeli" placeholder="Masukkan nama lengkap Anda" required>
            </div>

            <div class="form-group">

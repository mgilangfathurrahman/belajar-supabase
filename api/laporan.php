<?php
if (!isset($_COOKIE['admin_logged_in']) || $_COOKIE['admin_logged_in'] !== 'true') {
    header("Location: login.php");
    exit;
}

// Proses Download Laporan Excel (.xls) dengan tampilan tabel rapi
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require 'koneksi.php';

    $filename = "Laporan-Penjualan-Toko-Gilang-" . date('Y-m-d_H-i') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $sql = "SELECT pesanan.id, pesanan.nama_pembeli, pesanan.no_telp, pesanan.email, pesanan.alamat, 
                   produk.nama_produk, pesanan.jumlah, pesanan.total_harga, pesanan.tanggal 
            FROM pesanan 
            JOIN produk ON pesanan.produk_id = produk.id 
            ORDER BY pesanan.id DESC";
    $data_pesanan = $pdo->query($sql)->fetchAll();
    $grand_total = 0;
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <style>
            table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
            th { background-color: #24b47e; color: #ffffff; font-weight: bold; border: 1px solid #000000; padding: 10px; text-align: center; }
            td { border: 1px solid #000000; padding: 8px; vertical-align: top; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .total-row { background-color: #e6f7f0; font-weight: bold; }
            /* Mencegah nomor telepon berubah menjadi format ilmiah (8.12E+10) di Excel */
            .str { mso-number-format:\@; }
        </style>
    </head>
    <body>
        <h2>Laporan Penjualan - Toko Gilang</h2>
        <p>Dicetak pada: <?= date('d-m-Y H:i') ?> WIB</p>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Pembeli</th>
                    <th>No. Telepon</th>
                    <th>Email</th>
                    <th>Alamat Pengiriman</th>
                    <th>Nama Produk</th>
                    <th>Jumlah</th>
                    <th>Total Harga</th>
                    <th>Waktu Transaksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data_pesanan as $row): ?>
                    <?php $grand_total += $row['total_harga']; ?>
                    <tr>
                        <td class="text-center"><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['nama_pembeli']) ?></td>
                        <td class="str"><?= htmlspecialchars($row['no_telp'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['email'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['alamat'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                        <td class="text-center"><?= $row['jumlah'] ?></td>
                        <td class="text-right">Rp <?= number_format($row['total_harga'], 0, ',', '.') ?></td>
                        <td class="text-center"><?= $row['tanggal'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="7" class="text-right">TOTAL KESELURUHAN PENDAPATAN</td>
                    <td class="text-right">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan Real-Time - Toko Gilang</title>
    <style>
        body { font-family: sans-serif; margin: 20px 40px; }
        nav a { margin-right: 15px; text-decoration: none; font-weight: bold; color: #24b47e; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
        .btn-download { background: #1d6f42; color: white; padding: 10px 16px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; }
        .btn-download:hover { background: #155231; }
        .cards { display: flex; flex-wrap: wrap; gap: 20px; margin: 20px 0; }
        .card { background: #f4f7f6; padding: 20px; border-left: 5px solid #24b47e; width: 250px; border-radius: 4px; }
        .card h3 { margin: 0; font-size: 14px; color: #555; }
        .card p { margin: 10px 0 0; font-size: 24px; font-weight: bold; color: #222; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 14px; }
        th { background: #24b47e; color: white; }
        .badge { background: #e6f7f0; color: #24b47e; padding: 4px 8px; font-size: 12px; border-radius: 4px; vertical-align: middle; }
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

    <div class="header-bar">
        <h2 style="margin:0;">Dashboard Penjualan <span class="badge">● Live Real-Time</span></h2>
        <a href="laporan.php?export=excel" class="btn-download">📥 Download Laporan Excel (.xls)</a>
    </div>
    
    <div class="cards">
        <div class="card">
            <h3>Total Pendapatan</h3>
            <p id="omzet">Memuat...</p>
        </div>
        <div class="card">
            <h3>Total Transaksi</h3>
            <p id="transaksi">Memuat...</p>
        </div>
    </div>

    <h3>5 Transaksi Terbaru</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Pembeli</th>
                <th>Produk</th>
                <th>Jumlah</th>
                <th>Total</th>
                <th>Waktu</th>
            </tr>
        </thead>
        <tbody id="tabel-body">
            <tr><td colspan="6">Menarik data dari PostgreSQL...</td></tr>
        </tbody>
    </table>

    <script>
        function formatRupiah(angka) {
            return 'Rp ' + Number(angka).toLocaleString('id-ID');
        }

        async function muatDataRealTime() {
            try {
                const respon = await fetch('api-laporan.php');
                const data = await respon.json();

                if (data.status === 'sukses') {
                    document.getElementById('omzet').innerText = formatRupiah(data.ringkasan.total_omzet);
                    document.getElementById('transaksi').innerText = data.ringkasan.total_transaksi + ' Pesanan';

                    const tbody = document.getElementById('tabel-body');
                    tbody.innerHTML = '';

                    if (data.transaksi.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Belum ada transaksi.</td></tr>';
                        return;
                    }

                    data.transaksi.forEach(item => {
                        tbody.innerHTML += `
                            <tr>
                                <td>${item.id}</td>
                                <td>${item.nama_pembeli}</td>
                                <td>${item.nama_produk}</td>
                                <td>${item.jumlah}</td>
                                <td>${formatRupiah(item.total_harga)}</td>
                                <td>${item.tanggal}</td>
                            </tr>
                        `;
                    });
                }
            } catch (err) {
                console.error('Gagal memuat data real-time:', err);
            }
        }

        muatDataRealTime();
        setInterval(muatDataRealTime, 3000);
    </script>
</body>
</html>

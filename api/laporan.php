<?php
if (!isset($_COOKIE['admin_logged_in']) || $_COOKIE['admin_logged_in'] !== 'true') {
    header("Location: login.php");
    exit;
}

// Proses Download Laporan CSV / Excel jika parameter ?export=csv dipanggil
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    require 'koneksi.php';

    $filename = "laporan-penjualan-" . date('Y-m-d_H-i') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // Tambahkan BOM UTF-8 agar karakter dan kolom terbaca rapi di Microsoft Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header Kolom Excel
    fputcsv($output, ['ID Pesanan', 'Nama Pembeli', 'No. Telepon', 'Email', 'Alamat Pengiriman', 'Nama Produk', 'Jumlah', 'Total Harga (Rp)', 'Waktu Transaksi']);

    $sql = "SELECT pesanan.id, pesanan.nama_pembeli, pesanan.no_telp, pesanan.email, pesanan.alamat, 
                   produk.nama_produk, pesanan.jumlah, pesanan.total_harga, pesanan.tanggal 
            FROM pesanan 
            JOIN produk ON pesanan.produk_id = produk.id 
            ORDER BY pesanan.id DESC";
    $stmt = $pdo->query($sql);

    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['nama_pembeli'],
            $row['no_telp'] ?: '-',
            $row['email'] ?: '-',
            $row['alamat'] ?: '-',
            $row['nama_produk'],
            $row['jumlah'],
            $row['total_harga'],
            $row['tanggal']
        ]);
    }

    fclose($output);
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
        <a href="laporan.php?export=csv" class="btn-download">📥 Download Laporan (Excel / CSV)</a>
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

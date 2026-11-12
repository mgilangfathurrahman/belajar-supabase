<?php
if (!isset($_COOKIE['admin_logged_in']) || $_COOKIE['admin_logged_in'] !== 'true') {
    header("Location: login.php");
    exit;
}

require 'koneksi.php';

// Ambil seluruh data transaksi untuk kebutuhan ekspor
$sql_semua = "SELECT pesanan.id, pesanan.nama_pembeli, pesanan.no_telp, pesanan.email, pesanan.alamat, 
                     produk.nama_produk, pesanan.jumlah, pesanan.total_harga, pesanan.tanggal 
              FROM pesanan 
              JOIN produk ON pesanan.produk_id = produk.id 
              ORDER BY pesanan.id DESC";
$semua_pesanan = $pdo->query($sql_semua)->fetchAll();

// 1. Proses Download .XLS (HTML Table Berwarna)
if (isset($_GET['export']) && $_GET['export'] === 'xls') {
    $filename = "Laporan-Penjualan-" . date('Y-m-d_H-i') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $grand_total = 0;
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <style>
            table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
            th { background-color: #24b47e; color: #ffffff; font-weight: bold; border: 1px solid #000000; padding: 10px; }
            td { border: 1px solid #000000; padding: 8px; }
            .str { mso-number-format:\@; }
                    /* Aturan otomatis menyembunyikan menu & tombol saat dicetak ke PDF */
        @media print {
            nav, .export-group, .badge, hr {
                display: none !important;
            }
            body {
                margin: 0;
                padding: 0;
            }
            th {
                background-color: #24b47e !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        </style>
    </head>
    <body>
        <h2>Laporan Penjualan - Toko Gilang</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Nama Pembeli</th><th>No. Telepon</th><th>Email</th>
                    <th>Alamat</th><th>Produk</th><th>Jumlah</th><th>Total Harga</th><th>Waktu</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($semua_pesanan as $row): $grand_total += $row['total_harga']; ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['nama_pembeli']) ?></td>
                    <td class="str"><?= htmlspecialchars($row['no_telp'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($row['email'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($row['alamat'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                    <td><?= $row['jumlah'] ?></td>
                    <td>Rp <?= number_format($row['total_harga'], 0, ',', '.') ?></td>
                    <td><?= $row['tanggal'] ?></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="7" style="text-align:right; font-weight:bold;">TOTAL PENDAPATAN</td>
                    <td style="font-weight:bold;">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}

// 2. Proses Download .CSV (Pemisah Titik Koma agar Rapi di Excel Indonesia)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "Laporan-Penjualan-" . date('Y-m-d_H-i') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['ID', 'Nama Pembeli', 'No. Telepon', 'Email', 'Alamat', 'Produk', 'Jumlah', 'Total Harga', 'Waktu'], ';');
    foreach ($semua_pesanan as $row) {
        fputcsv($output, [
            $row['id'], $row['nama_pembeli'], $row['no_telp'] ?: '-', $row['email'] ?: '-',
            $row['alamat'] ?: '-', $row['nama_produk'], $row['jumlah'], $row['total_harga'], $row['tanggal']
        ], ';');
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
    <!-- Library SheetJS untuk XLSX Asli & jsPDF untuk PDF -->
    <script src="https://jsdelivr.net"></script>
    <script src="https://cloudflare.com"></script>
    <script src="https://cloudflare.com"></script>
    <style>
        body { font-family: sans-serif; margin: 20px 40px; }
        nav a { margin-right: 15px; text-decoration: none; font-weight: bold; color: #24b47e; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-top: 15px; }
        .export-group { display: flex; flex-wrap: wrap; gap: 8px; }
        .btn-exp { color: white; padding: 9px 14px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 13px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
        .btn-xlsx { background: #107c41; }
        .btn-xlsx:hover { background: #0b5a2f; }
        .btn-xls { background: #24b47e; }
        .btn-xls:hover { background: #1c8c61; }
        .btn-csv { background: #4b5563; }
        .btn-csv:hover { background: #374151; }
        .btn-pdf { background: #dc2626; }
        .btn-pdf:hover { background: #b91c1c; }
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
        <div class="export-group">
            <button onclick="downloadXLSX()" class="btn-exp btn-xlsx">📊 Excel (.xlsx)</button>
            <a href="laporan.php?export=xls" class="btn-exp btn-xls">📗 Excel (.xls)</a>
            <a href="laporan.php?export=csv" class="btn-exp btn-csv">📄 CSV (.csv)</a>
            <button onclick="downloadPDF()" class="btn-exp btn-pdf">📕 PDF (.pdf)</button>
        </div>
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
        const dataLaporan = <?= json_encode($semua_pesanan, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        function formatRupiah(angka) {
            return 'Rp ' + Number(angka).toLocaleString('id-ID');
        }

        function downloadXLSX() {
            if (typeof XLSX === 'undefined') {
                alert('Library Excel sedang dimuat, silakan coba klik beberapa detik lagi atau gunakan tombol Excel (.xls).');
                return;
            }
            let totalSemua = 0;
            const rows = dataLaporan.map(item => {
                totalSemua += Number(item.total_harga);
                return {
                    'ID': item.id,
                    'Nama Pembeli': item.nama_pembeli,
                    'No. Telepon': item.no_telp || '-',
                    'Email': item.email || '-',
                    'Alamat Pengiriman': item.alamat || '-',
                    'Nama Produk': item.nama_produk,
                    'Jumlah': Number(item.jumlah),
                    'Total Harga (Rp)': Number(item.total_harga),
                    'Waktu Transaksi': item.tanggal
                };
            });

            rows.push({
                'ID': '', 'Nama Pembeli': '', 'No. Telepon': '', 'Email': '',
                'Alamat Pengiriman': '', 'Nama Produk': '',
                'Jumlah': 'TOTAL PENDAPATAN',
                'Total Harga (Rp)': totalSemua,
                'Waktu Transaksi': ''
            });

            const worksheet = XLSX.utils.json_to_sheet(rows);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, "Laporan Penjualan");
            XLSX.writeFile(workbook, "Laporan-Penjualan-Toko-Gilang.xlsx");
        }

     function downloadPDF() {
            window.print();
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

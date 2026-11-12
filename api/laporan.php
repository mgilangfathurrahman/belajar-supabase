<?php
if (!isset($_COOKIE['admin_logged_in']) || $_COOKIE['admin_logged_in'] !== 'true') {
    header("Location: login.php");
    exit;
}

require 'koneksi.php';
require_once __DIR__ . '/export_helpers.php';

// Ambil seluruh data transaksi
$sql_semua = "SELECT pesanan.id, pesanan.nama_pembeli, pesanan.no_telp, pesanan.email, pesanan.alamat, 
                     produk.nama_produk, pesanan.jumlah, pesanan.total_harga, pesanan.tanggal 
              FROM pesanan 
              JOIN produk ON pesanan.produk_id = produk.id 
              ORDER BY pesanan.id DESC";
$semua_pesanan = $pdo->query($sql_semua)->fetchAll();

$export = $_GET['export'] ?? '';

// ===== Pengaturan export XLSX & XLS =====
// Jumlah transaksi terbaru yang dimasukkan ke file. Isi null untuk semua transaksi.
$BATAS_EXPORT = 5;

$total_omzet_semua = 0;
foreach ($semua_pesanan as $r) {
    $total_omzet_semua += (int)$r['total_harga'];
}
$total_transaksi_semua = count($semua_pesanan);
$data_export = $BATAS_EXPORT ? array_slice($semua_pesanan, 0, $BATAS_EXPORT) : $semua_pesanan;
$judul_tabel = $BATAS_EXPORT ? $BATAS_EXPORT . ' Transaksi Terbaru' : 'Semua Transaksi';

// 1. Download .XLSX (murni PHP, tanpa ZipArchive & tanpa CDN)
if ($export === 'xlsx') {
    $kosong = fn(string $gaya) => xcell('', $gaya);

    $rows = [];
    $rows[] = [xcell('Laporan Penjualan - Toko Gilang', 'title')];
    $rows[] = [xcell('Dicetak: ' . date('d-m-Y H:i'), 'muted')];
    $rows[] = [];
    $rows[] = [
        xcell('Total Pendapatan', 'label'), $kosong('label'), $kosong('label'),
        xcell('Total Transaksi', 'label'), $kosong('label'), $kosong('label'),
    ];
    $rows[] = [
        xcell($total_omzet_semua, 'bigrp'), $kosong('bigrp'), $kosong('bigrp'),
        xcell($total_transaksi_semua . ' Pesanan', 'bigtext'), $kosong('bigtext'), $kosong('bigtext'),
    ];
    $rows[] = [];
    $rows[] = [xcell($judul_tabel, 'section')];
    $rows[] = array_map(fn($h) => xcell($h, 'head'), ['ID', 'Pembeli', 'Produk', 'Jumlah', 'Total', 'Waktu']);

    foreach ($data_export as $r) {
        $rows[] = [
            xcell((int)$r['id'], 'int'),
            xcell((string)$r['nama_pembeli'], 'cell'),
            xcell((string)$r['nama_produk'], 'cell'),
            xcell((int)$r['jumlah'], 'int'),
            xcell((int)$r['total_harga'], 'rp'),
            xcell(substr((string)$r['tanggal'], 0, 19), 'cell'),
        ];
    }

    $xlsx = buildXlsx(
        $rows,
        [],
        'Laporan Penjualan',
        [8, 22, 32, 10, 18, 22],
        ['A1:F1', 'A2:F2', 'A4:C4', 'D4:F4', 'A5:C5', 'D5:F5', 'A7:F7'],
        false
    );
    send_download(
        $xlsx,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Laporan-Penjualan-Toko-Gilang-' . date('Y-m-d_H-i') . '.xlsx'
    );
}

// 2. Download .PDF (file PDF asli, tampilan sama dengan dashboard)
if ($export === 'pdf') {
    $headers = ['ID', 'Pembeli', 'Produk', 'Jumlah', 'Total', 'Waktu'];
    $widths  = [40, 150, 220, 60, 120, 192]; // total 782 pt (A4 landscape)

    $rows = [];
    foreach ($data_export as $r) {
        $rows[] = [
            (string)$r['id'],
            (string)$r['nama_pembeli'],
            (string)$r['nama_produk'],
            (string)$r['jumlah'],
            'Rp ' . number_format((int)$r['total_harga'], 0, ',', '.'),
            substr((string)$r['tanggal'], 0, 19)
        ];
    }
    $summary = [
        ['Total Pendapatan', 'Rp ' . number_format($total_omzet_semua, 0, ',', '.')],
        ['Total Transaksi', $total_transaksi_semua . ' Pesanan'],
    ];

    $pdf = buildPdf('Laporan Penjualan - Toko Gilang', $headers, $rows, $widths, null, $summary, $judul_tabel);
    send_download($pdf, 'application/pdf', 'Laporan-Penjualan-' . date('Y-m-d_H-i') . '.pdf');
}

// 3. Proses Download .XLS (HTML Table Berwarna, tampilan sama dengan dashboard)
if ($export === 'xls') {
    $filename = "Laporan-Penjualan-" . date('Y-m-d_H-i') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <style>
            table { border-collapse: collapse; font-family: Arial, sans-serif; }
            .judul { font-size: 18pt; font-weight: bold; }
            .muted { font-size: 10pt; color: #555555; }
            .label { background-color: #f4f7f6; color: #555555; font-size: 10pt; font-weight: bold; }
            .nilai { background-color: #f4f7f6; font-size: 16pt; font-weight: bold; text-align: left; }
            .seksi { font-size: 13pt; font-weight: bold; }
            th { background-color: #24b47e; color: #ffffff; font-weight: bold; border: 1px solid #bbbbbb; padding: 8px; text-align: left; }
            td.sel { border: 1px solid #bbbbbb; padding: 8px; text-align: left; vertical-align: top; }
            .str { mso-number-format:\@; }
        </style>
    </head>
    <body>
        <table>
            <colgroup>
                <col width="50"><col width="150"><col width="220"><col width="70"><col width="130"><col width="160">
            </colgroup>
            <tr><td colspan="6" class="judul">Laporan Penjualan - Toko Gilang</td></tr>
            <tr><td colspan="6" class="muted">Dicetak: <?= date('d-m-Y H:i') ?></td></tr>
            <tr><td colspan="6"></td></tr>
            <tr>
                <td colspan="3" class="label">Total Pendapatan</td>
                <td colspan="3" class="label">Total Transaksi</td>
            </tr>
            <tr>
                <td colspan="3" class="nilai">Rp <?= number_format($total_omzet_semua, 0, ',', '.') ?></td>
                <td colspan="3" class="nilai"><?= $total_transaksi_semua ?> Pesanan</td>
            </tr>
            <tr><td colspan="6"></td></tr>
            <tr><td colspan="6" class="seksi"><?= htmlspecialchars($judul_tabel) ?></td></tr>
            <tr>
                <th>ID</th><th>Pembeli</th><th>Produk</th><th>Jumlah</th><th>Total</th><th>Waktu</th>
            </tr>
            <?php foreach ($data_export as $row): ?>
            <tr>
                <td class="sel"><?= (int)$row['id'] ?></td>
                <td class="sel"><?= htmlspecialchars($row['nama_pembeli']) ?></td>
                <td class="sel"><?= htmlspecialchars($row['nama_produk']) ?></td>
                <td class="sel"><?= (int)$row['jumlah'] ?></td>
                <td class="sel">Rp <?= number_format((int)$row['total_harga'], 0, ',', '.') ?></td>
                <td class="sel str"><?= htmlspecialchars(substr((string)$row['tanggal'], 0, 19)) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </body>
    </html>
    <?php
    exit;
}

// 4. Proses Download .CSV
if ($export === 'csv') {
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
            <a href="laporan.php?export=xlsx" class="btn-exp btn-xlsx">📊 Excel (.xlsx)</a>
            <a href="laporan.php?export=xls" class="btn-exp btn-xls">📗 Excel (.xls)</a>
            <a href="laporan.php?export=csv" class="btn-exp btn-csv">📄 CSV (.csv)</a>
            <a href="laporan.php?export=pdf" class="btn-exp btn-pdf">📕 PDF (.pdf)</a>
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

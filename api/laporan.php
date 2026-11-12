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

// 1. Ambil Seluruh Data Transaksi Sesuai Hak Akses
if ($is_super) {
    $sql_semua = "SELECT pesanan.id, pesanan.nama_pembeli, pesanan.no_telp, pesanan.email, pesanan.alamat, 
                         produk.nama_produk, pesanan.jumlah, pesanan.total_harga, pesanan.tanggal, pesanan.dibuat_oleh 
                  FROM pesanan 
                  JOIN produk ON pesanan.produk_id = produk.id 
                  ORDER BY pesanan.id DESC";
    $semua_pesanan = $pdo->query($sql_semua)->fetchAll();
} else {
    $sql_semua = "SELECT pesanan.id, pesanan.nama_pembeli, pesanan.no_telp, pesanan.email, pesanan.alamat, 
                         produk.nama_produk, pesanan.jumlah, pesanan.total_harga, pesanan.tanggal, pesanan.dibuat_oleh 
                  FROM pesanan 
                  JOIN produk ON pesanan.produk_id = produk.id 
                  WHERE pesanan.dibuat_oleh = :oleh 
                  ORDER BY pesanan.id DESC";
    $stmt_semua = $pdo->prepare($sql_semua);
    $stmt_semua->execute([':oleh' => $admin_nama]);
    $semua_pesanan = $stmt_semua->fetchAll();
}

$total_omzet_semua = 0;
foreach ($semua_pesanan as $r) {
    $total_omzet_semua += (int)$r['total_harga'];
}
$total_transaksi_semua = count($semua_pesanan);
$data_export = $semua_pesanan;
$judul_lap   = $is_super ? 'Laporan Penjualan (Semua Admin) - Toko Gilang' : 'Laporan Penjualan (' . $admin_nama . ') - Toko Gilang';
$judul_tabel = $is_super ? 'Semua Transaksi Penjualan' : 'Semua Transaksi Inputan ' . $admin_nama;

$export = $_GET['export'] ?? '';

// 2. Download .XLSX
if ($export === 'xlsx') {
    $kosong = fn(string $gaya) => xcell('', $gaya);

    $rows = [];
    $rows[] = [xcell($judul_lap, 'title')];
    $rows[] = [xcell('Dicetak: ' . date('d-m-Y H:i'), 'muted')];
    $rows[] = [];
    $rows[] = [
        xcell('Total Pendapatan', 'label'), $kosong('label'), $kosong('label'),
        xcell('Total Transaksi', 'label'), $kosong('label'), $kosong('label'), $kosong('label'),
    ];
    $rows[] = [
        xcell($total_omzet_semua, 'bigrp'), $kosong('bigrp'), $kosong('bigrp'),
        xcell($total_transaksi_semua . ' Pesanan', 'bigtext'), $kosong('bigtext'), $kosong('bigtext'), $kosong('bigtext'),
    ];
    $rows[] = [];
    $rows[] = [xcell($judul_tabel, 'section')];
    $rows[] = array_map(fn($h) => xcell($h, 'head'), ['No', 'Pembeli', 'Produk', 'Jumlah', 'Total', 'Diinput Oleh', 'Waktu']);

    foreach (array_values($data_export) as $i => $r) {
        $rows[] = [
            xcell($i + 1, 'int'),
            xcell((string)$r['nama_pembeli'], 'cell'),
            xcell((string)$r['nama_produk'], 'cell'),
            xcell((int)$r['jumlah'], 'int'),
            xcell((int)$r['total_harga'], 'rp'),
            xcell((string)($r['dibuat_oleh'] ?? '-'), 'cell'),
            xcell(substr((string)$r['tanggal'], 0, 19), 'cell'),
        ];
    }

    $lebar_kolom = array(8, 22, 30, 10, 18, 18, 22);
    $gabung_sel  = array('A1:G1', 'A2:G2', 'A4:C4', 'D4:G4', 'A5:C5', 'D5:G5', 'A7:G7');
    $xlsx = buildXlsx($rows, array(), 'Laporan Penjualan', $lebar_kolom, $gabung_sel, false);
    send_download(
        $xlsx,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Laporan-Penjualan-' . date('Y-m-d_H-i') . '.xlsx'
    );
}

// 3. Download .PDF
if ($export === 'pdf') {
    $headers = array('No', 'Pembeli', 'Produk', 'Jumlah', 'Total', 'Diinput Oleh', 'Waktu');
    $widths  = array(35, 130, 195, 52, 110, 110, 150);

    $rows = [];
    foreach (array_values($data_export) as $i => $r) {
        $rows[] = [
            (string)($i + 1),
            (string)$r['nama_pembeli'],
            (string)$r['nama_produk'],
            (string)$r['jumlah'],
            'Rp ' . number_format((int)$r['total_harga'], 0, ',', '.'),
            (string)($r['dibuat_oleh'] ?? '-'),
            substr((string)$r['tanggal'], 0, 19)
        ];
    }
    $summary = [
        ['Total Pendapatan', 'Rp ' . number_format($total_omzet_semua, 0, ',', '.')],
        ['Total Transaksi', $total_transaksi_semua . ' Pesanan'],
    ];

    $pdf = buildPdf($judul_lap, $headers, $rows, $widths, null, $summary, $judul_tabel);
    send_download($pdf, 'application/pdf', 'Laporan-Penjualan-' . date('Y-m-d_H-i') . '.pdf');
}

// 4. Download .XLS
if ($export === 'xls') {
    $filename = "Laporan-Penjualan-" . date('Y-m-d_H-i') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");

    $s_judul = 'font-size:18pt;font-weight:bold;';
    $s_muted = 'font-size:10pt;color:#555555;';
    $s_label = 'background:#f4f7f6;color:#555555;font-size:10pt;font-weight:bold;text-align:left;';
    $s_nilai = 'background:#f4f7f6;font-size:16pt;font-weight:bold;text-align:left;mso-number-format:\@;';
    $s_seksi = 'font-size:13pt;font-weight:bold;';
    $s_head  = 'background:#24b47e;color:#ffffff;font-weight:bold;border:1px solid #bbbbbb;padding:6px;text-align:left;';
    $s_sel   = 'border:1px solid #bbbbbb;padding:6px;text-align:left;vertical-align:top;mso-number-format:\@;';

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
    echo '<table style="border-collapse:collapse;font-family:Calibri,Arial,sans-serif;">';
    echo '<tr><td colspan="7" style="' . $s_judul . '">' . htmlspecialchars($judul_lap) . '</td></tr>';
    echo '<tr><td colspan="7" style="' . $s_muted . '">Dicetak: ' . date('d-m-Y H:i') . '</td></tr>';
    echo '<tr><td colspan="7"></td></tr>';
    echo '<tr><td colspan="3" bgcolor="#f4f7f6" style="' . $s_label . '">Total Pendapatan</td><td colspan="4" bgcolor="#f4f7f6" style="' . $s_label . '">Total Transaksi</td></tr>';
    echo '<tr><td colspan="3" bgcolor="#f4f7f6" style="' . $s_nilai . '">Rp ' . number_format($total_omzet_semua, 0, ',', '.') . '</td><td colspan="4" bgcolor="#f4f7f6" style="' . $s_nilai . '">' . $total_transaksi_semua . ' Pesanan</td></tr>';
    echo '<tr><td colspan="7"></td></tr>';
    echo '<tr><td colspan="7" style="' . $s_seksi . '">' . htmlspecialchars($judul_tabel) . '</td></tr><tr>';
    foreach (['No', 'Pembeli', 'Produk', 'Jumlah', 'Total', 'Diinput Oleh', 'Waktu'] as $h) {
        echo '<th bgcolor="#24b47e" style="' . $s_head . '"><font color="#FFFFFF">' . $h . '</font></th>';
    }
    echo '</tr>';
    foreach (array_values($data_export) as $i => $row) {
        echo '<tr>';
        echo '<td style="' . $s_sel . '">' . ($i + 1) . '</td>';
        echo '<td style="' . $s_sel . '">' . htmlspecialchars($row['nama_pembeli']) . '</td>';
        echo '<td style="' . $s_sel . '">' . htmlspecialchars($row['nama_produk']) . '</td>';
        echo '<td style="' . $s_sel . '">' . (int)$row['jumlah'] . '</td>';
        echo '<td style="' . $s_sel . '">Rp ' . number_format((int)$row['total_harga'], 0, ',', '.') . '</td>';
        echo '<td style="' . $s_sel . '">' . htmlspecialchars($row['dibuat_oleh'] ?? '-') . '</td>';
        echo '<td style="' . $s_sel . '">' . htmlspecialchars(substr((string)$row['tanggal'], 0, 19)) . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}

// 5. Download .CSV
if ($export === 'csv') {
    $filename = "Laporan-Penjualan-" . date('Y-m-d_H-i') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['No', 'Nama Pembeli', 'No. Telepon', 'Email', 'Alamat', 'Produk', 'Jumlah', 'Total Harga', 'Diinput Oleh', 'Waktu'], ';');
    foreach (array_values($data_export) as $i => $row) {
        fputcsv($output, [
            $i + 1, $row['nama_pembeli'], $row['no_telp'] ?: '-', $row['email'] ?: '-',
            $row['alamat'] ?: '-', $row['nama_produk'], $row['jumlah'], $row['total_harga'],
            $row['dibuat_oleh'] ?? '-', $row['tanggal']
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
    <title>Laporan Penjualan Real-Time</title>
    <style>
        body { font-family: sans-serif; margin: 20px 40px; }
        nav { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        nav a { margin-right: 15px; text-decoration: none; font-weight: bold; color: #24b47e; }
        .user-badge { background: #e6f7f0; color: #1c8c61; padding: 5px 10px; border-radius: 5px; font-size: 13px; font-weight: bold; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin: 25px 0 10px; }
        .export-group { display: flex; flex-wrap: wrap; gap: 8px; }
        .btn-exp { color: white; padding: 8px 12px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-xlsx { background: #107c41; } .btn-xlsx:hover { background: #0b5a2f; }
        .btn-xls { background: #24b47e; } .btn-xls:hover { background: #1c8c61; }
        .btn-csv { background: #4b5563; } .btn-csv:hover { background: #374151; }
        .btn-pdf { background: #dc2626; } .btn-pdf:hover { background: #b91c1c; }
        .cards { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .card { background: #f4f7f6; padding: 20px; border-radius: 8px; flex: 1; min-width: 220px; border-left: 5px solid #24b47e; }
        .card h3 { margin: 0 0 10px 0; color: #555; font-size: 14px; }
        .card p { margin: 0; font-size: 28px; font-weight: bold; color: #222; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 14px; }
        th { background: #24b47e; color: white; }
        .badge { background: #e6f7f0; color: #24b47e; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
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

    <h2 style="margin: 20px 0 15px;">Dashboard Penjualan <span class="badge">● Live Real-Time</span></h2>

    <div class="cards">
        <div class="card">
            <h3><?= $is_super ? 'Total Pendapatan Keseluruhan' : 'Total Pendapatan (' . htmlspecialchars($admin_nama) . ')' ?></h3>
            <p id="omzet">Rp <?= number_format($total_omzet_semua, 0, ',', '.') ?></p>
        </div>
        <div class="card">
            <h3><?= $is_super ? 'Total Transaksi Keseluruhan' : 'Total Transaksi (' . htmlspecialchars($admin_nama) . ')' ?></h3>
            <p id="transaksi"><?= $total_transaksi_semua ?> Pesanan</p>
        </div>
    </div>

    <div class="header-bar">
        <h3 style="margin:0;"><?= htmlspecialchars($judul_tabel) ?></h3>
        <div class="export-group">
            <a href="laporan.php?export=xlsx" class="btn-exp btn-xlsx">📊 Excel (.xlsx)</a>
            <a href="laporan.php?export=xls" class="btn-exp btn-xls">📗 Excel (.xls)</a>
            <a href="laporan.php?export=csv" class="btn-exp btn-csv">📄 CSV (.csv)</a>
            <a href="laporan.php?export=pdf" class="btn-exp btn-pdf">📕 PDF (.pdf)</a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Pembeli</th>
                <th>Produk</th>
                <th>Jumlah</th>
                <th>Total</th>
                <th>Diinput Oleh</th>
                <th>Waktu</th>
            </tr>
        </thead>
        <tbody id="tabel-transaksi">
            <?php if (count($semua_pesanan) > 0): ?>
                <?php foreach (array_values($semua_pesanan) as $i => $row): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($row['nama_pembeli']) ?></td>
                    <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                    <td><?= (int)$row['jumlah'] ?></td>
                    <td><strong>Rp <?= number_format((int)$row['total_harga'], 0, ',', '.') ?></strong></td>
                    <td><strong><?= htmlspecialchars($row['dibuat_oleh'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars(substr((string)$row['tanggal'], 0, 19)) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center;">Belum ada transaksi.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        function updateLaporan() {
            fetch('api-laporan.php')
                .then(res => res.json())
                .then(data => {
                    document.getElementById('omzet').innerText = 'Rp ' + Number(data.total_omzet).toLocaleString('id-ID');
                    document.getElementById('transaksi').innerText = data.total_transaksi + ' Pesanan';

                    let html = '';
                    if (data.terbaru.length === 0) {
                        html = '<tr><td colspan="7" style="text-align:center;">Belum ada transaksi.</td></tr>';
                    } else {
                        data.terbaru.forEach((row, index) => {
                            let oleh = row.dibuat_oleh ? row.dibuat_oleh : '-';
                            let waktu = row.tanggal ? row.tanggal.substring(0, 19) : '-';
                            html += `<tr>
                                <td>${index + 1}</td>
                                <td>${row.nama_pembeli}</td>
                                <td>${row.nama_produk}</td>
                                <td>${row.jumlah}</td>
                                <td><strong>Rp ${Number(row.total_harga).toLocaleString('id-ID')}</strong></td>
                                <td><strong>${oleh}</strong></td>
                                <td>${waktu}</td>
                            </tr>`;
                        });
                    }
                    document.getElementById('tabel-transaksi').innerHTML = html;
                })
                .catch(err => console.error('Gagal mengambil data:', err));
        }

        setInterval(updateLaporan, 3000);
    </script>
</body>
</html>

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

// 4. Download data pesanan: ?export=xlsx | xls | pdf | csv
$export = $_GET['export'] ?? '';
if (in_array($export, ['xlsx', 'xls', 'pdf', 'csv'], true)) {
    require_once __DIR__ . '/export_helpers.php';

    $total_omzet   = 0;
    foreach ($pesanan_list as $r) {
        $total_omzet += (int)$r['total_harga'];
    }
    $total_pesanan = count($pesanan_list);
    $judul_tabel   = 'Daftar Semua Pesanan';
    $headers       = ['No', 'Pembeli', 'No. Telepon', 'Email', 'Alamat', 'Produk', 'Qty', 'Total', 'Waktu'];
    $stamp         = date('Y-m-d_H-i');
    $isi           = fn($v) => ($v === null || trim((string)$v) === '') ? '-' : trim((string)$v);
    $waktu         = fn($v) => substr((string)$v, 0, 19);

    // ---------- XLSX ----------
    if ($export === 'xlsx') {
        $kosong = fn(string $gaya) => xcell('', $gaya);

        $rows = [];
        $rows[] = [xcell('Data Pesanan - Toko Gilang', 'title')];
        $rows[] = [xcell('Dicetak: ' . date('d-m-Y H:i'), 'muted')];
        $rows[] = [];
        $rows[] = [
            xcell('Total Pendapatan', 'label'), $kosong('label'), $kosong('label'), $kosong('label'),
            xcell('Total Pesanan', 'label'), $kosong('label'), $kosong('label'), $kosong('label'), $kosong('label'),
        ];
        $rows[] = [
            xcell($total_omzet, 'bigrp'), $kosong('bigrp'), $kosong('bigrp'), $kosong('bigrp'),
            xcell($total_pesanan . ' Pesanan', 'bigtext'), $kosong('bigtext'), $kosong('bigtext'), $kosong('bigtext'), $kosong('bigtext'),
        ];
        $rows[] = [];
        $rows[] = [xcell($judul_tabel, 'section')];
        $rows[] = array_map(fn($h) => xcell($h, 'head'), $headers);

        foreach (array_values($pesanan_list) as $i => $r) {
            $rows[] = [
                xcell($i + 1, 'int'),
                xcell($isi($r['nama_pembeli']), 'cell'),
                xcell($isi($r['no_telp']), 'cell'),   // teks, agar angka 0 di depan tidak hilang
                xcell($isi($r['email']), 'cell'),
                xcell($isi($r['alamat']), 'cell'),
                xcell($isi($r['nama_produk']), 'cell'),
                xcell((int)$r['jumlah'], 'int'),
                xcell((int)$r['total_harga'], 'rp'),
                xcell($waktu($r['tanggal']), 'cell'),
            ];
        }

        $xlsx = buildXlsx(
            $rows,
            [],
            'Data Pesanan',
            [8, 22, 17, 28, 36, 28, 8, 18, 22],
            ['A1:I1', 'A2:I2', 'A4:D4', 'E4:I4', 'A5:D5', 'E5:I5', 'A7:I7'],
            false
        );
        send_download(
            $xlsx,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Data-Pesanan-Toko-Gilang-' . $stamp . '.xlsx'
        );
    }

    // ---------- PDF ----------
    if ($export === 'pdf') {
        $widths = [30, 95, 75, 115, 150, 100, 35, 80, 102]; // total 782 pt (A4 landscape)

        $rows = [];
        foreach (array_values($pesanan_list) as $i => $r) {
            $rows[] = [
                (string)($i + 1),
                $isi($r['nama_pembeli']),
                $isi($r['no_telp']),
                $isi($r['email']),
                $isi($r['alamat']),
                $isi($r['nama_produk']),
                (string)$r['jumlah'],
                'Rp ' . number_format((int)$r['total_harga'], 0, ',', '.'),
                $waktu($r['tanggal']),
            ];
        }
        $summary = [
            ['Total Pendapatan', 'Rp ' . number_format($total_omzet, 0, ',', '.')],
            ['Total Pesanan', $total_pesanan . ' Pesanan'],
        ];

        $pdf = buildPdf('Data Pesanan - Toko Gilang', $headers, $rows, $widths, null, $summary, $judul_tabel);
        send_download($pdf, 'application/pdf', 'Data-Pesanan-' . $stamp . '.pdf');
    }

    // ---------- CSV ----------
    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Data-Pesanan-' . $stamp . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
        fputcsv($out, $headers, ';');
        foreach (array_values($pesanan_list) as $i => $r) {
            fputcsv($out, [
                $i + 1, $isi($r['nama_pembeli']), $isi($r['no_telp']), $isi($r['email']),
                $isi($r['alamat']), $isi($r['nama_produk']), $r['jumlah'], $r['total_harga'], $waktu($r['tanggal'])
            ], ';');
        }
        fclose($out);
        exit;
    }

    // ---------- XLS (HTML table, semua gaya inline agar terbaca Excel) ----------
    if ($export === 'xls') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="Data-Pesanan-' . $stamp . '.xls"');

        $s_judul = 'font-size:18pt;font-weight:bold;';
        $s_muted = 'font-size:10pt;color:#555555;';
        $s_label = 'background:#f4f7f6;color:#555555;font-size:10pt;font-weight:bold;text-align:left;';
        $s_nilai = 'background:#f4f7f6;font-size:16pt;font-weight:bold;text-align:left;mso-number-format:\@;';
        $s_seksi = 'font-size:13pt;font-weight:bold;';
        $s_head  = 'background:#24b47e;color:#ffffff;font-weight:bold;border:1px solid #bbbbbb;padding:6px;text-align:left;vertical-align:middle;white-space:nowrap;';
        $s_sel   = 'border:1px solid #bbbbbb;padding:6px;text-align:left;vertical-align:top;mso-number-format:\@;';
        $s_nowr  = $s_sel . 'white-space:nowrap;';
        ?>
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
                <x:Name>Data Pesanan</x:Name>
                <x:WorksheetOptions><x:DisplayGridlines>false</x:DisplayGridlines></x:WorksheetOptions>
            </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
        </head>
        <body>
            <table style="border-collapse:collapse;font-family:Calibri,Arial,sans-serif;">
                <colgroup>
                    <col width="60"><col width="160"><col width="120"><col width="200"><col width="240">
                    <col width="190"><col width="60"><col width="130"><col width="160">
                </colgroup>
                <tr><td colspan="9" style="<?= $s_judul ?>">Data Pesanan - Toko Gilang</td></tr>
                <tr><td colspan="9" style="<?= $s_muted ?>">Dicetak: <?= date('d-m-Y H:i') ?></td></tr>
                <tr><td colspan="9"></td></tr>
                <tr>
                    <td colspan="4" bgcolor="#f4f7f6" style="<?= $s_label ?>">Total Pendapatan</td>
                    <td colspan="5" bgcolor="#f4f7f6" style="<?= $s_label ?>">Total Pesanan</td>
                </tr>
                <tr>
                    <td colspan="4" bgcolor="#f4f7f6" style="<?= $s_nilai ?>">Rp <?= number_format($total_omzet, 0, ',', '.') ?></td>
                    <td colspan="5" bgcolor="#f4f7f6" style="<?= $s_nilai ?>"><?= $total_pesanan ?> Pesanan</td>
                </tr>
                <tr><td colspan="9"></td></tr>
                <tr><td colspan="9" style="<?= $s_seksi ?>"><?= htmlspecialchars($judul_tabel) ?></td></tr>
                <tr>
                    <?php foreach ($headers as $h): ?>
                    <th bgcolor="#24b47e" style="<?= $s_head ?>"><font color="#FFFFFF"><?= htmlspecialchars($h) ?></font></th>
                    <?php endforeach; ?>
                </tr>
                <?php foreach (array_values($pesanan_list) as $i => $r): ?>
                <tr>
                    <td style="<?= $s_nowr ?>"><?= $i + 1 ?></td>
                    <td style="<?= $s_sel ?>"><?= htmlspecialchars($isi($r['nama_pembeli'])) ?></td>
                    <td style="<?= $s_nowr ?>"><?= htmlspecialchars($isi($r['no_telp'])) ?></td>
                    <td style="<?= $s_sel ?>"><?= htmlspecialchars($isi($r['email'])) ?></td>
                    <td style="<?= $s_sel ?>"><?= htmlspecialchars($isi($r['alamat'])) ?></td>
                    <td style="<?= $s_sel ?>"><?= htmlspecialchars($isi($r['nama_produk'])) ?></td>
                    <td style="<?= $s_nowr ?>"><?= (int)$r['jumlah'] ?></td>
                    <td style="<?= $s_nowr ?>">Rp <?= number_format((int)$r['total_harga'], 0, ',', '.') ?></td>
                    <td style="<?= $s_nowr ?>"><?= htmlspecialchars($waktu($r['tanggal'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </body>
        </html>
        <?php
        exit;
    }
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

        .header-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-top: 25px; }
        .export-group { display: flex; flex-wrap: wrap; gap: 8px; }
        .btn-exp { color: white; padding: 9px 14px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-xlsx { background: #107c41; } .btn-xlsx:hover { background: #0b5a2f; }
        .btn-xls  { background: #24b47e; } .btn-xls:hover  { background: #1c8c61; }
        .btn-csv  { background: #4b5563; } .btn-csv:hover  { background: #374151; }
        .btn-pdf  { background: #dc2626; } .btn-pdf:hover  { background: #b91c1c; }
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

    <div class="header-bar">
        <h3 style="margin:0;">Daftar Semua Pesanan Masuk</h3>
        <div class="export-group">
            <a href="pesanan.php?export=xlsx" class="btn-exp btn-xlsx">📊 Excel (.xlsx)</a>
            <a href="pesanan.php?export=xls" class="btn-exp btn-xls">📗 Excel (.xls)</a>
            <a href="pesanan.php?export=csv" class="btn-exp btn-csv">📄 CSV (.csv)</a>
            <a href="pesanan.php?export=pdf" class="btn-exp btn-pdf">📕 PDF (.pdf)</a>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>No</th>
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
                    <?php foreach (array_values($pesanan_list) as $no => $ps): ?>
                    <tr>
                        <td><?= $no + 1 ?></td>
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

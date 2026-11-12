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

$pesan = "";

// 1. Proses Batalkan Pesanan
if (isset($_GET['batal'])) {
    $id_pesanan = (int) $_GET['batal'];
    try {
        $pdo->beginTransaction();

        if ($is_super) {
            $stmt = $pdo->prepare("SELECT produk_id, jumlah FROM pesanan WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $id_pesanan]);
        } else {
            $stmt = $pdo->prepare("SELECT produk_id, jumlah FROM pesanan WHERE id = :id AND dibuat_oleh = :oleh FOR UPDATE");
            $stmt->execute([':id' => $id_pesanan, ':oleh' => $admin_nama]);
        }
        $data_pesanan = $stmt->fetch();

        if ($data_pesanan) {
            $upd = $pdo->prepare("UPDATE produk SET stok = stok + :jml WHERE id = :pid");
            $upd->execute([':jml' => $data_pesanan['jumlah'], ':pid' => $data_pesanan['produk_id']]);

            $del = $pdo->prepare("DELETE FROM pesanan WHERE id = :id");
            $del->execute([':id' => $id_pesanan]);

            $pdo->commit();
            header("Location: pesanan.php?status=batal");
            exit;
        } else {
            $pdo->rollBack();
            $pesan = "Pesanan tidak ditemukan atau Anda tidak memiliki hak untuk membatalkannya.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pesan = "Gagal membatalkan pesanan: " . $e->getMessage();
    }
}

// 2. Proses Catat Pesanan Baru
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

            $ins = $pdo->prepare("INSERT INTO pesanan (nama_pembeli, no_telp, email, alamat, produk_id, jumlah, total_harga, dibuat_oleh) 
                                  VALUES (:pembeli, :telp, :email, :alamat, :pid, :jml, :total, :oleh)");
            $ins->execute([
                ':pembeli' => $nama_pembeli,
                ':telp'    => $no_telp,
                ':email'   => $email,
                ':alamat'  => $alamat,
                ':pid'     => $produk_id,
                ':jml'     => $jumlah,
                ':total'   => $total_harga,
                ':oleh'    => $admin_nama
            ]);

            $upd = $pdo->prepare("UPDATE produk SET stok = stok - :jml WHERE id = :pid");
            $upd->execute([':jml' => $jumlah, ':pid' => $produk_id]);

            header("Location: pesanan.php?status=sukses");
            exit;
        } else {
            $pesan = "Stok tidak mencukupi atau jumlah tidak valid!";
        }
    } catch (PDOException $e) {
        $pesan = "Gagal menyimpan pesanan: " . $e->getMessage();
    }
}

// 3. Ambil Produk & Daftar Pesanan Sesuai Hak Akses
$produk_tersedia = $pdo->query("SELECT * FROM produk WHERE stok > 0 ORDER BY nama_produk ASC")->fetchAll();

if ($is_super) {
    $sql_pesanan = "SELECT pesanan.*, produk.nama_produk FROM pesanan 
                    JOIN produk ON pesanan.produk_id = produk.id 
                    ORDER BY pesanan.id DESC";
    $pesanan_list = $pdo->query($sql_pesanan)->fetchAll();
} else {
    $sql_pesanan = "SELECT pesanan.*, produk.nama_produk FROM pesanan 
                    JOIN produk ON pesanan.produk_id = produk.id 
                    WHERE pesanan.dibuat_oleh = :oleh 
                    ORDER BY pesanan.id DESC";
    $stmt_ps = $pdo->prepare($sql_pesanan);
    $stmt_ps->execute([':oleh' => $admin_nama]);
    $pesanan_list = $stmt_ps->fetchAll();
}

// 4. Proses Ekspor 4 Format (.xlsx, .pdf, .xls, .csv)
$export = $_GET['export'] ?? '';
if ($export !== '') {
    $total_omzet = 0;
    foreach ($pesanan_list as $ps) {
        $total_omzet += (int)$ps['total_harga'];
    }
    $total_trx   = count($pesanan_list);
    $judul_lap   = $is_super ? 'Laporan Semua Pesanan - Toko Gilang' : 'Laporan Pesanan (' . $admin_nama . ') - Toko Gilang';
    $judul_seksi = $is_super ? 'Daftar Semua Pesanan Masuk' : 'Daftar Pesanan Inputan ' . $admin_nama;

    if ($export === 'xlsx') {
        $kosong = fn(string $gaya) => xcell('', $gaya);
        $rows = [];
        $rows[] = [xcell($judul_lap, 'title')];
        $rows[] = [xcell('Dicetak: ' . date('d-m-Y H:i'), 'muted')];
        $rows[] = [];
        $rows[] = [
            xcell('Total Pendapatan', 'label'), $kosong('label'), $kosong('label'), $kosong('label'),
            xcell('Total Pesanan', 'label'), $kosong('label'), $kosong('label'), $kosong('label'),
        ];
        $rows[] = [
            xcell($total_omzet, 'bigrp'), $kosong('bigrp'), $kosong('bigrp'), $kosong('bigrp'),
            xcell($total_trx . ' Pesanan', 'bigtext'), $kosong('bigtext'), $kosong('bigtext'), $kosong('bigtext'),
        ];
        $rows[] = [];
        $rows[] = [xcell($judul_seksi, 'section')];
        $rows[] = array_map(fn($h) => xcell($h, 'head'), ['No', 'Pembeli', 'No. Telp', 'Alamat', 'Produk', 'Qty', 'Total Harga', 'Diinput Oleh']);

        foreach (array_values($pesanan_list) as $i => $r) {
            $rows[] = [
                xcell($i + 1, 'int'),
                xcell((string)$r['nama_pembeli'], 'cell'),
                xcell((string)($r['no_telp'] ?: '-'), 'cell'),
                xcell((string)($r['alamat'] ?: '-'), 'cell'),
                xcell((string)$r['nama_produk'], 'cell'),
                xcell((int)$r['jumlah'], 'int'),
                xcell((int)$r['total_harga'], 'rp'),
                xcell((string)($r['dibuat_oleh'] ?? '-'), 'cell'),
            ];
        }

        $lebar_kolom = array(8, 22, 16, 28, 24, 8, 18, 18);
        $gabung_sel  = array('A1:H1', 'A2:H2', 'A4:D4', 'E4:H4', 'A5:D5', 'E5:H5', 'A7:H7');
        $xlsx = buildXlsx($rows, array(), 'Data Pesanan', $lebar_kolom, $gabung_sel, false);
        send_download($xlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Data-Pesanan-' . date('Y-m-d_H-i') . '.xlsx');
    }

    if ($export === 'pdf') {
        $headers = array('No', 'Pembeli', 'No. Telp', 'Alamat', 'Produk', 'Qty', 'Total Harga', 'Diinput Oleh');
        $widths  = array(35, 115, 85, 155, 130, 42, 110, 110);
        $rows = [];
        foreach (array_values($pesanan_list) as $i => $r) {
            $rows[] = [
                (string)($i + 1),
                (string)$r['nama_pembeli'],
                (string)($r['no_telp'] ?: '-'),
                (string)($r['alamat'] ?: '-'),
                (string)$r['nama_produk'],
                (string)$r['jumlah'],
                'Rp ' . number_format((int)$r['total_harga'], 0, ',', '.'),
                (string)($r['dibuat_oleh'] ?? '-')
            ];
        }
        $summary = [
            ['Total Pendapatan', 'Rp ' . number_format($total_omzet, 0, ',', '.')],
            ['Total Pesanan', $total_trx . ' Pesanan'],
        ];
        $pdf = buildPdf($judul_lap, $headers, $rows, $widths, null, $summary, $judul_seksi);
        send_download($pdf, 'application/pdf', 'Data-Pesanan-' . date('Y-m-d_H-i') . '.pdf');
    }

    if ($export === 'xls') {
        $filename = "Data-Pesanan-" . date('Y-m-d_H-i') . ".xls";
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        $s_judul = 'font-size:18pt;font-weight:bold;';
        $s_muted = 'font-size:10pt;color:#555555;';
        $s_label = 'background:#f4f7f6;color:#555555;font-size:10pt;font-weight:bold;text-align:left;';
        $s_nilai = 'background:#f4f7f6;font-size:16pt;font-weight:bold;text-align:left;mso-number-format:\@;';
        $s_head  = 'background:#24b47e;color:#ffffff;font-weight:bold;border:1px solid #bbbbbb;padding:6px;text-align:left;';
        $s_sel   = 'border:1px solid #bbbbbb;padding:6px;text-align:left;vertical-align:top;mso-number-format:\@;';
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
        echo '<table style="border-collapse:collapse;font-family:Calibri,Arial,sans-serif;">';
        echo '<tr><td colspan="9" style="' . $s_judul . '">' . htmlspecialchars($judul_lap) . '</td></tr>';
        echo '<tr><td colspan="9" style="' . $s_muted . '">Dicetak: ' . date('d-m-Y H:i') . '</td></tr>';
        echo '<tr><td colspan="9"></td></tr>';
        echo '<tr><td colspan="4" bgcolor="#f4f7f6" style="' . $s_label . '">Total Pendapatan</td><td colspan="5" bgcolor="#f4f7f6" style="' . $s_label . '">Total Pesanan</td></tr>';
        echo '<tr><td colspan="4" bgcolor="#f4f7f6" style="' . $s_nilai . '">Rp ' . number_format($total_omzet, 0, ',', '.') . '</td><td colspan="5" bgcolor="#f4f7f6" style="' . $s_nilai . '">' . $total_trx . ' Pesanan</td></tr>';
        echo '<tr><td colspan="9"></td></tr><tr>';
        foreach (['No', 'Pembeli', 'No. Telp', 'Email', 'Alamat', 'Produk', 'Qty', 'Total Harga', 'Diinput Oleh'] as $h) {
            echo '<th bgcolor="#24b47e" style="' . $s_head . '"><font color="#FFFFFF">' . $h . '</font></th>';
        }
        echo '</tr>';
 
	foreach (array_values($pesanan_list) as $i => $r) {
            echo '<tr>';
            echo '<td style="' . $s_sel . '">' . ($i + 1) . '</td>';
            echo '<td style="' . $s_sel . '">' . htmlspecialchars($r['nama_pembeli']) . '</td>';
            echo '<td style="' . $s_sel . '">' . htmlspecialchars($r['no_telp'] ?: '-') . '</td>';
            echo '<td style="' . $s_sel . '">' . htmlspecialchars($r['email'] ?: '-') . '</td>';
            echo '<td style="' . $s_sel . '">' . htmlspecialchars($r['alamat'] ?: '-') . '</td>';
            echo '<td style="' . $s_sel . '">' . htmlspecialchars($r['nama_produk']) . '</td>';
            echo '<td style="' . $s_sel . '">' . (int)$r['jumlah'] . '</td>';
            echo '<td style="' . $s_sel . '">Rp ' . number_format((int)$r['total_harga'], 0, ',', '.') . '</td>';
            echo '<td style="' . $s_sel . '">' . htmlspecialchars($r['dibuat_oleh'] ?? '-') . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }

    if ($export === 'csv') {
        $filename = "Data-Pesanan-" . date('Y-m-d_H-i') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['No', 'Pembeli', 'No. Telp', 'Email', 'Alamat', 'Produk', 'Qty', 'Total Harga', 'Diinput Oleh', 'Waktu'], ';');
        foreach (array_values($pesanan_list) as $i => $r) {
            fputcsv($output, [
                $i + 1, $r['nama_pembeli'], $r['no_telp'] ?: '-', $r['email'] ?: '-',
                $r['alamat'] ?: '-', $r['nama_produk'], $r['jumlah'], $r['total_harga'],
                $r['dibuat_oleh'] ?? '-', $r['tanggal']
            ], ';');
        }
        fclose($output);
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
        nav { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        nav a { margin-right: 15px; text-decoration: none; font-weight: bold; color: #24b47e; }
        .user-badge { background: #e6f7f0; color: #1c8c61; padding: 5px 10px; border-radius: 5px; font-size: 13px; font-weight: bold; }
        .table-wrapper { overflow-x: auto; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; min-width: 900px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; font-size: 14px; }
        th { background: #24b47e; color: white; }
        .form-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
        .form-grid input, .form-grid select, .form-grid button { padding: 8px; font-size: 14px; }
        button { background: #24b47e; color: white; border: none; cursor: pointer; font-weight: bold; border-radius: 4px; padding: 8px 15px; }
        .btn-batal { background: #d9534f; color: white; padding: 6px 10px; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; }
        .alert-ok { background: #e6f7f0; color: #24b47e; padding: 10px; margin: 15px 0; border-radius: 5px; font-weight: bold; }
        .alert-err { background: #ffe6e6; color: #d93025; padding: 10px; margin: 15px 0; border-radius: 5px; }
        .kontak-info { font-size: 12px; color: #555; margin-top: 4px; }
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

    <h2>Catat Pesanan Baru (<?= htmlspecialchars($admin_nama) ?>)</h2>

    <?php if (isset($_GET['status'])): ?>
        <div class="alert-ok">
            <?php
                if ($_GET['status'] === 'sukses') echo "Pesanan berhasil dicatat atas nama " . htmlspecialchars($admin_nama) . "!";
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

    <h3><?= $is_super ? 'Daftar Semua Pesanan Masuk (Seluruh Admin & Pelanggan)' : 'Daftar Pesanan Inputan ' . htmlspecialchars($admin_nama) ?></h3>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Pembeli & Kontak</th>
                    <th>Alamat</th>
                    <th>Produk</th>
                    <th>Qty</th>
                    <th>Total Harga</th>
                    <th>Diinput Oleh</th>
                    <th>Waktu</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($pesanan_list) > 0): ?>
                    <?php $no = 1; ?>
                    <?php foreach ($pesanan_list as $ps): ?>
                    <tr>
                        <td><?= $no++ ?></td>
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
                        <td><strong><?= htmlspecialchars($ps['dibuat_oleh'] ?? '-') ?></strong></td>
                        <td><?= $ps['tanggal'] ?></td>
                        <td>
                            <a href="pesanan.php?batal=<?= $ps['id'] ?>" class="btn-batal" onclick="return confirm('Batalkan pesanan ini? Stok produk akan otomatis dikembalikan.')">Batalkan</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" style="text-align:center;">Belum ada riwayat pesanan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

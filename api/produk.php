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
$edit_data = null;

// 1. Proses Hapus Produk (Khusus Superadmin)
if (isset($_GET['hapus'])) {
    if (!$is_super) {
        $pesan = "Akses ditolak! Hanya Superadmin yang dapat menghapus produk.";
    } else {
        $id_hapus = (int) $_GET['hapus'];
        try {
            $stmt = $pdo->prepare("DELETE FROM produk WHERE id = :id");
            $stmt->execute([':id' => $id_hapus]);
            header("Location: produk.php?status=dihapus");
            exit;
        } catch (PDOException $e) {
            $pesan = "Gagal menghapus produk (sudah memiliki riwayat transaksi): " . $e->getMessage();
        }
    }
}

// 2. Ambil Data Jika Sedang Mode Edit (Hanya pembuat atau Superadmin)
if (isset($_GET['edit'])) {
    $id_edit = (int) $_GET['edit'];
    if ($is_super) {
        $stmt = $pdo->prepare("SELECT * FROM produk WHERE id = :id");
        $stmt->execute([':id' => $id_edit]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM produk WHERE id = :id AND TRIM(dibuat_oleh) = :oleh");
        $stmt->execute([':id' => $id_edit, ':oleh' => trim($admin_nama)]);
    }
    $edit_data = $stmt->fetch();

    if (!$edit_data) {
        $pesan = "Akses ditolak! Anda hanya dapat mengedit produk yang Anda buat sendiri.";
    }
}

// 3. Proses Simpan (Tambah Baru atau Update Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_produk = trim($_POST['nama_produk']);
    $harga       = (int) $_POST['harga'];
    $stok        = (int) $_POST['stok'];

    try {
        if (!empty($_POST['id_edit'])) {
            $id_edit = (int) $_POST['id_edit'];
            if ($is_super) {
                $stmt = $pdo->prepare("UPDATE produk SET nama_produk = :nama, harga = :harga, stok = :stok WHERE id = :id");
                $stmt->execute([
                    ':nama'  => $nama_produk,
                    ':harga' => $harga,
                    ':stok'  => $stok,
                    ':id'    => $id_edit
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE produk SET nama_produk = :nama, harga = :harga, stok = :stok WHERE id = :id AND TRIM(dibuat_oleh) = :oleh");
                $stmt->execute([
                    ':nama'  => $nama_produk,
                    ':harga' => $harga,
                    ':stok'  => $stok,
                    ':id'    => $id_edit,
                    ':oleh'  => trim($admin_nama)
                ]);
            }
            header("Location: produk.php?status=diubah");
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO produk (nama_produk, harga, stok, dibuat_oleh) VALUES (:nama, :harga, :stok, :oleh)");
            $stmt->execute([
                ':nama'  => $nama_produk,
                ':harga' => $harga,
                ':stok'  => $stok,
                ':oleh'  => trim($admin_nama)
            ]);
            header("Location: produk.php?status=sukses");
            exit;
        }
    } catch (PDOException $e) {
        $pesan = "Terjadi kesalahan: " . $e->getMessage();
    }
}

$produk_list = $pdo->query("SELECT * FROM produk ORDER BY id DESC")->fetchAll();
$export = $_GET['export'] ?? '';

if ($export !== '') {
    $total_jenis = count($produk_list);
    $total_stok  = 0;
    $total_aset  = 0;
    foreach ($produk_list as $p) {
        $total_stok += (int)$p['stok'];
        $total_aset += ((int)$p['harga'] * (int)$p['stok']);
    }

    // 1. Download .XLSX
    if ($export === 'xlsx') {
        $kosong = fn(string $gaya) => xcell('', $gaya);
        $rows = [];
        $rows[] = [xcell('Laporan Stok Produk - Toko Gilang', 'title')];
        $rows[] = [xcell('Dicetak: ' . date('d-m-Y H:i'), 'muted')];
        $rows[] = [];
        $rows[] = [
            xcell('Total Nilai Aset', 'label'), $kosong('label'), $kosong('label'),
            xcell('Total Sisa Stok', 'label'), $kosong('label'), $kosong('label'),
        ];
        $rows[] = [
            xcell($total_aset, 'bigrp'), $kosong('bigrp'), $kosong('bigrp'),
            xcell($total_stok . ' Unit (' . $total_jenis . ' Produk)', 'bigtext'), $kosong('bigtext'), $kosong('bigtext'),
        ];
        $rows[] = [];
        $rows[] = [xcell('Daftar Stok Produk', 'section')];
        $rows[] = array_map(fn($h) => xcell($h, 'head'), ['No', 'Nama Produk', 'Harga', 'Stok', 'Nilai Aset', 'Dibuat Oleh']);

        foreach (array_values($produk_list) as $i => $p) {
            $nilai = (int)$p['harga'] * (int)$p['stok'];
            $rows[] = [
                xcell($i + 1, 'int'),
                xcell((string)$p['nama_produk'], 'cell'),
                xcell((int)$p['harga'], 'rp'),
                xcell((int)$p['stok'], 'int'),
                xcell($nilai, 'rp'),
                xcell((string)($p['dibuat_oleh'] ?? '-'), 'cell'),
            ];
        }

        $lebar_kolom = array(8, 32, 18, 12, 20, 22);
        $gabung_sel  = array('A1:F1', 'A2:F2', 'A4:C4', 'D4:F4', 'A5:C5', 'D5:F5', 'A7:F7');
        $xlsx = buildXlsx($rows, array(), 'Data Produk', $lebar_kolom, $gabung_sel, false);
        send_download($xlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Laporan-Produk-' . date('Y-m-d_H-i') . '.xlsx');
    }

    // 2. Download .PDF
    if ($export === 'pdf') {
        $headers = array('No', 'Nama Produk', 'Harga', 'Stok', 'Nilai Aset', 'Dibuat Oleh');
        $widths  = array(40, 240, 120, 62, 140, 180);
        $rows = [];
        foreach (array_values($produk_list) as $i => $p) {
            $nilai = (int)$p['harga'] * (int)$p['stok'];
            $rows[] = [
                (string)($i + 1),
                (string)$p['nama_produk'],
                'Rp ' . number_format((int)$p['harga'], 0, ',', '.'),
                (string)$p['stok'],
                'Rp ' . number_format($nilai, 0, ',', '.'),
                (string)($p['dibuat_oleh'] ?? '-')
            ];
        }
        $summary = [
            ['Total Nilai Aset', 'Rp ' . number_format($total_aset, 0, ',', '.')],
            ['Total Sisa Stok', $total_stok . ' Unit (' . $total_jenis . ' Produk)'],
        ];
        $pdf = buildPdf('Laporan Stok Produk - Toko Gilang', $headers, $rows, $widths, null, $summary, 'Daftar Stok Produk');
        send_download($pdf, 'application/pdf', 'Laporan-Produk-' . date('Y-m-d_H-i') . '.pdf');
    }

    // 3. Download .XLS
    if ($export === 'xls') {
        $filename = "Laporan-Produk-" . date('Y-m-d_H-i') . ".xls";
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        $s_judul = 'font-size:18pt;font-weight:bold;';
        $s_muted = 'font-size:10pt;color:#555555;';
        $s_label = 'background:#f4f7f6;color:#555555;font-size:10pt;font-weight:bold;text-align:left;';
        $s_nilai = 'background:#f4f7f6;font-size:16pt;font-weight:bold;text-align:left;mso-number-format:\@;';
        $s_head  = 'background:#24b47e;color:#ffffff;font-weight:bold;border:1px solid #bbbbbb;padding:6px;text-align:left;';
        $s_sel   = 'border:1px solid #bbbbbb;padding:6px;text-align:left;mso-number-format:\@;';
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
        echo '<table style="border-collapse:collapse;font-family:Calibri,Arial,sans-serif;">';
        echo '<tr><td colspan="6" style="' . $s_judul . '">Laporan Stok Produk - Toko Gilang</td></tr>';
        echo '<tr><td colspan="6" style="' . $s_muted . '">Dicetak: ' . date('d-m-Y H:i') . '</td></tr>';
        echo '<tr><td colspan="6"></td></tr>';
        echo '<tr><td colspan="3" bgcolor="#f4f7f6" style="' . $s_label . '">Total Nilai Aset</td><td colspan="3" bgcolor="#f4f7f6" style="' . $s_label . '">Total Sisa Stok</td></tr>';
        echo '<tr><td colspan="3" bgcolor="#f4f7f6" style="' . $s_nilai . '">Rp ' . number_format($total_aset, 0, ',', '.') . '</td><td colspan="3" bgcolor="#f4f7f6" style="' . $s_nilai . '">' . $total_stok . ' Unit (' . $total_jenis . ' Produk)</td></tr>';
        echo '<tr><td colspan="6"></td></tr><tr>';
        foreach (['No', 'Nama Produk', 'Harga', 'Stok', 'Nilai Aset', 'Dibuat Oleh'] as $h) {
            echo '<th bgcolor="#24b47e" style="' . $s_head . '"><font color="#FFFFFF">' . $h . '</font></th>';
        }
        echo '</tr>';
        foreach (array_values($produk_list) as $i => $p) {
            $nilai = (int)$p['harga'] * (int)$p['stok'];
            echo '<tr>';
            echo '<td style="' . $s_sel . '">' . ($i + 1) . '</td>';
            echo '<td style="' . $s_sel . '">' . htmlspecialchars($p['nama_produk']) . '</td>';
            echo '<td style="' . $s_sel . '">Rp ' . number_format((int)$p['harga'], 0, ',', '.') . '</td>';
            echo '<td style="' . $s_sel . '">' . (int)$p['stok'] . '</td>';
            echo '<td style="' . $s_sel . '">Rp ' . number_format($nilai, 0, ',', '.') . '</td>';
            echo '<td style="' . $s_sel . '">' . htmlspecialchars($p['dibuat_oleh'] ?? '-') . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }

    // 4. Download .CSV
    if ($export === 'csv') {
        $filename = "Laporan-Produk-" . date('Y-m-d_H-i') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['No', 'Nama Produk', 'Harga', 'Stok', 'Nilai Aset', 'Dibuat Oleh'], ';');
        foreach (array_values($produk_list) as $i => $p) {
            $nilai = (int)$p['harga'] * (int)$p['stok'];
            fputcsv($output, [$i + 1, $p['nama_produk'], $p['harga'], $p['stok'], $nilai, $p['dibuat_oleh'] ?? '-'], ';');
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
    <title>Kelola Produk - Toko Gilang</title>
    <style>
        body { font-family: sans-serif; margin: 20px 40px; }
        nav { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        nav a { margin-right: 15px; text-decoration: none; font-weight: bold; color: #24b47e; }
        .user-badge { background: #e6f7f0; color: #1c8c61; padding: 5px 10px; border-radius: 5px; font-size: 13px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #24b47e; color: white; }
        form input, button { padding: 8px; margin-right: 5px; }
        button { background: #24b47e; color: white; border: none; cursor: pointer; font-weight: bold; border-radius: 4px; }
        .btn-batal { background: #888; color: white; padding: 8px 12px; text-decoration: none; font-size: 13px; border-radius: 4px; }
        .btn-aksi { padding: 5px 10px; text-decoration: none; color: white; border-radius: 4px; font-size: 12px; margin-right: 4px; }
        .btn-edit { background: #f0ad4e; }
        .btn-edit-aktif { background: #d9534f; }
        .btn-hapus { background: #d9534f; }
        .alert-ok { background: #e6f7f0; color: #24b47e; padding: 10px; margin: 15px 0; border-radius: 5px; }
        .alert-err { background: #ffe6e6; color: #d93025; padding: 10px; margin: 15px 0; border-radius: 5px; }
.header-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin: 20px 0 10px; }
.export-group { display: flex; flex-wrap: wrap; gap: 8px; }
.btn-exp { color: white; padding: 8px 12px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; }
.btn-xlsx { background: #107c41; } .btn-xlsx:hover { background: #0b5a2f; }
.btn-xls { background: #24b47e; } .btn-xls:hover { background: #1c8c61; }
.btn-csv { background: #4b5563; } .btn-csv:hover { background: #374151; }
.btn-pdf { background: #dc2626; } .btn-pdf:hover { background: #b91c1c; }
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

    <?php if (isset($_GET['status'])): ?>
        <div class="alert-ok">
            <?php
                if ($_GET['status'] === 'sukses') echo "Produk baru berhasil ditambahkan!";
                if ($_GET['status'] === 'diubah') echo "Data produk berhasil diperbarui!";
                if ($_GET['status'] === 'dihapus') echo "Produk berhasil dihapus!";
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($pesan)): ?>
        <div class="alert-err"><?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>

    <form method="POST" action="produk.php">
        <input type="hidden" name="id_edit" value="<?= $edit_data ? $edit_data['id'] : '' ?>">
        <input type="text" name="nama_produk" placeholder="Nama Produk" value="<?= $edit_data ? htmlspecialchars($edit_data['nama_produk']) : '' ?>" required>
        <input type="number" name="harga" placeholder="Harga (Rp)" min="0" value="<?= $edit_data ? $edit_data['harga'] : '' ?>" required>
        <input type="number" name="stok" placeholder="Stok" min="0" value="<?= $edit_data ? $edit_data['stok'] : '' ?>" required>
        <button type="submit" style="<?= $edit_data ? 'background:#f0ad4e;' : '' ?>">
            <?= $edit_data ? 'Simpan Perubahan' : 'Tambah Produk' ?>
        </button>
        <?php if ($edit_data): ?>
            <a href="produk.php" class="btn-batal">Batal</a>
        <?php endif; ?>
    </form>

      <div class="header-bar">
        <h3 style="margin:0;">Daftar Stok Produk</h3>
        <div class="export-group">
            <a href="produk.php?export=xlsx" class="btn-exp btn-xlsx">📊 Excel (.xlsx)</a>
            <a href="produk.php?export=xls" class="btn-exp btn-xls">📗 Excel (.xls)</a>
            <a href="produk.php?export=csv" class="btn-exp btn-csv">📄 CSV (.csv)</a>
            <a href="produk.php?export=pdf" class="btn-exp btn-pdf">📕 PDF (.pdf)</a>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Produk</th>
                <th>Harga</th>
                <th>Stok</th>
                <th>Dibuat Oleh</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($produk_list) > 0): ?>
                <?php $no = 1; ?>
                <?php foreach ($produk_list as $p): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($p['nama_produk']) ?></td>
                    <td>Rp <?= number_format($p['harga'], 0, ',', '.') ?></td>
                    <td><?= $p['stok'] ?></td>
                    <td><strong><?= htmlspecialchars($p['dibuat_oleh'] ?? '-') ?></strong></td>
                   <td>
                        <?php if ($is_super || ($p['dibuat_oleh'] ?? '') === $admin_nama): ?>
                            <a href="produk.php?edit=<?= $p['id'] ?>" class="btn-aksi btn-edit">Edit</a>
                        <?php else: ?>
                            <span style="color:#888; font-size:12px;">Hanya Lihat</span>
                        <?php endif; ?>

                        <?php if ($is_super): ?>
                            <a href="produk.php?hapus=<?= $p['id'] ?>" class="btn-aksi btn-hapus" onclick="return confirm('Yakin ingin menghapus produk ini?')">Hapus</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center;">Belum ada data produk.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>

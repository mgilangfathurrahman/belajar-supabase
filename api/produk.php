<?php
if (!isset($_COOKIE['admin_logged_in']) || $_COOKIE['admin_logged_in'] !== 'true') {
    header("Location: login.php");
    exit;
}

require 'koneksi.php';

$pesan = "";
$edit_data = null;

// 1. Proses Hapus Produk
if (isset($_GET['hapus'])) {
    $id_hapus = (int) $_GET['hapus'];
    try {
        $stmt = $pdo->prepare("DELETE FROM produk WHERE id = :id");
        $stmt->execute([':id' => $id_hapus]);
        header("Location: produk.php?status=dihapus");
        exit;
    } catch (PDOException $e) {
        $pesan = "Gagal menghapus produk: " . $e->getMessage();
    }
}

// 2. Ambil Data Jika Sedang Mode Edit
if (isset($_GET['edit'])) {
    $id_edit = (int) $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM produk WHERE id = :id");
    $stmt->execute([':id' => $id_edit]);
    $edit_data = $stmt->fetch();
}

// 3. Proses Simpan (Tambah Baru atau Update Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_produk = trim($_POST['nama_produk']);
    $harga       = (int) $_POST['harga'];
    $stok        = (int) $_POST['stok'];

    try {
        if (!empty($_POST['id_edit'])) {
            // Update Produk Lama
            $id_edit = (int) $_POST['id_edit'];
            $stmt = $pdo->prepare("UPDATE produk SET nama_produk = :nama, harga = :harga, stok = :stok WHERE id = :id");
            $stmt->execute([
                ':nama'  => $nama_produk,
                ':harga' => $harga,
                ':stok'  => $stok,
                ':id'    => $id_edit
            ]);
            header("Location: produk.php?status=diubah");
            exit;
        } else {
            // Tambah Produk Baru
            $stmt = $pdo->prepare("INSERT INTO produk (nama_produk, harga, stok) VALUES (:nama, :harga, :stok)");
            $stmt->execute([
                ':nama'  => $nama_produk,
                ':harga' => $harga,
                ':stok'  => $stok
            ]);
            header("Location: produk.php?status=sukses");
            exit;
        }
    } catch (PDOException $e) {
        $pesan = "Terjadi kesalahan: " . $e->getMessage();
    }
}

$produk_list = $pdo->query("SELECT * FROM produk ORDER BY id DESC")->fetchAll();

// 4. Download data produk: ?export=xlsx | xls | pdf | csv
$export = $_GET['export'] ?? '';
if (in_array($export, ['xlsx', 'xls', 'pdf', 'csv'], true)) {
    require_once __DIR__ . '/export_helpers.php';

    $total_produk = count($produk_list);
    $total_stok   = 0;
    foreach ($produk_list as $r) {
        $total_stok += (int)$r['stok'];
    }
    $judul_tabel = 'Daftar Semua Produk';
    $headers     = ['No', 'Nama Produk', 'Harga', 'Stok'];
    $stamp       = date('Y-m-d_H-i');

    // ---------- XLSX ----------
    if ($export === 'xlsx') {
        $kosong = fn(string $gaya) => xcell('', $gaya);

        $rows = [];
        $rows[] = [xcell('Data Produk - Toko Gilang', 'title')];
        $rows[] = [xcell('Dicetak: ' . date('d-m-Y H:i'), 'muted')];
        $rows[] = [];
        $rows[] = [
            xcell('Total Produk', 'label'), $kosong('label'),
            xcell('Total Stok', 'label'), $kosong('label'),
        ];
        $rows[] = [
            xcell($total_produk . ' Produk', 'bigtext'), $kosong('bigtext'),
            xcell($total_stok . ' Unit', 'bigtext'), $kosong('bigtext'),
        ];
        $rows[] = [];
        $rows[] = [xcell($judul_tabel, 'section')];
        $rows[] = array_map(fn($h) => xcell($h, 'head'), $headers);

        foreach (array_values($produk_list) as $i => $r) {
            $rows[] = [
                xcell($i + 1, 'int'),
                xcell((string)$r['nama_produk'], 'cell'),
                xcell((int)$r['harga'], 'rp'),
                xcell((int)$r['stok'], 'int'),
            ];
        }

        $xlsx = buildXlsx(
            $rows,
            [],
            'Data Produk',
            [8, 42, 20, 14],
            ['A1:D1', 'A2:D2', 'A4:B4', 'C4:D4', 'A5:B5', 'C5:D5', 'A7:D7'],
            false
        );
        send_download(
            $xlsx,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Data-Produk-Toko-Gilang-' . $stamp . '.xlsx'
        );
    }

    // ---------- PDF ----------
    if ($export === 'pdf') {
        $widths = [60, 360, 180, 182]; // total 782 pt (A4 landscape)

        $rows = [];
        foreach (array_values($produk_list) as $i => $r) {
            $rows[] = [
                (string)($i + 1),
                (string)$r['nama_produk'],
                'Rp ' . number_format((int)$r['harga'], 0, ',', '.'),
                (string)$r['stok'],
            ];
        }
        $summary = [
            ['Total Produk', $total_produk . ' Produk'],
            ['Total Stok', $total_stok . ' Unit'],
        ];

        $pdf = buildPdf('Data Produk - Toko Gilang', $headers, $rows, $widths, null, $summary, $judul_tabel);
        send_download($pdf, 'application/pdf', 'Data-Produk-' . $stamp . '.pdf');
    }

    // ---------- CSV ----------
    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Data-Produk-' . $stamp . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
        fputcsv($out, $headers, ';');
        foreach (array_values($produk_list) as $i => $r) {
            fputcsv($out, [$i + 1, $r['nama_produk'], $r['harga'], $r['stok']], ';');
        }
        fclose($out);
        exit;
    }

    // ---------- XLS (HTML table, semua gaya inline agar terbaca Excel) ----------
    if ($export === 'xls') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="Data-Produk-' . $stamp . '.xls"');

        $s_judul = 'font-size:18pt;font-weight:bold;';
        $s_muted = 'font-size:10pt;color:#555555;';
        $s_label = 'background:#f4f7f6;color:#555555;font-size:10pt;font-weight:bold;text-align:left;';
        $s_nilai = 'background:#f4f7f6;font-size:16pt;font-weight:bold;text-align:left;mso-number-format:\@;';
        $s_seksi = 'font-size:13pt;font-weight:bold;';
        $s_head  = 'background:#24b47e;color:#ffffff;font-weight:bold;border:1px solid #bbbbbb;padding:6px;text-align:left;vertical-align:middle;white-space:nowrap;';
        $s_sel   = 'border:1px solid #bbbbbb;padding:6px;text-align:left;vertical-align:top;white-space:nowrap;mso-number-format:\@;';
        ?>
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
                <x:Name>Data Produk</x:Name>
                <x:WorksheetOptions><x:DisplayGridlines>false</x:DisplayGridlines></x:WorksheetOptions>
            </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
        </head>
        <body>
            <table style="border-collapse:collapse;font-family:Calibri,Arial,sans-serif;">
                <colgroup>
                    <col width="60"><col width="320"><col width="160"><col width="100">
                </colgroup>
                <tr><td colspan="4" style="<?= $s_judul ?>">Data Produk - Toko Gilang</td></tr>
                <tr><td colspan="4" style="<?= $s_muted ?>">Dicetak: <?= date('d-m-Y H:i') ?></td></tr>
                <tr><td colspan="4"></td></tr>
                <tr>
                    <td colspan="2" bgcolor="#f4f7f6" style="<?= $s_label ?>">Total Produk</td>
                    <td colspan="2" bgcolor="#f4f7f6" style="<?= $s_label ?>">Total Stok</td>
                </tr>
                <tr>
                    <td colspan="2" bgcolor="#f4f7f6" style="<?= $s_nilai ?>"><?= $total_produk ?> Produk</td>
                    <td colspan="2" bgcolor="#f4f7f6" style="<?= $s_nilai ?>"><?= $total_stok ?> Unit</td>
                </tr>
                <tr><td colspan="4"></td></tr>
                <tr><td colspan="4" style="<?= $s_seksi ?>"><?= htmlspecialchars($judul_tabel) ?></td></tr>
                <tr>
                    <?php foreach ($headers as $h): ?>
                    <th bgcolor="#24b47e" style="<?= $s_head ?>"><font color="#FFFFFF"><?= htmlspecialchars($h) ?></font></th>
                    <?php endforeach; ?>
                </tr>
                <?php foreach (array_values($produk_list) as $i => $r): ?>
                <tr>
                    <td style="<?= $s_sel ?>"><?= $i + 1 ?></td>
                    <td style="<?= $s_sel ?>"><?= htmlspecialchars($r['nama_produk']) ?></td>
                    <td style="<?= $s_sel ?>">Rp <?= number_format((int)$r['harga'], 0, ',', '.') ?></td>
                    <td style="<?= $s_sel ?>"><?= (int)$r['stok'] ?></td>
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
    <title>Kelola Produk - Toko Gilang</title>
    <style>
        body { font-family: sans-serif; margin: 20px 40px; }
        nav a { margin-right: 15px; text-decoration: none; font-weight: bold; color: #24b47e; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #24b47e; color: white; }
        form input, button { padding: 8px; margin-right: 5px; }
        button { background: #24b47e; color: white; border: none; cursor: pointer; font-weight: bold; }
        .btn-batal { background: #888; color: white; padding: 8px 12px; text-decoration: none; font-size: 13px; }
        .btn-aksi { padding: 5px 10px; text-decoration: none; color: white; border-radius: 4px; font-size: 12px; margin-right: 4px; }
        .btn-edit { background: #f0ad4e; }
        .btn-hapus { background: #d9534f; }
        .alert-ok { background: #e6f7f0; color: #24b47e; padding: 10px; margin: 15px 0; border-radius: 5px; }
        .alert-err { background: #ffe6e6; color: #d93025; padding: 10px; margin: 15px 0; border-radius: 5px; }

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
        <a href="logout.php" style="color:red;">Logout</a>
    </nav>
    <hr>

    <h2><?= $edit_data ? 'Edit Data Produk' : 'Tambah Produk Baru' ?></h2>

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
        <h3 style="margin:0;">Daftar Produk</h3>
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
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($produk_list) > 0): ?>
                <?php foreach (array_values($produk_list) as $no => $p): ?>
                <tr>
                    <td><?= $no + 1 ?></td>
                    <td><?= htmlspecialchars($p['nama_produk']) ?></td>
                    <td>Rp <?= number_format($p['harga'], 0, ',', '.') ?></td>
                    <td><?= $p['stok'] ?></td>
                    <td>
                        <a href="produk.php?edit=<?= $p['id'] ?>" class="btn-aksi btn-edit">Edit</a>
                        <a href="produk.php?hapus=<?= $p['id'] ?>" class="btn-aksi btn-hapus" onclick="return confirm('Yakin ingin menghapus produk ini?')">Hapus</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center;">Belum ada data produk.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>

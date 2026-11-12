<?php
if (!isset($_COOKIE['admin_logged_in']) || $_COOKIE['admin_logged_in'] !== 'true') {
    header("Location: login.php");
    exit;
}

require 'koneksi.php';
require_once __DIR__ . '/export_helpers.php';

$pesan = "";

// 1. Proses Batalkan / Hapus Pesanan & Kembalikan Stok
if (isset($_GET['batal'])) {
    $id_pesanan = (int) $_GET['batal'];
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT produk_id, jumlah FROM pesanan WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $id_pesanan]);
        $data_pesanan = $stmt->fetch();

        if ($data_pesanan) {
            $upd = $pdo->prepare("UPDATE produk SET stok = stok + :jml WHERE id = :pid");
            $upd->execute([
                ':jml' => $data_pesanan['jumlah'],
                ':pid' => $data_pesanan['produk_id']
            ]);

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

// 3. Ambil Daftar Produk & Riwayat Pesanan Lengkap
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

// 4. Handler Download 4 Format (XLSX, PDF, XLS, CSV)
$export = $_GET['export'] ?? '';
if ($export !== '') {
    $grand_total = 0;
    foreach ($pesanan_list as $ps) {
        $grand_total += (int)$ps['total_harga'];
    }

    if ($export === 'xlsx') {
        $rows = [];
        $rows[] = [xcell('Data Lengkap Pesanan - Toko Gilang', 'title')];
        $rows[] = [xcell('Dicetak: ' . date('d-m-Y H:i'), 'muted')];
        $rows[] = [];
        $rows[] = array_map(fn($h) => xcell($h, 'head'), ['ID', 'Nama Pembeli', 'No. Telepon', 'Email', 'Alamat Pengiriman', 'Produk', 'Qty', 'Total Harga', 'Waktu']);

        foreach ($pesanan_list as $r) {
            $rows[] = [
                xcell((int)$r['id'], 'int'),
                xcell((string)$r['nama_pembeli'], 'cell'),
                xcell((string)($r['no_telp'] ?: '-'), 'cell'),
                xcell((string)($r['email'] ?: '-'), 'cell'),
                xcell((string)($r['alamat'] ?: '-'), 'cell'),
                xcell((string)$r['nama_produk'], 'cell'),
                xcell((int)$r['jumlah'], 'int'),
                xcell((int)$r['total_harga'], 'rp'),
                xcell(substr((string)$r['tanggal'], 0, 19), 'cell'),
            ];
        }
        $rows[] = [
            xcell('', 'cell'), xcell('', 'cell'), xcell('', 'cell'), xcell('', 'cell'),
            xcell('', 'cell'), xcell('', 'cell'), xcell('TOTAL', 'head'),
            xcell($grand_total, 'rp'), xcell('', 'cell')
        ];

        $lebar_kolom = array(8, 22, 16, 24, 30, 24, 8, 18, 20);
        $gabung_sel  = array('A1:I1', 'A2:I2');
        $xlsx = buildXlsx($rows, array(), 'Data Pesanan', $lebar_kolom, $gabung_sel, false);
        send_download($xlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Data-Pesanan-' . date('Y-m-d_H-i') . '.xlsx');
    }

    if ($export === 'pdf') {
        $headers = array('ID', 'Pembeli', 'No. Telp', 'Alamat', 'Produk', 'Qty', 'Total Harga', 'Waktu');
        $widths  = array(35, 110, 85, 150, 125, 42, 105, 130);
        $rows = [];
        foreach ($pesanan_list as $r) {
            $rows[] = [
                (string)$r['id'],
                (string)$r['nama_pembeli'],
                (string)($r['no_telp'] ?: '-'),
                (string)($r['alamat'] ?: '-'),
                (string)$r['nama_produk'],
                (string)$r['jumlah'],
                'Rp ' . number_format((int)$r['total_harga'], 0, ',', '.'),
                substr((string)$r['tanggal'], 0, 19)
            ];
        }
        $totalRow = array('', '', '', '', '', 'TOTAL', 'Rp ' . number_format($grand_total, 0, ',', '.'), '');
        $pdf = buildPdf('Data Lengkap Pesanan - Toko Gilang', $headers, $rows, $widths, $totalRow);
        send_download($pdf, 'application/pdf', 'Data-Pesanan-' . date('Y-m-d_H-i') . '.pdf');
    }

    if ($export === 'xls') {
        $filename = 'Data-Pesanan-' . date('Y-m-d_H-i') . '.xls';
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        $s_head = 'background:#24b47e;color:#ffffff;font-weight:bold;border:1px solid #bbbbbb;padding:6px;text-align:left;';
        $s_sel  = 'border:1px solid #bbbbbb;padding:6px;text-align:left;vertical-align:top;mso-number-format:\@;';
        echo '<html><head><meta charset="utf-8"></head><body>';
        echo '<h2>Data Lengkap Pesanan - Toko Gilang</h2><p>Dicetak: ' . date('d-m-Y H:i') . '</p>';
        echo '<table style="border-collapse:collapse;font-family:Calibri,Arial,sans-serif;"><tr>';
        foreach (['ID', 'Nama Pembeli', 'No. Telepon', 'Email', 'Alamat Pengiriman', 'Produk', 'Qty', 'Total Harga', 'Waktu'] as $h) {
            echo '<th bgcolor="#24b47e" style="' . $s_head . '">' . $h . '</th>';
        }
        echo '</tr>';
        foreach ($pesanan_list as $r) {
            echo '<tr>';
            echo '<td style="' . $s_sel . '">' . (int)$r['id'] . '</td>';
            echo '<td style="' . $s_sel . '">' . htmlspecialchars($r['nama_pembeli']) . '</td>';
            echo '<td style="' . $s_sel . '">' . htmlspecialchars($r['no_telp'] ?: '-') . '</td>';
            echo '<td style="' . $s_sel . '">' . htmlspecialchars($r['email'] ?: '-') . '</td>';
            echo '<td style="' . $s_sel . '">' . htmlspecialchars($r['alamat'] ?: '-') . '</td>';
            echo '<td style="' . $s_sel . '">' . htmlspecialchars($r['nama_produk']) . '</td>';
            echo '<td style="' . $s_sel . '">' . (int)$r['jumlah'] . '</td>';
            echo '<td style="' . $s_sel . '">Rp ' . number_format((int)$r['total_harga'], 0, ',', '.') . '</td>';
            echo '<td style="' . $s_sel . '">' . htmlspecialchars(substr((string)$r['tanggal'], 0, 19)) . '</td>';
            echo '</tr>';
        }
        echo '<tr><td colspan="7" style="' . $s_sel . 'font-weight:bold;text-align:right;">TOTAL PENDAPATAN</td>';
        echo '<td style="' . $s_sel . 'font-weight:bold;">Rp ' . number_format($grand_total, 0, ',', '.') . '</td><td style="' . $s_sel . '"></td></tr>';
        echo '</table></body></html>';
        exit;
    }

    if ($export === 'csv') {
        $filename = 'Data-Pesanan-' . date('Y-m-d_H-i') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['ID', 'Nama Pembeli', 'No. Telepon', 'Email', 'Alamat Pengiriman', 'Produk', 'Qty', 'Total Harga', 'Waktu'], ';');
        foreach ($pesanan_list as $r) {
            fputcsv($output, [
                $r['id'], $r['nama_pembeli'], $r['no_telp'] ?: '-', $r['email'] ?: '-',
                $r['alamat'] ?: '-', $r['nama_produk'], $r['jumlah'], $r['total_harga'], $r['tanggal']
            ], ';');
        }
        fclose($output);
        exit;
    }
}
?>

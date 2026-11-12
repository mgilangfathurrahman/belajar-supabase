<?php
header('Content-Type: application/json');
require 'koneksi.php';

$admin_nama = $_COOKIE['admin_nama'] ?? ($_COOKIE['admin_username'] ?? 'Admin');
$admin_role = $_COOKIE['admin_role'] ?? 'admin';
$is_super   = ($admin_role === 'superadmin');

try {
    if ($is_super) {
        $sql_stat = "SELECT COALESCE(SUM(total_harga), 0) AS total_omzet, COUNT(id) AS total_transaksi FROM pesanan";
        $stat = $pdo->query($sql_stat)->fetch();

        $sql_semua = "SELECT pesanan.id, pesanan.nama_pembeli, produk.nama_produk, pesanan.jumlah, pesanan.total_harga, pesanan.tanggal, pesanan.dibuat_oleh 
                      FROM pesanan 
                      JOIN produk ON pesanan.produk_id = produk.id 
                      ORDER BY pesanan.id DESC";
        $semua = $pdo->query($sql_semua)->fetchAll();
    } else {
        $sql_stat = "SELECT COALESCE(SUM(total_harga), 0) AS total_omzet, COUNT(id) AS total_transaksi FROM pesanan WHERE dibuat_oleh = :oleh";
        $stmt_stat = $pdo->prepare($sql_stat);
        $stmt_stat->execute([':oleh' => $admin_nama]);
        $stat = $stmt_stat->fetch();

        $sql_semua = "SELECT pesanan.id, pesanan.nama_pembeli, produk.nama_produk, pesanan.jumlah, pesanan.total_harga, pesanan.tanggal, pesanan.dibuat_oleh 
                      FROM pesanan 
                      JOIN produk ON pesanan.produk_id = produk.id 
                      WHERE pesanan.dibuat_oleh = :oleh 
                      ORDER BY pesanan.id DESC";
        $stmt_semua = $pdo->prepare($sql_semua);
        $stmt_semua->execute([':oleh' => $admin_nama]);
        $semua = $stmt_semua->fetchAll();
    }

    echo json_encode([
        'total_omzet'     => (int) $stat['total_omzet'],
        'total_transaksi' => (int) $stat['total_transaksi'],
        'terbaru'         => $semua
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

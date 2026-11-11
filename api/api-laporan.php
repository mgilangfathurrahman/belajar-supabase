<?php
header('Content-Type: application/json');
require 'koneksi.php';

try {
    // Hitung total omzet & jumlah transaksi
    $ringkasan = $pdo->query("SELECT COALESCE(SUM(total_harga), 0) AS total_omzet, COUNT(id) AS total_transaksi FROM pesanan")->fetch();
    
    // Ambil 5 transaksi terbaru
    $transaksi = $pdo->query("SELECT pesanan.id, pesanan.nama_pembeli, produk.nama_produk, pesanan.jumlah, pesanan.total_harga, pesanan.tanggal 
                              FROM pesanan JOIN produk ON pesanan.produk_id = produk.id 
                              ORDER BY pesanan.id DESC LIMIT 5")->fetchAll();

    echo json_encode([
        'status' => 'sukses',
        'ringkasan' => $ringkasan,
        'transaksi' => $transaksi
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'pesan' => $e->getMessage()]);
}
?>

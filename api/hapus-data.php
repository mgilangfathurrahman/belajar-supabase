<?php
// 1. Hubungkan dengan file koneksi
require 'koneksi.php';

// 2. Cek apakah ada parameter 'id' yang dikirim melalui URL
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        // 3. Siapkan query DELETE menggunakan prepared statement
        $sql = "DELETE FROM produk WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        
        // 4. Eksekusi query dengan mengikat ID
        $stmt->execute([':id' => $id]);

        // 5. Jika berhasil, langsung arahkan kembali ke halaman tampil-data.php
        header("Location: tampil-data.php");
        exit();

    } catch (PDOException $e) {
        echo "Gagal menghapus data: " . $e->getMessage();
    }
} else {
    // Jika tidak ada ID di URL, kembalikan ke halaman utama
    header("Location: tampil-data.php");
    exit();
}
?>

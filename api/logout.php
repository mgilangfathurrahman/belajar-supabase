<?php
// 1. Mulai atau panggil sesi yang sedang berjalan
session_start();

// 2. Kosongkan semua variabel sesi yang tersimpan
$_SESSION = [];

// 3. Hapus sesi secara permanen dari server
session_destroy();

// 4. Arahkan pengguna kembali ke halaman katalog produk publik
header("Location: index.php");
exit;
?>

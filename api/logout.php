<?php
// Hapus cookie pada path root (/) dan path default
setcookie('admin_logged_in', '', time() - 3600, '/');
setcookie('admin_logged_in', '', time() - 3600);

// Bersihkan variabel superglobal $_COOKIE pada eksekusi saat ini
unset($_COOKIE['admin_logged_in']);

// Arahkan kembali ke halaman login dengan pesan status
header("Location: login.php?status=logout");
exit;

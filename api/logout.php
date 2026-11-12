<?php
// Hapus kedua cookie sesi admin secara menyeluruh pada path root (/)
setcookie('admin_logged_in', '', time() - 3600, '/');
setcookie('admin_username', '', time() - 3600, '/');

// Cadangan hapus tanpa parameter path
setcookie('admin_logged_in', '', time() - 3600);
setcookie('admin_username', '', time() - 3600);

// Bersihkan variabel $_COOKIE pada eksekusi saat ini
unset($_COOKIE['admin_logged_in']);
unset($_COOKIE['admin_username']);

// Arahkan kembali ke halaman login
header("Location: login.php?status=logout");
exit;

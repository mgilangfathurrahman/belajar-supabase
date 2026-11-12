<?php
setcookie('admin_logged_in', '', time() - 3600, '/');
setcookie('admin_username', '', time() - 3600, '/');
setcookie('admin_nama', '', time() - 3600, '/');
setcookie('admin_role', '', time() - 3600, '/');

unset($_COOKIE['admin_logged_in']);
unset($_COOKIE['admin_username']);
unset($_COOKIE['admin_nama']);
unset($_COOKIE['admin_role']);

header("Location: login.php?status=logout");
exit;

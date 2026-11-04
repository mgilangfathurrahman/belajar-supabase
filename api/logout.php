<?php
session_start();
session_unset();
session_destroy();
header("Location: tampil-data.php");
exit;
?>

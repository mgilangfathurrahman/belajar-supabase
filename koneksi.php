<?php
// PHP akan mengambil data ini dari pengaturan server (Environment Variables)
$host     = getenv('aws-0-ap-southeast-1.pooler.supabase.com') ?: "://supabase.com"; // Isikan default localhost Anda jika ada
$port     = getenv('DB_PORT') ?: "5432";
$dbname   = getenv('DB_NAME') ?: "postgres";
$user     = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password";
    
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // echo "Koneksi Sukses!"; // Sudah kita komentari sebelumnya
} catch (PDOException $e) {
    echo "<h1>Koneksi Gagal!</h1>";
    echo "<p>Pesan Error dari System: <strong>" . $e->getMessage() . "</strong></p>";
}
?>

<?php
// PHP akan langsung mengambil semua data ini murni dari Environment Variables Vercel
$host     = getenv('DB_HOST');
$port     = getenv('DB_PORT');
$dbname   = getenv('DB_NAME');
$user     = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

try {
    // Wajib menambahkan sslmode=require untuk koneksi ke server Aiven
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

} catch (PDOException $e) {
    echo "<h1>Koneksi ke Aiven Gagal!</h1>";
    echo "<p>Pesan Error: <strong>" . $e->getMessage() . "</strong></p>";
}
?>

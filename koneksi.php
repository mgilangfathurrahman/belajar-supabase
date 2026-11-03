<?php
// AMBIL DATA INI DARI DASHBOARD SUPABASE -> SETTINGS -> DATABASE

$host     = "aws-0-ap-southeast-1.pooler.supabase.com"; // Ganti dengan Host Session Pooler Anda
$port     = "5432";                                     // Coba port 5432 atau ganti ke 6543 jika gagal
$dbname   = "postgres";                                 // Tetap 'postgres'
$user     = "postgres.eflhblxsxxgekuaxjaoj";            // Ganti dengan User (Ada Project ID Anda)
$password = "Malang@2026#";                       // HAPUS tanda [ ] jika ada, masukkan password asli

try {
    // Format DSN standar PHP yang paling stabil untuk PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password";
    
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

  //  echo "<h1>Koneksi Sukses!</h1>";
  //  echo "<p>Hebat, PHP Anda sudah terhubung 100% ke PostgreSQL Supabase.</p>";

} catch (PDOException $e) {
    echo "<h1>Koneksi Gagal!</h1>";
    echo "<p>Pesan Error dari System: <strong>" . $e->getMessage() . "</strong></p>";
}
?>
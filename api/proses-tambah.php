<?php
// 1. Hubungkan dengan file koneksi
require 'koneksi.php';

// 2. Pastikan file ini diakses setelah user menekan tombol submit (metode POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 3. Tangkap data dari form HTML
    $nama_produk = $_POST['nama_produk'];
    $harga       = $_POST['harga'];
    $stok        = $_POST['stok'];

    try {
        // 4. Siapkan Query INSERT dengan placeholder
        $sql = "INSERT INTO produk (nama_produk, harga, stok) VALUES (:nama, :harga, :stok)";
        $stmt = $pdo->prepare($sql);
        
        // 5. Eksekusi query dengan mengikat data asli
        $stmt->execute([
            ':nama'  => $nama_produk,
            ':harga' => $harga,
            ':stok'  => $stok
        ]);

        // --- BAGIAN BERHASIL (DITAMBAHKAN AUTO REDIRECT) ---
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Data Berhasil Disimpan</title>
            
            <!-- Otomatis pindah ke tampil-data.php setelah 2 detik -->
            <meta http-equiv="refresh" content="2;url=tampil-data.php">

            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f4f7f6;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                }
                .card {
                    background: #ffffff;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    text-align: center;
                    max-width: 400px;
                    width: 100%;
                }
                h2 { color: #2e7d32; margin-top: 0; }
                p { color: #555; margin-bottom: 20px; }
                a { color: #10b981; text-decoration: none; font-weight: bold; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class="card">
                <h2>Data Berhasil Disimpan!</h2>
                <p>Produk <strong><?php echo htmlspecialchars($nama_produk); ?></strong> telah ditambahkan ke database Supabase.</p>
                <p><small>Anda akan diarahkan ke daftar produk dalam 2 detik...</small></p>
                <p><a href="tampil-data.php">&laquo; Klik di sini jika tidak berpindah otomatis</a></p>
            </div>
        </body>
        </html>
        <?php

    } catch (PDOException $e) {
        echo "<h1>Gagal Menyimpan Data!</h1>";
        echo "<p>Penyebab error: " . $e->getMessage() . "</p>";
        echo "<a href='form-tambah.php'>&laquo; Kembali ke Form</a>";
    }

} else {
    // Jika file ini diakses langsung tanpa lewat form, arahkan kembali ke form
    header("Location: form-tambah.php");
    exit;
}
?>

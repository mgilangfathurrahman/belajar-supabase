<?php
// Langsung menampilkan seluruh isi dari tampil-data.php di halaman utama
require_once __DIR__ . '/tampil-data.php'; 
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Aplikasi Supabase</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 40px;
            display: flex;
            justify-content: center;
        }
        .container {
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
        }
        h1 {
            color: #2e7d32;
            margin-bottom: 20px;
            font-size: 24px;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            margin-bottom: 12px;
        }
        a {
            display: block;
            padding: 12px 16px;
            background: #10b981;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            transition: background 0.2s;
        }
        a:hover {
            background: #059669;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Menu Aplikasi Supabase</h1>
    <p>Pilih halaman yang ingin dibuka:</p>
    <ul>
        <li><a href="tampil-data.php">📄 Lihat Daftar Produk</a></li>
        <!-- Tambahkan file lain di bawah ini sesuai kebutuhan -->
        <!-- <li><a href="tambah-data.php">➕ Tambah Produk</a></li> -->
    </ul>
</div>

</body>
</html>

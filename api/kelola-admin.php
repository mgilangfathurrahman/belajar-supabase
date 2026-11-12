<?php
if (!isset($_COOKIE['admin_logged_in']) || $_COOKIE['admin_logged_in'] !== 'true') {
    header("Location: login.php");
    exit;
}

// Kunci halaman ini khusus untuk Superadmin
$admin_nama = $_COOKIE['admin_nama'] ?? ($_COOKIE['admin_username'] ?? 'Admin');
$admin_role = $_COOKIE['admin_role'] ?? 'admin';
$admin_user = $_COOKIE['admin_username'] ?? '';

if ($admin_role !== 'superadmin') {
    header("Location: laporan.php");
    exit;
}

require 'koneksi.php';

$pesan_err = "";
$edit_admin = null;

// 1. Proses Hapus Akun Admin
if (isset($_GET['hapus'])) {
    $id_hapus = (int) $_GET['hapus'];
    $cek = $pdo->prepare("SELECT username FROM admin WHERE id = :id");
    $cek->execute([':id' => $id_hapus]);
    $target = $cek->fetch();

    if ($target && $target['username'] === $admin_user) {
        $pesan_err = "Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif!";
    } else {
        $del = $pdo->prepare("DELETE FROM admin WHERE id = :id");
        $del->execute([':id' => $id_hapus]);
        header("Location: kelola-admin.php?status=dihapus");
        exit;
    }
}

// 2. Proses Ubah Status Aktif / Nonaktif
if (isset($_GET['toggle'])) {
    $id_toggle = (int) $_GET['toggle'];
    $cek = $pdo->prepare("SELECT username, aktif FROM admin WHERE id = :id");
    $cek->execute([':id' => $id_toggle]);
    $target = $cek->fetch();

    if ($target && $target['username'] === $admin_user) {
        $pesan_err = "Anda tidak dapat menonaktifkan akun Anda sendiri!";
    } elseif ($target) {
        $status_baru = $target['aktif'] ? 'false' : 'true';
        $upd = $pdo->prepare("UPDATE admin SET aktif = $status_baru WHERE id = :id");
        $upd->execute([':id' => $id_toggle]);
        header("Location: kelola-admin.php?status=diubah");
        exit;
    }
}

// 3. Ambil Data Jika Sedang Mode Edit
if (isset($_GET['edit'])) {
    $id_edit = (int) $_GET['edit'];
    $stmt = $pdo->prepare("SELECT id, username, nama, role, aktif FROM admin WHERE id = :id");
    $stmt->execute([':id' => $id_edit]);
    $edit_admin = $stmt->fetch();
}

// 4. Proses Simpan (Tambah Admin Baru atau Update Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = ($_POST['role'] === 'superadmin') ? 'superadmin' : 'admin';

    if ($nama === '' || $username === '') {
        $pesan_err = "Nama lengkap dan username wajib diisi!";
    } else {
        try {
            if (!empty($_POST['id_edit'])) {
                $id_edit = (int) $_POST['id_edit'];
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE admin SET nama = :nama, username = :user, password = :pass, role = :role WHERE id = :id");
                    $stmt->execute([':nama' => $nama, ':user' => $username, ':pass' => $hash, ':role' => $role, ':id' => $id_edit]);
                } else {
                    $stmt = $pdo->prepare("UPDATE admin SET nama = :nama, username = :user, role = :role WHERE id = :id");
                    $stmt->execute([':nama' => $nama, ':user' => $username, ':role' => $role, ':id' => $id_edit]);
                }
                header("Location: kelola-admin.php?status=diubah");
                exit;
            } else {
                if ($password === '') {
                    $pesan_err = "Password wajib diisi untuk pembuatan akun admin baru!";
                } else {
                    $cekUser = $pdo->prepare("SELECT id FROM admin WHERE username = :user");
                    $cekUser->execute([':user' => $username]);
                    if ($cekUser->fetch()) {
                        $pesan_err = "Username '$username' sudah digunakan. Pilih username lain!";
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO admin (nama, username, password, role, aktif) VALUES (:nama, :user, :pass, :role, true)");
                        $stmt->execute([':nama' => $nama, ':user' => $username, ':pass' => $hash, ':role' => $role]);
                        header("Location: kelola-admin.php?status=sukses");
                        exit;
                    }
                }
            }
        } catch (PDOException $e) {
            $pesan_err = "Kesalahan database: " . $e->getMessage();
        }
    }
}

$daftar_admin = $pdo->query("SELECT id, username, nama, role, aktif, dibuat FROM admin ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Admin - Toko Gilang</title>
    <style>
        body { font-family: sans-serif; margin: 20px 40px; }
        nav { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        nav a { margin-right: 15px; text-decoration: none; font-weight: bold; color: #24b47e; }
        .user-badge { background: #e6f7f0; color: #1c8c61; padding: 5px 10px; border-radius: 5px; font-size: 13px; font-weight: bold; }
        .form-box { background: #f4f7f6; padding: 18px; border-radius: 6px; margin: 15px 0 25px; border-left: 4px solid #24b47e; }
        .form-grid { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        input, select { padding: 9px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        button { background: #24b47e; color: white; border: none; padding: 9px 16px; border-radius: 4px; font-weight: bold; cursor: pointer; }
        button:hover { background: #1c8c61; }
        .btn-batal { background: #6b7280; color: white; padding: 9px 14px; text-decoration: none; border-radius: 4px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 14px; }
        th { background: #24b47e; color: white; }
        .badge-role { padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .role-super { background: #fef3c7; color: #b45309; }
        .role-admin { background: #e0f2fe; color: #0369a1; }
        .status-aktif { color: #16a34a; font-weight: bold; }
        .status-nonaktif { color: #dc2626; font-weight: bold; }
        .btn-aksi { padding: 5px 9px; text-decoration: none; color: white; border-radius: 4px; font-size: 12px; margin-right: 4px; display: inline-block; }
        .btn-edit { background: #f59e0b; }
        .btn-toggle { background: #4b5563; }
        .btn-hapus { background: #dc2626; }
        .alert-ok { background: #e6f7f0; color: #1c8c61; padding: 10px; margin: 15px 0; border-radius: 5px; font-weight: bold; }
        .alert-err { background: #ffe6e6; color: #d93025; padding: 10px; margin: 15px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <nav>
        <div>
            <a href="laporan.php">Laporan Real-Time</a>
            <a href="produk.php">Produk</a>
            <a href="pesanan.php">Pesanan</a>
            <a href="kelola-admin.php">Kelola Admin</a>
            <a href="index.php" target="_blank">Lihat Katalog</a>
            <a href="logout.php" style="color:red;">Logout</a>
        </div>
        <div class="user-badge">👤 <?= htmlspecialchars($admin_nama) ?> (SUPERADMIN)</div>
    </nav>
    <hr>

    <h2>Kelola Akun Admin & Pegawai</h2>

    <?php if (isset($_GET['status'])): ?>
        <div class="alert-ok">
            <?php
                if ($_GET['status'] === 'sukses') echo "Akun admin baru berhasil ditambahkan!";
                if ($_GET['status'] === 'diubah') echo "Data akun admin berhasil diperbarui!";
                if ($_GET['status'] === 'dihapus') echo "Akun admin berhasil dihapus!";
            ?>
        </div>
    <?php endif; ?>

    <?php if ($pesan_err !== ""): ?>
        <div class="alert-err"><?= htmlspecialchars($pesan_err) ?></div>
    <?php endif; ?>

    <div class="form-box">
        <h3 style="margin-top:0;"><?= $edit_admin ? 'Edit Akun: ' . htmlspecialchars($edit_admin['nama']) : 'Tambah Pegawai Admin Baru' ?></h3>
        <form method="POST" action="kelola-admin.php" class="form-grid">
            <input type="hidden" name="id_edit" value="<?= $edit_admin ? $edit_admin['id'] : '' ?>">
            <input type="text" name="nama" placeholder="Nama Tampilan (mis: Admin 1)" value="<?= $edit_admin ? htmlspecialchars($edit_admin['nama']) : '' ?>" required>
            <input type="text" name="username" placeholder="Username Login (mis: admin1)" value="<?= $edit_admin ? htmlspecialchars($edit_admin['username']) : '' ?>" required>
            <input type="password" name="password" placeholder="<?= $edit_admin ? 'Password Baru (kosongkan jika tetap)' : 'Password Login' ?>" <?= $edit_admin ? '' : 'required' ?>>
            <select name="role">
                <option value="admin" <?= ($edit_admin && $edit_admin['role'] === 'admin') ? 'selected' : '' ?>>Admin Pegawai</option>
                <option value="superadmin" <?= ($edit_admin && $edit_admin['role'] === 'superadmin') ? 'selected' : '' ?>>Superadmin</option>
            </select>
            <button type="submit" style="<?= $edit_admin ? 'background:#f59e0b;' : '' ?>">
                <?= $edit_admin ? 'Simpan Perubahan' : 'Tambah Admin' ?>
            </button>
            <?php if ($edit_admin): ?>
                <a href="kelola-admin.php" class="btn-batal">Batal</a>
            <?php endif; ?>
        </form>
    </div>

   <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Tampilan</th>
                <th>Username</th>
                <th>Hak Akses</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; ?>
            <?php foreach ($daftar_admin as $adm): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><strong><?= htmlspecialchars($adm['nama']) ?></strong></td>
                <td><?= htmlspecialchars($adm['username']) ?></td>
                <td>
                    <span class="badge-role <?= $adm['role'] === 'superadmin' ? 'role-super' : 'role-admin' ?>">
                        <?= strtoupper(htmlspecialchars($adm['role'])) ?>
                    </span>
                </td>
                <td>
                    <?php if ($adm['aktif']): ?>
                        <span class="status-aktif">● Aktif</span>
                    <?php else: ?>
                        <span class="status-nonaktif">● Nonaktif</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="kelola-admin.php?edit=<?= $adm['id'] ?>" class="btn-aksi btn-edit">Edit</a>
                    <?php if ($adm['username'] !== $admin_user): ?>
                        <a href="kelola-admin.php?toggle=<?= $adm['id'] ?>" class="btn-aksi btn-toggle">
                            <?= $adm['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </a>
                        <a href="kelola-admin.php?hapus=<?= $adm['id'] ?>" class="btn-aksi btn-hapus" onclick="return confirm('Hapus akun admin ini secara permanen?')">Hapus</a>
                    <?php else: ?>
                        <span style="font-size:12px; color:#888;">(Akun Anda)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>

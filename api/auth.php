<?php
/**
 * auth.php - autentikasi multi-admin untuk Vercel (serverless).
 *
 * PHP session berbasis file tidak awet di Vercel, jadi login disimpan di cookie
 * yang ditandatangani (HMAC-SHA256) dengan rahasia AUTH_SECRET.
 * Cookie hanya berisi ID pengguna + waktu kedaluwarsa; peran (role) selalu
 * dibaca ulang dari database, sehingga akun yang dinonaktifkan langsung tertolak.
 *
 * Wajib diatur di Vercel -> Settings -> Environment Variables:
 *   AUTH_SECRET = teks acak minimal 32 karakter (mis. hasil: openssl rand -hex 32)
 *
 * Pemakaian di halaman (setelah koneksi.php, yang mendefinisikan $pdo):
 *   require_once 'koneksi.php';
 *   require_once 'auth.php';
 *   $me = wajib_login();      // semua admin
 *   $me = wajib_super();      // khusus super admin
 */

const AUTH_COOKIE = 'sesi_admin';
const AUTH_TTL    = 43200; // 12 jam

function auth_secret(): string
{
    $s = getenv('AUTH_SECRET');
    if (!$s) {
        $s = $_SERVER['AUTH_SECRET'] ?? ($_ENV['AUTH_SECRET'] ?? '');
    }
    if (!is_string($s) || strlen($s) < 16) {
        http_response_code(500);
        exit('AUTH_SECRET belum diatur di Environment Variables (minimal 16 karakter).');
    }
    return $s;
}

function b64url_enc(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function b64url_dec(string $s)
{
    return base64_decode(strtr($s, '-_', '+/'));
}

function auth_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** Buat cookie login untuk pengguna dengan ID tertentu. */
function auth_issue(int $id): void
{
    $b64   = b64url_enc(json_encode(['id' => $id, 'exp' => time() + AUTH_TTL]));
    $token = $b64 . '.' . hash_hmac('sha256', $b64, auth_secret());
    setcookie(AUTH_COOKIE, $token, [
        'expires'  => time() + AUTH_TTL,
        'path'     => '/',
        'secure'   => auth_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Hapus cookie login (logout). */
function auth_clear(): void
{
    setcookie(AUTH_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => auth_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[AUTH_COOKIE]);
}

/** Pengguna yang sedang login (array: id, username, nama, role) atau null. */
function auth_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;

    $token = $_COOKIE[AUTH_COOKIE] ?? '';
    if (!is_string($token) || substr_count($token, '.') !== 1) {
        return null;
    }
    [$b64, $sig] = explode('.', $token);
    if (!hash_equals(hash_hmac('sha256', $b64, auth_secret()), $sig)) {
        return null;
    }
    $data = json_decode((string) b64url_dec($b64), true);
    if (!is_array($data) || empty($data['id']) || ($data['exp'] ?? 0) < time()) {
        return null;
    }

    global $pdo;
    $stmt = $pdo->prepare('SELECT id, username, nama, role FROM admin_users WHERE id = :id AND aktif = TRUE');
    $stmt->execute([':id' => (int) $data['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $cache = $row ?: null;
    return $cache;
}

function is_super(): bool
{
    $u = auth_user();
    return $u !== null && $u['role'] === 'superadmin';
}

/** Wajib login (semua peran). Jika belum, arahkan ke login.php. */
function wajib_login(): array
{
    $u = auth_user();
    if ($u === null) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

/**
 * Wajib super admin.
 * @param bool $api true untuk endpoint JSON (balas 401/403 JSON, bukan redirect)
 */
function wajib_super(bool $api = false): array
{
    $u = auth_user();

    if ($u === null) {
        if ($api) {
            http_response_code(401);
            header('Content-Type: application/json');
            exit(json_encode(['status' => 'gagal', 'pesan' => 'Belum login']));
        }
        header('Location: login.php');
        exit;
    }

    if ($u['role'] !== 'superadmin') {
        http_response_code(403);
        if ($api) {
            header('Content-Type: application/json');
            exit(json_encode(['status' => 'gagal', 'pesan' => 'Akses ditolak']));
        }
        exit('<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Akses Ditolak</title></head>'
            . '<body style="font-family:sans-serif;margin:40px">'
            . '<h2>Akses ditolak</h2><p>Halaman ini hanya untuk Super Admin.</p>'
            . '<p><a href="pesanan.php">Kembali ke Pesanan</a></p></body></html>');
    }
    return $u;
}

<?php
// Konfigurasi waktu session & cookie
// Ubah angka ini sesuai kebutuhan.
// Contoh: 5 detik session, cookie otomatis 2x = 10 detik.
define('APP_SESSION_LIFETIME', 3500); //
define('APP_COOKIE_LIFETIME', APP_SESSION_LIFETIME * 2);

define('APP_SESSION_NAME', 'CLEANGOSESSID');

function cleango_send_no_cache_headers(): void {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
}

function cleango_boot_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.gc_maxlifetime', (string) APP_SESSION_LIFETIME);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_name(APP_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => APP_SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
    cleango_send_no_cache_headers();

    if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > APP_SESSION_LIFETIME) {
        // Hapus session saja — cookie remember_username DIBIARKAN hidup
        // sampai APP_COOKIE_LIFETIME (2x session) habis sendiri,
        // supaya username masih tersisa di form login setelah session expired.
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        session_start();
        cleango_send_no_cache_headers();
        $_SESSION['session_expired'] = true;
    }

    $_SESSION['last_activity'] = time();
}

function cleango_set_cookie(string $name, string $value, ?int $lifetime = null): void {
    $seconds = $lifetime ?? APP_COOKIE_LIFETIME;
    setcookie($name, $value, [
        'expires' => time() + $seconds,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function cleango_delete_cookie(string $name): void {
    setcookie($name, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
?>

<?php
$host   = '127.0.0.1';
$port   = '3306';
$dbname = 'cleango';
$user   = 'root';
$pass   = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:40px;color:#dc2626;">
        <h2>❌ Koneksi Database Gagal</h2>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
        <p>Pastikan MySQL sudah berjalan dan database <strong>cleanGo</strong> sudah dibuat dari file <code>cleanGo.sql</code>.</p>
    </div>');
}

$conn = new mysqli($host, $user, $pass, $dbname, (int)$port);
if ($conn->connect_error) {
    die('Koneksi mysqli gagal: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function badgeStatus($status) {
    return match($status) {
        'Menunggu Konfirmasi' => 'badge-warning',
        'Dijemput'           => 'badge-blue',
        'Dicuci'             => 'badge-process',
        'Disetrika'          => 'badge-purple',
        'Dikirim'            => 'badge-cyan',
        'Selesai'            => 'badge-success',
        'Dibatalkan'         => 'badge-danger',
        'Lunas'              => 'badge-success',
        'Pending'            => 'badge-warning',
        'Gagal'              => 'badge-danger',
        'Aktif'              => 'badge-success',
        'Nonaktif'           => 'badge-danger',
        default              => 'badge-default',
    };
}

function generateKodeOrder(PDO $pdo): string {
    $today = date('Ymd');
    $stmt  = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(tanggal_pesan) = CURDATE()");
    $stmt->execute();
    $cnt = (int)$stmt->fetchColumn() + 1;
    return 'ORD-' . $today . '-' . str_pad($cnt, 3, '0', STR_PAD_LEFT);
}

function generateNoInvoice(PDO $pdo): string {
    $today = date('Ymd');
    $stmt  = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE DATE(tgl_invoice) = CURDATE()");
    $stmt->execute();
    $cnt = (int)$stmt->fetchColumn() + 1;
    return 'INV-' . $today . '-' . str_pad($cnt, 3, '0', STR_PAD_LEFT);
}

function sendNotification(PDO $pdo, string $role, int $actor_id, string $title, string $message, string $link = ''): void {
    $pdo->prepare("INSERT INTO notifications (role, actor_id, title, message, link) VALUES (?,?,?,?,?)")
        ->execute([$role, $actor_id, $title, $message, $link]);
}

function notifyAllStaff(PDO $pdo, string $title, string $message, string $link = ''): void {
    $staffs = $pdo->query("SELECT id_staff FROM staff WHERE is_active=1")->fetchAll();
    foreach ($staffs as $s) {
        sendNotification($pdo, 'staff', $s['id_staff'], $title, $message, $link);
    }
}

function notifyAllOwner(PDO $pdo, string $title, string $message, string $link = ''): void {
    $owners = $pdo->query("SELECT id_owner FROM owner WHERE is_active=1")->fetchAll();
    foreach ($owners as $o) {
        sendNotification($pdo, 'owner', $o['id_owner'], $title, $message, $link);
    }
}

function countUnread(PDO $pdo, string $role, int $actor_id): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE role=? AND actor_id=? AND is_read=0");
    $stmt->execute([$role, $actor_id]);
    return (int)$stmt->fetchColumn();
}

function getNotifications(PDO $pdo, string $role, int $actor_id, int $limit = 20): array {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE role=? AND actor_id=? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$role, $actor_id, $limit]);
    return $stmt->fetchAll();
}

function markAllRead(PDO $pdo, string $role, int $actor_id): void {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE role=? AND actor_id=?")->execute([$role, $actor_id]);
}

<?php
require_once 'app_config.php';
cleango_boot_session();
require_once 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$role     = $_SESSION['role'];
$actor_id = (int)$_SESSION['user_id'];
$action   = $_GET['action'] ?? $_POST['action'] ?? 'get';

if ($action === 'get') {
    $notifs  = getNotifications($pdo, $role, $actor_id, 20);
    $unread  = countUnread($pdo, $role, $actor_id);
    echo json_encode(['unread' => $unread, 'notifications' => $notifs]);

} elseif ($action === 'mark_read') {
    markAllRead($pdo, $role, $actor_id);
    echo json_encode(['success' => true]);

} elseif ($action === 'mark_one') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND role=? AND actor_id=?")
            ->execute([$id, $role, $actor_id]);
    }
    echo json_encode(['success' => true]);
}
?>

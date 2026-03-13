<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get pending friend requests that came in after the last-seen timestamp
// We track this in session so we only show truly *new* ones
$last_checked = $_SESSION['last_notif_check'] ?? date('Y-m-d H:i:s', strtotime('-10 seconds'));
$_SESSION['last_notif_check'] = date('Y-m-d H:i:s');

try {
    $stmt = $pdo->prepare("
        SELECT f.id, u.username, u.avatar, f.created_at
        FROM friends f
        JOIN users u ON f.user_id = u.id
        WHERE f.friend_id = ?
          AND f.status = 'pending'
          AND f.created_at > ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$user_id, $last_checked]);
    $new_requests = $stmt->fetchAll();

    echo json_encode(['success' => true, 'new_requests' => $new_requests]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

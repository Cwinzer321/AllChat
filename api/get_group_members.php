<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

if (!$group_id) {
    echo json_encode(['success' => false, 'error' => 'Group ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.avatar, u.status 
        FROM users u
        JOIN server_members sm ON u.id = sm.user_id
        WHERE sm.server_id = ?
        ORDER BY u.status DESC, u.username ASC
    ");
    $stmt->execute([$group_id]);
    $members = $stmt->fetchAll();

    echo json_encode(['success' => true, 'members' => $members]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

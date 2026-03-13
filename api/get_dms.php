<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$friend_id = isset($_GET['friend_id']) ? (int)$_GET['friend_id'] : 0;

if (!$friend_id) {
    echo json_encode(['success' => false, 'error' => 'Friend ID required']);
    exit;
}

try {
    // Only fetch if they are actually friends
    $check = $pdo->prepare("SELECT id FROM friends WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)) AND status = 'accepted'");
    $check->execute([$user_id, $friend_id, $friend_id, $user_id]);
    
    if ($check->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Not friends']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT m.id, m.content, m.created_at, m.attachment_url, m.attachment_name, m.attachment_type, u.username, u.avatar 
        FROM direct_messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$user_id, $friend_id, $friend_id, $user_id]);
    $messages = $stmt->fetchAll();

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$friend_id = isset($_POST['friend_id']) ? (int)$_POST['friend_id'] : 0;
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

if (!$friend_id || empty($content)) {
    echo json_encode(['success' => false, 'error' => 'Friend ID and content are required']);
    exit;
}

try {
    // Only fetch if they are actually friends
    $check = $pdo->prepare("SELECT id FROM friends WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)) AND status = 'accepted'");
    $check->execute([$user_id, $friend_id, $friend_id, $user_id]);
    
    if ($check->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => "Not friends (user: $user_id, friend: $friend_id)"]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO direct_messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $friend_id, $content]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

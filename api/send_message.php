<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST method required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$channel_id = isset($_POST['channel_id']) ? (int)$_POST['channel_id'] : 0;
$content = trim($_POST['content']);

if (!$channel_id || empty($content)) {
    echo json_encode(['success' => false, 'error' => 'Channel ID and content required']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO messages (channel_id, user_id, content) VALUES (?, ?, ?)");
    if ($stmt->execute([$channel_id, $user_id, $content])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

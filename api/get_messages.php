<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$channel_id = isset($_GET['channel_id']) ? (int)$_GET['channel_id'] : 0;

if (!$channel_id) {
    echo json_encode(['success' => false, 'error' => 'Channel ID required']);
    exit;
}

try {
    // Ensure attachment columns exist
    try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS attachment_url VARCHAR(500) DEFAULT NULL"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS attachment_name VARCHAR(255) DEFAULT NULL"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS attachment_type VARCHAR(50) DEFAULT NULL"); } catch(Exception $e) {}

    $stmt = $pdo->prepare("
        SELECT m.id, m.content, m.created_at, m.attachment_url, m.attachment_name, m.attachment_type, u.username, u.avatar 
        FROM messages m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.channel_id = ? 
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$channel_id]);
    $messages = $stmt->fetchAll();

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

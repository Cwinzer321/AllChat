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
$server_name = trim($_POST['name'] ?? '');

if (empty($server_name)) {
    echo json_encode(['success' => false, 'error' => 'Server name is required']);
    exit;
}

// Function to generate random invite code
function generateInviteCode($length = 8) {
    return strtoupper(substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length));
}

try {
    $pdo->beginTransaction();

    $invite_code = generateInviteCode();
    
    // 1. Create Server
    $stmt = $pdo->prepare("INSERT INTO servers (name, invite_code, owner_id) VALUES (?, ?, ?)");
    $stmt->execute([$server_name, $invite_code, $user_id]);
    $server_id = $pdo->lastInsertId();

    // 2. Add creator as member
    $stmt = $pdo->prepare("INSERT INTO server_members (server_id, user_id) VALUES (?, ?)");
    $stmt->execute([$server_id, $user_id]);

    // 3. Create default #general channel
    $stmt = $pdo->prepare("INSERT INTO channels (server_id, name) VALUES (?, 'general')");
    $stmt->execute([$server_id]);
    $channel_id = $pdo->lastInsertId();

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'server_id' => $server_id, 
        'channel_id' => $channel_id
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

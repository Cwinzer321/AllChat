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
$invite_code = strtoupper(trim($_POST['invite_code']));

if (empty($invite_code)) {
    echo json_encode(['success' => false, 'error' => 'Invite code required']);
    exit;
}

try {
    // Check if server exists with this invite code
    $stmt = $pdo->prepare("SELECT id FROM servers WHERE invite_code = ?");
    $stmt->execute([$invite_code]);
    $server = $stmt->fetch();

    if (!$server) {
        echo json_encode(['success' => false, 'error' => 'Invalid invite code']);
        exit;
    }

    $server_id = $server['id'];

    // Check if user is already a member
    $stmt = $pdo->prepare("SELECT id FROM server_members WHERE server_id = ? AND user_id = ?");
    $stmt->execute([$server_id, $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'You are already a member of this server']);
        exit;
    }

    // Join server
    $stmt = $pdo->prepare("INSERT INTO server_members (server_id, user_id) VALUES (?, ?)");
    if ($stmt->execute([$server_id, $user_id])) {
        echo json_encode(['success' => true, 'server_id' => $server_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to join server']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

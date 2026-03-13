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
$group_code = strtoupper(trim($_POST['invite_code']));

if (empty($group_code)) {
    echo json_encode(['success' => false, 'error' => 'Group code required']);
    exit;
}

try {
    // Check if group exists with this code
    $stmt = $pdo->prepare("SELECT id FROM servers WHERE invite_code = ?");
    $stmt->execute([$group_code]);
    $group = $stmt->fetch();

    if (!$group) {
        echo json_encode(['success' => false, 'error' => 'Invalid group code']);
        exit;
    }

    $server_id = $group['id'];

    // Check if user is already a member
    $stmt = $pdo->prepare("SELECT id FROM server_members WHERE server_id = ? AND user_id = ?");
    $stmt->execute([$server_id, $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'You are already a member of this group']);
        exit;
    }

    // Join group
    $stmt = $pdo->prepare("INSERT INTO server_members (server_id, user_id) VALUES (?, ?)");
    if ($stmt->execute([$server_id, $user_id])) {
        echo json_encode(['success' => true, 'group_id' => $server_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to join group']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

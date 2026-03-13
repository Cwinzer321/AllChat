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
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : null;

if (!$group_id) {
    echo json_encode(['success' => false, 'error' => 'Group ID is required']);
    exit;
}

try {
    // Check if the user is the owner of the group
    $stmt = $pdo->prepare("SELECT owner_id FROM servers WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();

    if (!$group) {
        echo json_encode(['success' => false, 'error' => 'Group not found']);
        exit;
    }

    if ($group['owner_id'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'Only the group owner can delete the group']);
        exit;
    }

    // Delete the group (cascades will handle members, channels, and messages)
    $stmt = $pdo->prepare("DELETE FROM servers WHERE id = ?");
    if ($stmt->execute([$group_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete group']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

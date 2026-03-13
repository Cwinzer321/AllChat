<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$friend_id = isset($_POST['friend_id']) ? (int)$_POST['friend_id'] : 0;

if (!$friend_id) {
    echo json_encode(['success' => false, 'error' => 'Friend ID required']);
    exit;
}

try {
    // Only allow if they are actually friends
    $check = $pdo->prepare("SELECT id FROM friends WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)) AND status = 'accepted'");
    $check->execute([$user_id, $friend_id, $friend_id, $user_id]);
    
    if ($check->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Not friends']);
        exit;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'File upload failed']);
        exit;
    }

    $file = $_FILES['file'];
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'error' => 'File too large. Max 10MB.']);
        exit;
    }

    $allowedMimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'text/plain',
        'application/zip', 'application/x-zip-compressed',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedMimes)) {
        echo json_encode(['success' => false, 'error' => 'File type not allowed']);
        exit;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $uniqueName = uniqid('dm_', true) . '.' . $ext;
    $uploadDir = dirname(__DIR__) . '/assets/uploads/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uploadPath = $uploadDir . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save file']);
        exit;
    }

    $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $isImage = in_array($mimeType, $imageTypes);
    $fileUrl = 'assets/uploads/' . $uniqueName;

    $content = isset($_POST['content']) && trim($_POST['content']) !== '' ? trim($_POST['content']) : '';

    $stmt = $pdo->prepare("INSERT INTO direct_messages (sender_id, receiver_id, content, attachment_url, attachment_name, attachment_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $friend_id, $content, $fileUrl, $file['name'], $mimeType]);

    echo json_encode([
        'success' => true,
        'file_url' => $fileUrl,
        'file_name' => $file['name'],
        'file_type' => $mimeType,
        'is_image' => $isImage
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

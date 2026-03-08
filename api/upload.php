<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$channel_id = isset($_POST['channel_id']) ? (int)$_POST['channel_id'] : 0;

if (!$channel_id) {
    echo json_encode(['success' => false, 'error' => 'Channel ID required']);
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

// Allowed types
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

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$uniqueName = uniqid('file_', true) . '.' . $ext;
$uploadDir = dirname(__DIR__) . '/assets/uploads/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$uploadPath = $uploadDir . $uniqueName;

if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    exit;
}

// Determine if it's an image
$imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$isImage = in_array($mimeType, $imageTypes);
$fileUrl = 'assets/uploads/' . $uniqueName;

// Save as message with attachment
try {
    // Ensure messages table has attachment columns
    $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS attachment_url VARCHAR(500) DEFAULT NULL");
    $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS attachment_name VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS attachment_type VARCHAR(50) DEFAULT NULL");

    $content = isset($_POST['content']) && trim($_POST['content']) !== '' ? trim($_POST['content']) : '';

    $stmt = $pdo->prepare("INSERT INTO messages (channel_id, user_id, content, attachment_url, attachment_name, attachment_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$channel_id, $user_id, $content, $fileUrl, $file['name'], $mimeType]);

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

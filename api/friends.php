<?php
session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if ($action === 'send') {
            $friend_username = trim($data['username'] ?? '');
            if (empty($friend_username)) {
                echo json_encode(['success' => false, 'error' => 'Username is required']);
                exit;
            }

            // Find user id by username
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$friend_username]);
            $friend = $stmt->fetch();

            if (!$friend) {
                echo json_encode(['success' => false, 'error' => 'User not found']);
                exit;
            }

            if ($friend['id'] == $user_id) {
                echo json_encode(['success' => false, 'error' => "You can't add yourself as a friend"]);
                exit;
            }

            $friend_id = $friend['id'];

            // Check if relationship already exists
            $stmt = $pdo->prepare("SELECT status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
            $stmt->execute([$user_id, $friend_id, $friend_id, $user_id]);
            $existing = $stmt->fetch();

            if ($existing) {
                echo json_encode(['success' => false, 'error' => 'Friend request already exists or you are already friends']);
                exit;
            }

            // Insert pending request
            $stmt = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$user_id, $friend_id]);
            echo json_encode(['success' => true, 'message' => 'Friend request sent']);

        } elseif ($action === 'respond') {
            $id = (int)($data['id'] ?? 0);
            $status = $data['status'] ?? ''; // 'accepted' or 'rejected'

            if (!in_array($status, ['accepted', 'rejected'])) {
                echo json_encode(['success' => false, 'error' => 'Invalid status']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE friends SET status = ? WHERE id = ? AND friend_id = ?");
            if ($stmt->execute([$status, $id, $user_id])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update request']);
            }
        }
    } else {
        if ($action === 'list') {
            // Get accepted friends
            $stmt = $pdo->prepare("
                SELECT f.id, u.username, u.status, u.avatar 
                FROM friends f 
                JOIN users u ON (f.user_id = u.id OR f.friend_id = u.id)
                WHERE (f.user_id = ? OR f.friend_id = ?) 
                AND f.status = 'accepted' 
                AND u.id != ?
            ");
            $stmt->execute([$user_id, $user_id, $user_id]);
            $friends = $stmt->fetchAll();

            // Get pending requests (incoming)
            $stmt = $pdo->prepare("
                SELECT f.id, u.username, u.avatar, 'incoming' as direction
                FROM friends f 
                JOIN users u ON f.user_id = u.id 
                WHERE f.friend_id = ? AND f.status = 'pending'
            ");
            $stmt->execute([$user_id]);
            $incoming = $stmt->fetchAll();

            // Get pending requests (outgoing)
            $stmt = $pdo->prepare("
                SELECT f.id, u.username, u.avatar, 'outgoing' as direction
                FROM friends f 
                JOIN users u ON f.friend_id = u.id
                WHERE f.user_id = ? AND f.status = 'pending'
            ");
            $stmt->execute([$user_id]);
            $outgoing = $stmt->fetchAll();

            $pending = array_merge($incoming, $outgoing);

            // Get blocked users
            $stmt = $pdo->prepare("
                SELECT f.id, u.username, u.avatar
                FROM friends f
                JOIN users u ON f.friend_id = u.id
                WHERE f.user_id = ? AND f.status = 'blocked'
            ");
            $stmt->execute([$user_id]);
            $blocked = $stmt->fetchAll();

            echo json_encode(['success' => true, 'friends' => $friends, 'pending' => $pending, 'blocked' => $blocked]);

        }
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

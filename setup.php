<?php
require_once 'config/db.php';

echo "<h2>Discord Clone Setup</h2>";

try {
    // Tables are now auto-created in config/db.php
    echo "Database connectivity and tables verified.<br>";

    // 2. Create Server
    $stmt = $pdo->prepare("INSERT IGNORE INTO servers (name, invite_code, owner_id) VALUES (?, ?, ?)");
    $stmt->execute(['AllChat Official', 'WELCOME', null]);
    $server_id = $pdo->lastInsertId();

    if (!$server_id) {
        // If it already existed, get the ID
        $stmt = $pdo->query("SELECT id FROM servers WHERE invite_code = 'WELCOME'");
        $server_id = $stmt->fetchColumn();
    }

    if ($server_id) {
        echo "Server: AllChat Official (ID: $server_id) is ready.<br>";
        
        // 3. Create Channels
        $channels = ['general', 'announcements', 'random'];
        foreach ($channels as $channel_name) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO channels (server_id, name) VALUES (?, ?)");
            $stmt->execute([$server_id, $channel_name]);
            echo "Checked Channel: #$channel_name<br>";
        }
    }

    echo "<br><b>Setup complete!</b> You can now <a href='register.php'>Register</a> and start chatting.";
    echo "<br>Note: To join this server, you'll need to be added to the 'server_members' table manually or via a 'join server' feature (to be implemented).";
    
    // For demo purposes, auto-add all users to this server
    $pdo->query("INSERT IGNORE INTO server_members (server_id, user_id) SELECT $server_id, id FROM users");

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

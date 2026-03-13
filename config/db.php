<?php
// Database connection configuration
$host = 'localhost';
$db   = 'discord_clone';
$user = 'root';
$pass = ''; // Default Laragon password is empty
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     // Create DB if not exists and select it
     $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET $charset COLLATE {$charset}_general_ci");
     $pdo->exec("USE `$db` ");
     
     // Re-connect to the specific DB
     $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, $options);

     // AUTO-INITIALIZE TABLES IF MISSING
     $stmt = $pdo->query("SHOW TABLES LIKE 'friends'");
     if ($stmt->rowCount() == 0) {
         $sqlFile = dirname(__DIR__) . '/discord_clone.sql';
         if (file_exists($sqlFile)) {
             $sql = file_get_contents($sqlFile);
             $statements = array_filter(array_map('trim', explode(';', $sql)));
             foreach ($statements as $statement) {
                 if (!empty($statement)) $pdo->exec($statement);
             }
         }
     }
     
     // Ensure direct_messages table exists
     $pdo->exec("CREATE TABLE IF NOT EXISTS `direct_messages` (
         `id` int NOT NULL AUTO_INCREMENT,
         `sender_id` int NOT NULL,
         `receiver_id` int NOT NULL,
         `content` text NOT NULL,
         `attachment_url` varchar(500) DEFAULT NULL,
         `attachment_name` varchar(255) DEFAULT NULL,
         `attachment_type` varchar(50) DEFAULT NULL,
         `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
         PRIMARY KEY (`id`),
         KEY `sender_id` (`sender_id`),
         KEY `receiver_id` (`receiver_id`),
         CONSTRAINT `dm_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
         CONSTRAINT `dm_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>

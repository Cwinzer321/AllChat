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
         $sqlFile = dirname(__DIR__) . '/database.sql';
         if (file_exists($sqlFile)) {
             $sql = file_get_contents($sqlFile);
             $statements = array_filter(array_map('trim', explode(';', $sql)));
             foreach ($statements as $statement) {
                 if (!empty($statement)) $pdo->exec($statement);
             }
         }
     }
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>

<?php
$host = $argv[1] ?? '127.0.0.1';
$port = $argv[2] ?? '3306';
$user = $argv[3] ?? 'root';
$pass = $argv[4] ?? '';
$dbname = getenv('MYSQL_DATABASE') ?: ($argv[5] ?? 'mona-click');
try {
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database `$dbname` created or already exists\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

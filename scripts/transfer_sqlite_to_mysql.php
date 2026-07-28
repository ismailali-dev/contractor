<?php
// Usage: php transfer_sqlite_to_mysql.php [mysql_host] [mysql_port] [mysql_user] [mysql_pass] [mysql_db]
$host = $argv[1] ?? '127.0.0.1';
$port = $argv[2] ?? '3306';
$user = $argv[3] ?? 'root';
$pass = $argv[4] ?? '';
$dbname = getenv('MYSQL_DATABASE') ?: ($argv[5] ?? 'mona-click');
$sqlitePath = __DIR__ . '/../database/database.sqlite';
if (!file_exists($sqlitePath)) { echo "SQLite file not found at $sqlitePath\n"; exit(1); }

try {
    $sqlite = new PDO('sqlite:' . $sqlitePath);
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $mysql = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    echo "Connection error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// get sqlite tables
$tables = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
if (empty($tables)) { echo "No tables found in sqlite DB\n"; exit(0); }

echo "Tables to transfer: " . implode(', ', $tables) . "\n";

$mysql->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($tables as $table) {
    echo "\n--> Copying table: $table\n";
    // fetch columns
    $cols = $sqlite->query("PRAGMA table_info('$table')")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($cols)) { echo "  (no columns)\n"; continue; }
    $colNames = array_map(fn($c)=>$c['name'], $cols);
    $colList = implode(', ', array_map(fn($c) => "`$c`", $colNames));
    // clear mysql table to avoid duplicates
    try {
        $mysql->exec("TRUNCATE TABLE `$table`");
        echo "  Truncated `$table` in MySQL\n";
    } catch (Exception $e) {
        echo "  Warning: could not truncate `$table`: " . $e->getMessage() . "\n";
    }
    $rows = $sqlite->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) { echo "  (no rows)\n"; continue; }
    $placeholders = implode(', ', array_fill(0, count($colNames), '?'));
    $insertSql = "INSERT INTO `$table` ($colList) VALUES ($placeholders)";
    $stmt = $mysql->prepare($insertSql);
    $count = 0;
    foreach ($rows as $r) {
        $vals = [];
        foreach ($colNames as $cn) {
            $vals[] = array_key_exists($cn, $r) ? $r[$cn] : null;
        }
        try {
            $stmt->execute($vals);
            $count++;
        } catch (Exception $e) {
            echo "  Insert failed for table $table: " . $e->getMessage() . "\n";
        }
    }
    echo "  Inserted $count rows into `$table`\n";
}
$mysql->exec('SET FOREIGN_KEY_CHECKS=1');

echo "\nTransfer complete.\n";

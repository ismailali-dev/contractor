<?php
$db = new PDO('sqlite:' . __DIR__ . '/../database/database.sqlite');
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
echo "TABLES: " . implode(', ', $tables) . "\n\n";

$check = $db->query("SELECT id, email, name FROM users LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
if (count($check) === 0) { echo "NO USERS\n"; } else { echo "USERS:\n"; foreach ($check as $u) echo "- {$u['id']}\t{$u['email']}\t{$u['name']}\n"; }

echo "\nROLES TABLE:\n";
try {
    $roles = $db->query('SELECT id, name FROM roles LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
    if (empty($roles)) echo "(no roles)\n"; else { foreach ($roles as $r) echo "- {$r['id']}\t{$r['name']}\n"; }
} catch (Exception $e) { echo "roles table not found\n"; }

echo "\nMODEL_HAS_ROLES:\n";
try {
    $mhr = $db->query('SELECT * FROM model_has_roles LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
    if (empty($mhr)) echo "(no assignments)\n"; else { foreach ($mhr as $row) echo "- role_id:{$row['role_id']} model_type:{$row['model_type']} model_id:{$row['model_id']}\n"; }
} catch (Exception $e) { echo "model_has_roles table not found\n"; }

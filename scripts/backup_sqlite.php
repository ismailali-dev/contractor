<?php
$src = __DIR__ . '/database/database.sqlite';
$destDir = __DIR__ . '/backups';
if (!file_exists($destDir)) mkdir($destDir, 0755, true);
$dest = $destDir . '/database.sqlite.' . date('Ymd_His');
if (!file_exists($src)) { echo "No sqlite file at $src\n"; exit(1); }
if (copy($src, $dest)) echo "Backed up to $dest\n"; else { echo "Backup failed\n"; exit(1); }

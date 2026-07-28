<?php
$url = $argv[1] ?? 'http://127.0.0.1:8000/entry/contractors?slug=bharat';
$needle = $argv[2] ?? '01KRYQKSH3ZB9PJ0QXFG8P5SSK.jpg';
$s = @file_get_contents($url);
if ($s === false) { echo "Failed to fetch $url\n"; exit(1); }
if (strpos($s, $needle) !== false) echo "FOUND: $needle on page\n"; else echo "MISSING: $needle not on page\n";

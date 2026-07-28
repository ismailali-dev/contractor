<?php
$url = $argv[1] ?? 'http://127.0.0.1:8000/entry?module=contractors&slug=bharat';
$s = @file_get_contents($url);
if ($s === false) { echo "Failed to fetch $url\n"; exit(1); }
file_put_contents('tmp_entry_api.json', $s);
echo "Wrote tmp_entry_api.json (" . strlen($s) . " bytes)\n";
echo substr($s, 0, 2000) . "\n";

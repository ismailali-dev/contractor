<?php
$entryUrl = 'http://127.0.0.1:8000/entry/contractors?slug=bharat';
$apiUrl = 'http://127.0.0.1:8000/api/monaclick/entry?module=contractors&slug=bharat';

$html = @file_get_contents($entryUrl);
if ($html === false) {
    echo "ENTRY_HTML_FAIL\n";
    exit(1);
}

echo preg_match('/monaclick-entry-dynamic\\.js\\?v=\\d+/', $html) ? "ENTRY_JS_VERSIONED\n" : "ENTRY_JS_NOT_VERSIONED\n";
echo preg_match('/monaclick-entry-features-patch\\.js\\?v=\\d+/', $html) ? "PATCH_JS_VERSIONED\n" : "PATCH_JS_NOT_VERSIONED\n";

$api = @file_get_contents($apiUrl);
echo ($api === false) ? "API_FAIL\n" : "API_OK\n";

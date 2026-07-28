<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
$users = User::take(10)->get(['id','email','name'])->toArray();
if (empty($users)) {
    echo "NO_USERS\n";
    exit;
}
foreach ($users as $u) {
    echo $u['id'] . "\t" . ($u['email'] ?? '') . "\t" . ($u['name'] ?? '') . PHP_EOL;
}

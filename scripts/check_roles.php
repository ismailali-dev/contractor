<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use App\Models\User;

$roles = Role::all()->pluck('name')->toArray();
echo "ROLES:\n";
foreach ($roles as $r) echo "- $r\n";

$user = User::find(1);
if (!$user) { echo "NO_USER_1\n"; exit; }

echo "\nUser 1 roles: \n";
foreach ($user->getRoleNames() as $r) echo "- $r\n";

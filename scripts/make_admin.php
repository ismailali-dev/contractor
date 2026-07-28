<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use App\Models\User;

$email = $argv[1] ?? 'admin@example.test';
$password = $argv[2] ?? 'password';

$user = User::where('email', $email)->first();
if (!$user) {
    $user = User::create(['name' => 'Admin', 'email' => $email, 'password' => bcrypt($password)]);
    echo "Created user {$email} with password {$password}\n";
} else {
    echo "Found user {$email}\n";
}

$role = Role::firstOrCreate(['name' => 'admin']);
$user->assignRole('admin');

echo "Assigned role 'admin' to {$email}\n";

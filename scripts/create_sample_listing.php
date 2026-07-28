<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

$user = User::first();
$categoryId = DB::table('categories')->value('id');
$cityId = DB::table('cities')->value('id');
if (!$user || !$categoryId || !$cityId) { echo "Missing user/category/city\n"; exit(1); }

$title = 'Sample Listing from Agent ' . date('His');
$slug = Str::slug($title) . '-' . time();

$l = Listing::create([
    'user_id' => $user->id,
    'category_id' => $categoryId,
    'city_id' => $cityId,
    'module' => 'real-estate',
    'title' => $title,
    'slug' => $slug,
    'excerpt' => 'Sample listing created for frontend test',
    'price' => '$1000',
    'price_amount' => 1000,
    'status' => 'published',
    'admin_status' => 'published',
    'published_at' => now(),
]);

echo "Created listing id {$l->id} slug {$l->slug} title {$l->title}\n";

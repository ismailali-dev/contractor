<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Listing;

$items = Listing::take(10)->get(['id','title','slug','image','module','status','admin_status'])->toArray();
if(empty($items)) { echo "NO_LISTINGS\n"; exit; }
foreach($items as $i) {
    echo "ID: {$i['id']}\tTitle: " . ($i['title'] ?? '') . "\tImage: " . ($i['image'] ?? '') . "\n";
}

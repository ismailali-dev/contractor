<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Listing;
use Illuminate\Support\Facades\DB;

$slug = $argv[1] ?? 'bharat';
$listing = Listing::where('slug', $slug)->first();
if (!$listing) { echo "Listing not found for slug: $slug\n"; exit(0); }

echo "Listing: id={$listing->id} title={$listing->title} slug={$listing->slug}\n";
echo "image field: " . ($listing->image ?? '(empty)') . "\n";
echo "image_url accessor: " . $listing->image_url . "\n";

$imgUrl = $listing->image_url;
// Try fetching the image URL
echo "Attempting HTTP fetch of image: $imgUrl\n";
ini_set('default_socket_timeout', 5);
$imgContents = @file_get_contents($imgUrl);
if ($imgContents === false) {
    echo "HTTP fetch failed for $imgUrl\n";
} else {
    echo "HTTP fetch succeeded, bytes=" . strlen($imgContents) . "\n";
}

// Check physical public path
$publicPath = __DIR__ . '/../public' . parse_url($imgUrl, PHP_URL_PATH);
echo "Physical public path: " . ($publicPath) . "\n";
echo file_exists($publicPath) ? "File exists on disk\n" : "File missing on disk\n";

$imgs = DB::table('listing_images')->where('listing_id', $listing->id)->get();
echo "listing_images rows: " . count($imgs) . "\n";
foreach ($imgs as $i) {
    $arr = (array) $i;
    echo " - columns: " . implode(', ', array_keys($arr)) . "\n";
    foreach ($arr as $k => $v) {
        echo "    $k => $v\n";
    }
}

$storagePublic = realpath(__DIR__ . '/../storage/app/public');
$publicStorage = realpath(__DIR__ . '/../public/storage');

echo "storage/app/public -> " . ($storagePublic ?: '(missing)') . "\n";
echo "public/storage -> " . ($publicStorage ?: '(missing)') . "\n";

if ($storagePublic && is_dir($storagePublic)) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storagePublic));
    $count=0;
    echo "Files under storage/app/public (first 20):\n";
    foreach ($files as $f) {
        if ($f->isDir()) continue;
        echo " - " . substr($f->getPathname(), strlen(__DIR__ . '/../')) . "\n";
        $count++;
        if ($count>=20) break;
    }
    echo "Total (scanned up to 20): $count\n";
}

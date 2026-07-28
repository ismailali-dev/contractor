<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tables = ['users','listings','listing_images','categories','cities','roles','model_has_roles','migrations'];
foreach($tables as $t){
    try{
        $count = Illuminate\Support\Facades\DB::table($t)->count();
        echo "$t: $count\n";
    }catch(Throwable $e){
        echo "$t: (error or missing)\n";
    }
}

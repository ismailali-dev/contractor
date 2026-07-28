<?php
$src = __DIR__ . '/../storage/app/public';
$dst = __DIR__ . '/../public/storage';

function rr_copy($src, $dst) {
    if (!file_exists($src)) return false;
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    $dir = new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS);
    $it = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $target = $dst . DIRECTORY_SEPARATOR . substr($item->getPathname(), strlen($src) + 1);
        if ($item->isDir()) {
            if (!is_dir($target)) mkdir($target, 0755, true);
        } else {
            copy($item->getPathname(), $target);
        }
    }
    return true;
}

if (rr_copy($src, $dst)) echo "Synced $src -> $dst\n"; else echo "Sync failed\n";

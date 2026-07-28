<?php

namespace App\Observers;

use App\Models\Listing;
use Illuminate\Support\Facades\Cache;

class ListingObserver
{
    public function saved(Listing $listing): void
    {
        $this->flushDashboardCaches();
    }

    public function deleted(Listing $listing): void
    {
        $this->flushDashboardCaches();
    }

    private function flushDashboardCaches(): void
    {
        Cache::forget('admin:listings-overview');
        Cache::forget('admin:module-counts');
    }
}

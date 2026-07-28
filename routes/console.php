<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('monaclick:diagnose-listings {--module= : Optional module filter} {--sample=5 : Sample rows to print}', function () {
    $module = strtolower(trim((string) $this->option('module')));
    $sample = max(0, (int) $this->option('sample'));

    $hasListings = \Illuminate\Support\Facades\Schema::hasTable('listings');
    $hasCities = \Illuminate\Support\Facades\Schema::hasTable('cities');
    $hasCategories = \Illuminate\Support\Facades\Schema::hasTable('categories');

    $this->info('Monaclick listings diagnostics');
    $this->line('---');
    $this->line('tables: ' . json_encode([
        'listings' => $hasListings,
        'cities' => $hasCities,
        'categories' => $hasCategories,
    ]));

    if (!$hasListings) {
        $this->error('Missing `listings` table. Run migrations.');
        return 1;
    }

    $base = \Illuminate\Support\Facades\DB::table('listings');
    if ($module !== '') {
        $base->whereRaw('LOWER(TRIM(module)) = ?', [$module]);
    }

    $total = (clone $base)->count();
    $this->line("total listings" . ($module !== '' ? " (module={$module})" : '') . ": {$total}");

    $byStatus = (clone $base)
        ->selectRaw('status, COUNT(*) as c')
        ->groupBy('status')
        ->orderByDesc('c')
        ->get();
    $this->line('by status: ' . $byStatus->map(fn ($r) => "{$r->status}:{$r->c}")->implode(', '));

    if ($module === '') {
        $byModule = \Illuminate\Support\Facades\DB::table('listings')
            ->selectRaw('module, COUNT(*) as c')
            ->groupBy('module')
            ->orderByDesc('c')
            ->get();
        $this->line('by module: ' . $byModule->map(fn ($r) => "{$r->module}:{$r->c}")->implode(', '));
    }

    if ($hasCities && \Illuminate\Support\Facades\Schema::hasColumn('listings', 'city_id')) {
        $missingCity = (clone $base)
            ->leftJoin('cities', 'cities.id', '=', 'listings.city_id')
            ->whereNull('cities.id')
            ->count();
        $this->line("missing city relation: {$missingCity}");
    }

    if ($hasCategories && \Illuminate\Support\Facades\Schema::hasColumn('listings', 'category_id')) {
        $missingCategory = (clone $base)
            ->leftJoin('categories', 'categories.id', '=', 'listings.category_id')
            ->whereNull('categories.id')
            ->count();
        $this->line("missing category relation: {$missingCategory}");
    }

    if ($sample > 0) {
        $rows = (clone $base)
            ->orderByDesc('id')
            ->limit($sample)
            ->get(['id', 'module', 'status', 'slug', 'title', 'city_id', 'category_id', 'published_at', 'created_at']);
        $this->line('sample rows:');
        foreach ($rows as $r) {
            $this->line(json_encode($r));
        }
    }

    $this->line('---');
    $this->line("tip: if total=0, run: php artisan db:seed --class=ListingSeeder --force");
    return 0;
})->purpose('Print listing counts by status/module and relationship integrity');

Artisan::command('monaclick:import-us-cities {path : Path to CSV/TSV/JSON file} {--truncate : Delete existing state-coded cities first} {--dry-run : Parse only, do not write} {--limit=0 : Stop after N rows}', function () {
    $path = (string) $this->argument('path');
    $limit = (int) $this->option('limit');
    $truncate = (bool) $this->option('truncate');
    $dryRun = (bool) $this->option('dry-run');

    if (!is_file($path)) {
        $this->error("File not found: {$path}");
        return 1;
    }

    if (!\Illuminate\Support\Facades\Schema::hasTable('cities') || !\Illuminate\Support\Facades\Schema::hasColumn('cities', 'state_code')) {
        $this->error('Missing required schema. Run migrations first (cities.state_code).');
        return 1;
    }

    $this->info('Importing US cities (state_code + city name) ...');

    if ($truncate && !$dryRun) {
        $deleted = \Illuminate\Support\Facades\DB::table('cities')->whereNotNull('state_code')->delete();
        $this->info("Truncated state-coded cities: {$deleted}");
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'json') {
        $raw = file_get_contents($path);
        $decoded = json_decode($raw ?: '', true);
        if (!is_array($decoded)) {
            $this->error('Invalid JSON file.');
            return 1;
        }
        $rows = $decoded;
        $isAssoc = array_keys($rows) !== range(0, count($rows) - 1);
        if ($isAssoc) {
            $rows = [$rows];
        }
        $iter = (function () use ($rows) {
            foreach ($rows as $row) {
                yield $row;
            }
        })();
    } else {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $firstLine = trim((string) $file->fgets());
        $delimiter = "\t";
        if (substr_count($firstLine, ',') >= substr_count($firstLine, "\t")) {
            $delimiter = ',';
        }
        $file->setCsvControl($delimiter);
        $file->rewind();

        $header = $file->fgetcsv();
        if (!is_array($header)) {
            $this->error('Could not read header row.');
            return 1;
        }
        $keys = array_map(function ($v) {
            $v = strtolower(trim((string) $v));
            return preg_replace('/\s+/', '_', $v) ?: $v;
        }, $header);

        $iter = (function () use ($file, $keys) {
            while (!$file->eof()) {
                $row = $file->fgetcsv();
                if (!is_array($row) || (count($row) === 1 && trim((string) $row[0]) === '')) {
                    continue;
                }
                $assoc = [];
                foreach ($keys as $i => $k) {
                    $assoc[$k] = $row[$i] ?? null;
                }
                yield $assoc;
            }
        })();
    }

    $stateKeys = ['state', 'state_code', 'state_short', 'stateabbr', 'state_abbr', 'stusps', 'usps', 'st', 'state_name', 'statename', 'state_full', 'statefull', 'state_fips', 'statefp', 'statefp10', 'state_fips_code', 'fips'];
    $nameKeys = ['city', 'city_name', 'place', 'place_name', 'name'];

    $stateNameToCode = [
        'alabama' => 'AL',
        'alaska' => 'AK',
        'arizona' => 'AZ',
        'arkansas' => 'AR',
        'california' => 'CA',
        'colorado' => 'CO',
        'connecticut' => 'CT',
        'delaware' => 'DE',
        'district-of-columbia' => 'DC',
        'florida' => 'FL',
        'georgia' => 'GA',
        'hawaii' => 'HI',
        'idaho' => 'ID',
        'illinois' => 'IL',
        'indiana' => 'IN',
        'iowa' => 'IA',
        'kansas' => 'KS',
        'kentucky' => 'KY',
        'louisiana' => 'LA',
        'maine' => 'ME',
        'maryland' => 'MD',
        'massachusetts' => 'MA',
        'michigan' => 'MI',
        'minnesota' => 'MN',
        'mississippi' => 'MS',
        'missouri' => 'MO',
        'montana' => 'MT',
        'nebraska' => 'NE',
        'nevada' => 'NV',
        'new-hampshire' => 'NH',
        'new-jersey' => 'NJ',
        'new-mexico' => 'NM',
        'new-york' => 'NY',
        'north-carolina' => 'NC',
        'north-dakota' => 'ND',
        'ohio' => 'OH',
        'oklahoma' => 'OK',
        'oregon' => 'OR',
        'pennsylvania' => 'PA',
        'puerto-rico' => 'PR',
        'rhode-island' => 'RI',
        'south-carolina' => 'SC',
        'south-dakota' => 'SD',
        'tennessee' => 'TN',
        'texas' => 'TX',
        'utah' => 'UT',
        'vermont' => 'VT',
        'virginia' => 'VA',
        'washington' => 'WA',
        'west-virginia' => 'WV',
        'wisconsin' => 'WI',
        'wyoming' => 'WY',
    ];

    $stateFipsToCode = [
        '01' => 'AL', '02' => 'AK', '04' => 'AZ', '05' => 'AR', '06' => 'CA', '08' => 'CO', '09' => 'CT',
        '10' => 'DE', '11' => 'DC', '12' => 'FL', '13' => 'GA', '15' => 'HI', '16' => 'ID', '17' => 'IL',
        '18' => 'IN', '19' => 'IA', '20' => 'KS', '21' => 'KY', '22' => 'LA', '23' => 'ME', '24' => 'MD',
        '25' => 'MA', '26' => 'MI', '27' => 'MN', '28' => 'MS', '29' => 'MO', '30' => 'MT', '31' => 'NE',
        '32' => 'NV', '33' => 'NH', '34' => 'NJ', '35' => 'NM', '36' => 'NY', '37' => 'NC', '38' => 'ND',
        '39' => 'OH', '40' => 'OK', '41' => 'OR', '42' => 'PA', '44' => 'RI', '45' => 'SC', '46' => 'SD',
        '47' => 'TN', '48' => 'TX', '49' => 'UT', '50' => 'VT', '51' => 'VA', '53' => 'WA', '54' => 'WV',
        '55' => 'WI', '56' => 'WY', '72' => 'PR',
    ];

    $existingByState = [];
    $ensureStateCache = function (string $stateCode) use (&$existingByState): void {
        if (isset($existingByState[$stateCode])) return;
        $existingByState[$stateCode] = [];
        $slugs = \Illuminate\Support\Facades\DB::table('cities')
            ->where('state_code', $stateCode)
            ->pluck('slug');
        foreach ($slugs as $slug) {
            $s = trim((string) $slug);
            if ($s !== '') $existingByState[$stateCode][$s] = true;
        }
    };

    $makeUniqueSlug = function (string $stateCode, string $base) use (&$existingByState, $ensureStateCache): string {
        $ensureStateCache($stateCode);
        $slug = $base !== '' ? $base : 'city';
        if (!isset($existingByState[$stateCode][$slug])) {
            $existingByState[$stateCode][$slug] = true;
            return $slug;
        }
        $counter = 2;
        while (isset($existingByState[$stateCode]["{$slug}-{$counter}"])) {
            $counter++;
        }
        $unique = "{$slug}-{$counter}";
        $existingByState[$stateCode][$unique] = true;
        return $unique;
    };

    $batch = [];
    $batchSize = 1000;
    $seen = 0;
    $written = 0;
    $skipped = 0;

    foreach ($iter as $row) {
        if (!is_array($row)) {
            $skipped++;
            continue;
        }

        $stateRaw = '';
        foreach ($stateKeys as $k) {
            if (array_key_exists($k, $row) && trim((string) $row[$k]) !== '') {
                $stateRaw = (string) $row[$k];
                break;
            }
            // Gazetteer uses USPS
            $k2 = strtoupper($k);
            if (array_key_exists($k2, $row) && trim((string) $row[$k2]) !== '') {
                $stateRaw = (string) $row[$k2];
                break;
            }
        }
        $stateCode = strtoupper(trim($stateRaw));
        if (!preg_match('/^[A-Z]{2}$/', $stateCode)) {
            $stateSlug = \Illuminate\Support\Str::slug((string) $stateRaw);
            $stateCode = (string) ($stateNameToCode[$stateSlug] ?? '');
        }
        if (!preg_match('/^[A-Z]{2}$/', $stateCode)) {
            $fips = preg_replace('/\D+/', '', (string) $stateRaw) ?: '';
            if ($fips !== '') {
                $fips = str_pad($fips, 2, '0', STR_PAD_LEFT);
                $stateCode = (string) ($stateFipsToCode[$fips] ?? '');
            }
        }
        if (!preg_match('/^[A-Z]{2}$/', $stateCode)) {
            $skipped++;
            continue;
        }

        $nameRaw = '';
        foreach ($nameKeys as $k) {
            if (array_key_exists($k, $row) && trim((string) $row[$k]) !== '') {
                $nameRaw = (string) $row[$k];
                break;
            }
            $k2 = strtoupper($k);
            if (array_key_exists($k2, $row) && trim((string) $row[$k2]) !== '') {
                $nameRaw = (string) $row[$k2];
                break;
            }
        }
        $name = trim(preg_replace('/\s+/', ' ', (string) $nameRaw) ?: '');
        if ($name === '' || strlen($name) > 255) {
            $skipped++;
            continue;
        }

        // Normalize title casing lightly (keep acronyms).
        $display = ucwords(strtolower($name));
        $slugBase = \Illuminate\Support\Str::slug($name);
        if ($slugBase === '') {
            $slugBase = 'city';
        }
        $slug = $makeUniqueSlug($stateCode, $slugBase);

        $now = now();
        $batch[] = [
            'name' => $display,
            'slug' => $slug,
            'state_code' => $stateCode,
            'is_active' => 1,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $seen++;
        if ($limit > 0 && $seen >= $limit) break;

        if (count($batch) >= $batchSize) {
            if (!$dryRun) {
                \Illuminate\Support\Facades\DB::table('cities')->upsert(
                    $batch,
                    ['state_code', 'slug'],
                    ['name', 'is_active', 'updated_at']
                );
                $written += count($batch);
            }
            $batch = [];
        }
    }

    if (count($batch)) {
        if (!$dryRun) {
            \Illuminate\Support\Facades\DB::table('cities')->upsert(
                $batch,
                ['state_code', 'slug'],
                ['name', 'is_active', 'updated_at']
            );
            $written += count($batch);
        }
    }

    $this->info("Parsed rows: {$seen}, written: {$written}, skipped: {$skipped}" . ($dryRun ? ' (dry-run)' : ''));
    $this->info('Done.');
    return 0;
})->purpose('Import a US cities dataset into cities table (state_code + name)');

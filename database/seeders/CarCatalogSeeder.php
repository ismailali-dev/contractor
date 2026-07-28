<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CarCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('car_makes') || !DB::getSchemaBuilder()->hasTable('car_models')) {
            return;
        }

        $catalog = [
            'Toyota' => ['Corolla', 'Camry', 'RAV4', 'Highlander', 'Prius'],
            'Mercedes-Benz' => ['A-Class', 'C-Class', 'E-Class', 'S-Class', 'GLA', 'GLC', 'GLE', 'GLS', 'AMG GT'],
            'BMW' => ['3 Series', '5 Series', '7 Series', 'X3', 'X5'],
            'Honda' => ['Civic', 'Accord', 'CR-V', 'Pilot', 'Fit'],
            'Ford' => ['F-150', 'Mustang', 'Explorer', 'Escape', 'Ranger'],
            'Chevrolet' => ['Silverado', 'Malibu', 'Equinox', 'Tahoe', 'Camaro'],
            'Audi' => ['A3', 'A4', 'A6', 'Q5', 'Q7'],
            'Tesla' => ['Model 3', 'Model S', 'Model X', 'Model Y'],
            'Nissan' => ['Altima', 'Sentra', 'Rogue', 'Pathfinder', 'Leaf'],
            'Volkswagen' => ['Golf', 'Jetta', 'Passat', 'Tiguan', 'Atlas'],
        ];

        $now = now();
        $makeRows = [];
        $order = 1;
        foreach (array_keys($catalog) as $makeName) {
            $makeRows[] = [
                'name' => $makeName,
                'slug' => Str::slug($makeName) ?: 'make',
                'is_active' => 1,
                'sort_order' => $order++,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('car_makes')->upsert($makeRows, ['slug'], ['name', 'is_active', 'sort_order', 'updated_at']);

        $makeMap = DB::table('car_makes')->pluck('id', 'name');
        $modelRows = [];
        foreach ($catalog as $makeName => $models) {
            $makeId = (int) ($makeMap[$makeName] ?? 0);
            if ($makeId <= 0) continue;
            $modelOrder = 1;
            foreach ($models as $modelName) {
                $modelRows[] = [
                    'make_id' => $makeId,
                    'name' => $modelName,
                    'slug' => Str::slug($modelName) ?: 'model',
                    'is_active' => 1,
                    'sort_order' => $modelOrder++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('car_models')->upsert($modelRows, ['make_id', 'slug'], ['name', 'is_active', 'sort_order', 'updated_at']);
    }
}


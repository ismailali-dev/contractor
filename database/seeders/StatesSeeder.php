<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatesSeeder extends Seeder
{
    public function run(): void
    {
        $states = [
            ['code' => 'AL', 'name' => 'Alabama'],
            ['code' => 'AK', 'name' => 'Alaska'],
            ['code' => 'AZ', 'name' => 'Arizona'],
            ['code' => 'AR', 'name' => 'Arkansas'],
            ['code' => 'CA', 'name' => 'California'],
            ['code' => 'CO', 'name' => 'Colorado'],
            ['code' => 'CT', 'name' => 'Connecticut'],
            ['code' => 'DE', 'name' => 'Delaware'],
            ['code' => 'DC', 'name' => 'District of Columbia'],
            ['code' => 'FL', 'name' => 'Florida'],
            ['code' => 'GA', 'name' => 'Georgia'],
            ['code' => 'HI', 'name' => 'Hawaii'],
            ['code' => 'ID', 'name' => 'Idaho'],
            ['code' => 'IL', 'name' => 'Illinois'],
            ['code' => 'IN', 'name' => 'Indiana'],
            ['code' => 'IA', 'name' => 'Iowa'],
            ['code' => 'KS', 'name' => 'Kansas'],
            ['code' => 'KY', 'name' => 'Kentucky'],
            ['code' => 'LA', 'name' => 'Louisiana'],
            ['code' => 'ME', 'name' => 'Maine'],
            ['code' => 'MD', 'name' => 'Maryland'],
            ['code' => 'MA', 'name' => 'Massachusetts'],
            ['code' => 'MI', 'name' => 'Michigan'],
            ['code' => 'MN', 'name' => 'Minnesota'],
            ['code' => 'MS', 'name' => 'Mississippi'],
            ['code' => 'MO', 'name' => 'Missouri'],
            ['code' => 'MT', 'name' => 'Montana'],
            ['code' => 'NE', 'name' => 'Nebraska'],
            ['code' => 'NV', 'name' => 'Nevada'],
            ['code' => 'NH', 'name' => 'New Hampshire'],
            ['code' => 'NJ', 'name' => 'New Jersey'],
            ['code' => 'NM', 'name' => 'New Mexico'],
            ['code' => 'NY', 'name' => 'New York'],
            ['code' => 'NC', 'name' => 'North Carolina'],
            ['code' => 'ND', 'name' => 'North Dakota'],
            ['code' => 'OH', 'name' => 'Ohio'],
            ['code' => 'OK', 'name' => 'Oklahoma'],
            ['code' => 'OR', 'name' => 'Oregon'],
            ['code' => 'PA', 'name' => 'Pennsylvania'],
            ['code' => 'PR', 'name' => 'Puerto Rico'],
            ['code' => 'RI', 'name' => 'Rhode Island'],
            ['code' => 'SC', 'name' => 'South Carolina'],
            ['code' => 'SD', 'name' => 'South Dakota'],
            ['code' => 'TN', 'name' => 'Tennessee'],
            ['code' => 'TX', 'name' => 'Texas'],
            ['code' => 'UT', 'name' => 'Utah'],
            ['code' => 'VT', 'name' => 'Vermont'],
            ['code' => 'VA', 'name' => 'Virginia'],
            ['code' => 'WA', 'name' => 'Washington'],
            ['code' => 'WV', 'name' => 'West Virginia'],
            ['code' => 'WI', 'name' => 'Wisconsin'],
            ['code' => 'WY', 'name' => 'Wyoming'],
        ];

        $now = now();
        $rows = [];
        $order = 1;
        foreach ($states as $state) {
            $rows[] = [
                'country_code' => 'US',
                'code' => $state['code'],
                'name' => $state['name'],
                'is_active' => 1,
                'sort_order' => $order++,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('states')->upsert($rows, ['country_code', 'code'], ['name', 'is_active', 'sort_order', 'updated_at']);
    }
}


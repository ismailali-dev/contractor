<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'vendor']);
        Role::firstOrCreate(['name' => 'user']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@monaclick.test'],
            [
                'name' => 'Monaclick Admin',
                'password' => 'password',
            ]
        );

        $admin->assignRole('admin');

        $this->call(StatesSeeder::class);
        $this->call(CarCatalogSeeder::class);
        $this->call(ListingSeeder::class);
    }
}

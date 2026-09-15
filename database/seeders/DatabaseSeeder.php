<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        AdminUser::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $this->call(ProductCatalogSeeder::class);
    }
}

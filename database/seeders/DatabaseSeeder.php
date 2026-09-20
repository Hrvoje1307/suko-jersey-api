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
     *
     * Deploy na Railwayu pokreće `db:seed` pri svakom startu, pa ovaj seeder
     * mora biti bezopasan u produkciji. Bez ove zaštite bi ondje nastao admin
     * `admin@example.com` s lozinkom `password` (vidi AdminUserFactory), a
     * drugi deploy bi puknuo na unique indeksu emaila i oborio aplikaciju.
     *
     * Katalog se u produkciju i dalje može ubaciti namjerno, jer `--class`
     * zaobilazi ovaj seeder:
     *   php artisan db:seed --class=ProductCatalogSeeder --force
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Produkcija: demo seed preskočen.');

            return;
        }

        AdminUser::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $this->call(ProductCatalogSeeder::class);
    }
}

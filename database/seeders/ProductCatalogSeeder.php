<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Demo katalog: 18 proizvoda kroz sve tri kategorije, s namjerno različitim
 * setovima veličina (pa `?size=M` stvarno suzi listu), različitim sezonama
 * uključujući jednu bez sezone, te po jednim sold_out i draft proizvodom.
 *
 * Idempotentan je — ključ je `name`, pa ponovno pokretanje osvježava podatke
 * umjesto da duplicira katalog.
 *
 * Protiv Supabasea: php artisan db:seed --class=ProductCatalogSeeder --database=supabase
 */
class ProductCatalogSeeder extends Seeder
{
    /** Standardni set veličina za odrasle. */
    private const ADULT = ['S' => 8, 'M' => 12, 'L' => 10, 'XL' => 6];

    public function run(): void
    {
        foreach ($this->catalog() as $index => $row) {
            $sizes = $row['sizes'];
            $players = $row['players'] ?? [];
            unset($row['sizes'], $row['players']);

            $product = Product::updateOrCreate(['name' => $row['name']], $row);

            // Razmaknuti datumi kreiranja: inače cijeli katalog dijeli istu
            // sekundu pa `?sort=created_at` padne na `id desc` tiebreaker i
            // sekcija "New in stock" izgleda kao da ne radi.
            $product->forceFill(['created_at' => now()->subDays($index)])->save();

            foreach ($sizes as $size => $stock) {
                $product->variants()->updateOrCreate(
                    ['size' => (string) $size],
                    ['stock_quantity' => $stock]
                );
            }

            $product->images()->updateOrCreate(
                ['url' => $this->placeholderImage($row['name'])],
                ['sort_order' => 1, 'is_primary' => true]
            );

            foreach ($players as $index => [$playerName, $number]) {
                $product->players()->updateOrCreate(
                    ['player_name' => $playerName],
                    ['player_number' => $number, 'sort_order' => $index + 1]
                );
            }
        }
    }

    /**
     * Placeholder dok ne dođu prave fotke — vanjski URL, isto kao `image_urls`
     * iz admina, pa ga `syncExternalImages` tretira kao vanjsku sliku.
     */
    private function placeholderImage(string $name): string
    {
        return 'https://placehold.co/800x1000/0b1f3a/ffffff?text='.rawurlencode($name);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            // ---------- Nogomet ----------
            [
                'name' => 'Real Madrid Home 25/26',
                'club_or_team' => 'Real Madrid',
                'category' => 'football',
                'kit_type' => 'home',
                'audience' => 'men',
                'season' => '25/26',
                'personalization' => 'both',
                'price' => 94.99,
                'description' => 'Bijeli domaći dres Real Madrida, sezona 2025/26.',
                'status' => 'active',
                'sizes' => self::ADULT + ['XXL' => 3],
                'players' => [['Kylian Mbappé', '9'], ['Jude Bellingham', '5'], ['Vinícius Júnior', '7']],
            ],
            [
                'name' => 'Real Madrid Away 25/26',
                'club_or_team' => 'Real Madrid',
                'category' => 'football',
                'kit_type' => 'away',
                'audience' => 'unisex',
                'season' => '25/26',
                'personalization' => 'custom_text',
                'price' => 89.99,
                'description' => 'Gostujući dres Real Madrida s mogućnošću upisa imena i broja.',
                'status' => 'active',
                'sizes' => ['M' => 4, 'L' => 7, 'XL' => 2],
            ],
            [
                'name' => 'GNK Dinamo Home 25/26',
                'club_or_team' => 'GNK Dinamo',
                'category' => 'football',
                'kit_type' => 'home',
                'audience' => 'men',
                'season' => '25/26',
                'personalization' => 'preset_only',
                'price' => 74.99,
                'description' => 'Plavi domaći dres Dinama za sezonu 2025/26.',
                'status' => 'active',
                'sizes' => self::ADULT,
                'players' => [['Petar Sučić', '10'], ['Josip Mišić', '6']],
            ],
            [
                'name' => 'GNK Dinamo Home Kids 25/26',
                'club_or_team' => 'GNK Dinamo',
                'category' => 'football',
                'kit_type' => 'home',
                'audience' => 'kids',
                'season' => '25/26',
                'personalization' => 'none',
                'price' => 54.99,
                'description' => 'Dječja verzija domaćeg dresa, veličine 128–164.',
                'status' => 'active',
                'sizes' => ['128' => 5, '140' => 6, '152' => 4, '164' => 2],
            ],
            [
                'name' => 'HNK Hajduk Home 25/26',
                'club_or_team' => 'HNK Hajduk',
                'category' => 'football',
                'kit_type' => 'home',
                'audience' => 'unisex',
                'season' => '25/26',
                'personalization' => 'custom_text',
                'price' => 74.99,
                'description' => 'Bijeli domaći dres Hajduka.',
                'status' => 'active',
                'sizes' => ['S' => 6, 'M' => 9, 'L' => 9, 'XL' => 4, 'XXL' => 2],
            ],
            [
                'name' => 'FC Barcelona Home 24/25',
                'club_or_team' => 'FC Barcelona',
                'category' => 'football',
                'kit_type' => 'home',
                'audience' => 'men',
                'season' => '24/25',
                'personalization' => 'both',
                'price' => 84.99,
                'description' => 'Prošlosezonski domaći dres Barcelone, zadnji komadi.',
                'status' => 'active',
                'sizes' => ['L' => 2, 'XL' => 1],
                'players' => [['Lamine Yamal', '19'], ['Robert Lewandowski', '9']],
            ],
            [
                'name' => 'Manchester City Away 24/25',
                'club_or_team' => 'Manchester City',
                'category' => 'football',
                'kit_type' => 'away',
                'audience' => 'women',
                'season' => '24/25',
                'personalization' => 'none',
                'price' => 79.99,
                'description' => 'Ženski kroj gostujućeg dresa Manchester Cityja.',
                'status' => 'active',
                'sizes' => ['XS' => 3, 'S' => 5, 'M' => 5],
            ],
            [
                'name' => 'Liverpool FC Third 24/25',
                'club_or_team' => 'Liverpool FC',
                'category' => 'football',
                'kit_type' => 'third',
                'audience' => 'unisex',
                'season' => '24/25',
                'personalization' => 'custom_text',
                'price' => 82.00,
                'description' => 'Treći dres Liverpoola — rasprodan, uskoro ponovno na stanju.',
                'status' => 'sold_out',
                'sizes' => ['M' => 0, 'L' => 0],
            ],
            [
                'name' => 'Inter Milan Home 25/26',
                'club_or_team' => 'Inter Milan',
                'category' => 'football',
                'kit_type' => 'home',
                'audience' => 'men',
                'season' => '25/26',
                'personalization' => 'none',
                'price' => 87.50,
                'description' => 'Crno-plavi domaći dres Intera.',
                'status' => 'draft',
                'sizes' => ['M' => 4, 'L' => 4],
            ],

            // ---------- Formula 1 ----------
            [
                'name' => 'Scuderia Ferrari Team Shirt 2025',
                'club_or_team' => 'Scuderia Ferrari',
                'category' => 'f1',
                'kit_type' => 'home',
                'audience' => 'unisex',
                'season' => '2025',
                'personalization' => 'preset_only',
                'price' => 109.00,
                'description' => 'Službena timska majica Ferrarija za sezonu 2025.',
                'status' => 'active',
                'sizes' => self::ADULT + ['XXL' => 4],
                'players' => [['Charles Leclerc', '16'], ['Lewis Hamilton', '44']],
            ],
            [
                'name' => 'Scuderia Ferrari Team Shirt 2024',
                'club_or_team' => 'Scuderia Ferrari',
                'category' => 'f1',
                'kit_type' => 'home',
                'audience' => 'men',
                'season' => '2024',
                'personalization' => 'none',
                'price' => 89.00,
                'description' => 'Timska majica iz sezone 2024.',
                'status' => 'active',
                'sizes' => ['XL' => 3, 'XXL' => 2],
            ],
            [
                'name' => 'McLaren F1 Team Shirt 2025',
                'club_or_team' => 'McLaren F1 Team',
                'category' => 'f1',
                'kit_type' => 'home',
                'audience' => 'unisex',
                'season' => '2025',
                'personalization' => 'preset_only',
                'price' => 104.00,
                'description' => 'Papaya timska majica McLarena.',
                'status' => 'active',
                'sizes' => ['S' => 4, 'M' => 8, 'L' => 8],
                'players' => [['Lando Norris', '4'], ['Oscar Piastri', '81']],
            ],
            [
                'name' => 'Oracle Red Bull Racing Shirt 2025',
                'club_or_team' => 'Oracle Red Bull Racing',
                'category' => 'f1',
                'kit_type' => 'home',
                'audience' => 'men',
                'season' => '2025',
                'personalization' => 'preset_only',
                'price' => 99.00,
                'description' => 'Timska majica Red Bull Racinga.',
                'status' => 'active',
                'sizes' => ['M' => 6, 'L' => 6, 'XL' => 3],
                'players' => [['Max Verstappen', '1']],
            ],
            [
                'name' => 'Mercedes-AMG Petronas Shirt 2025',
                'club_or_team' => 'Mercedes-AMG Petronas',
                'category' => 'f1',
                'kit_type' => 'away',
                'audience' => 'women',
                'season' => '2025',
                'personalization' => 'none',
                'price' => 97.00,
                'description' => 'Ženski kroj timske majice Mercedesa.',
                'status' => 'active',
                'sizes' => ['XS' => 2, 'S' => 4, 'M' => 4],
            ],

            // ---------- Košarka ----------
            [
                'name' => 'Los Angeles Lakers Icon Jersey',
                'club_or_team' => 'Los Angeles Lakers',
                'category' => 'basketball',
                'kit_type' => 'home',
                'audience' => 'men',
                'season' => '25/26',
                'personalization' => 'both',
                'price' => 119.00,
                'description' => 'Žuti Icon Edition dres Lakersa.',
                'status' => 'active',
                'sizes' => self::ADULT,
                'players' => [['LeBron James', '23'], ['Luka Dončić', '77']],
            ],
            [
                'name' => 'Boston Celtics Association Jersey',
                'club_or_team' => 'Boston Celtics',
                'category' => 'basketball',
                'kit_type' => 'away',
                'audience' => 'unisex',
                'season' => '25/26',
                'personalization' => 'custom_text',
                'price' => 114.00,
                'description' => 'Bijeli Association Edition dres Celticsa.',
                'status' => 'active',
                'sizes' => ['M' => 5, 'L' => 7, 'XL' => 5, 'XXL' => 2],
            ],
            [
                'name' => 'KK Cibona Home Jersey',
                'club_or_team' => 'KK Cibona',
                'category' => 'basketball',
                'kit_type' => 'home',
                'audience' => 'unisex',
                // Namjerno bez sezone — retro komad; /products/filters ovo mora preskočiti.
                'season' => null,
                'personalization' => 'none',
                'price' => 69.00,
                'description' => 'Retro domaći dres Cibone, bez oznake sezone.',
                'status' => 'active',
                'sizes' => ['S' => 3, 'M' => 3],
            ],
            [
                'name' => 'Chicago Bulls Statement Jersey',
                'club_or_team' => 'Chicago Bulls',
                'category' => 'basketball',
                'kit_type' => 'third',
                'audience' => 'kids',
                'season' => '24/25',
                'personalization' => 'none',
                'price' => 79.00,
                'description' => 'Dječji Statement Edition dres Bullsa.',
                'status' => 'active',
                'sizes' => ['128' => 4, '140' => 4, '152' => 3],
            ],
        ];
    }
}

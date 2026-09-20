<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Demo katalog za razvoj i QA frontenda: 28 proizvoda kroz sve tri kategorije.
 *
 * Namjerno pokriva rubne slučajeve koje frontend mora moći prikazati:
 * proizvod bez slika i proizvod s četiri slike, dječje brojčane veličine,
 * proizvod bez sezone, sold_out i draft statuse, te razmaknute datume
 * kreiranja.
 *
 * Idempotentan je — ključ je `name`, pa ponovno pokretanje osvježava podatke
 * umjesto da duplicira katalog.
 *
 * Protiv Supabasea: php artisan db:seed --class=ProductCatalogSeeder --database=supabase
 */
class ProductCatalogSeeder extends Seeder
{
    /** Standardni set veličina za odrasle. */
    private const ADULT = ['S', 'M', 'L', 'XL'];

    public function run(): void
    {
        foreach ($this->catalog() as $index => $row) {
            $imageCount = $row['images'] ?? 1;

            $row['images'] = array_map(
                fn (int $i) => $this->placeholderImage($row['name'], $i),
                range(1, max($imageCount, 0)) ?: [],
            );

            if ($imageCount < 1) {
                $row['images'] = [];
            }

            $product = Product::updateOrCreate(['name' => $row['name']], $row);

            // Razmaknuti datumi kreiranja: inače cijeli katalog dijeli istu
            // sekundu pa `?sort=created_at` padne na `id desc` tiebreaker i
            // sekcija "New in stock" izgleda kao da ne radi. Determinističko
            // je namjerno — ponovni seed ne premiješa katalog.
            $product->forceFill(['created_at' => now()->subDays($index * 5)])->save();
        }
    }

    /**
     * Placeholder dok ne dođu prave fotke — vanjski URL, isto kao `image_urls`
     * iz admina, pa ga `syncExternalImages` tretira kao vanjsku sliku.
     */
    private function placeholderImage(string $name, int $position): string
    {
        return 'https://placehold.co/800x1000/0b1f3a/ffffff?text='.rawurlencode($name.' #'.$position);
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
                'type' => 'adult',
                'season' => '25/26',
                'price' => 94.99,
                'description' => 'Bijeli domaći dres Real Madrida, sezona 2025/26. Dres se isporučuje s originalnim ligaškim znakovima.',
                'status' => 'active',
                'sizes' => [...self::ADULT, 'XXL'],
                // Galerija s više slika — thumbnails i swipe.
                'images' => 4,
            ],
            [
                'name' => 'Real Madrid Away 25/26',
                'club_or_team' => 'Real Madrid',
                'category' => 'football',
                'type' => 'adult',
                'season' => '25/26',
                'price' => 89.99,
                'description' => 'Gostujući dres Real Madrida s mogućnošću upisa vlastitog imena i broja.',
                'status' => 'active',
                'sizes' => ['M', 'L', 'XL'],
                'images' => 2,
            ],
            [
                'name' => 'GNK Dinamo Home 25/26',
                'club_or_team' => 'GNK Dinamo',
                'category' => 'football',
                'type' => 'adult',
                'season' => '25/26',
                'price' => 74.99,
                'description' => 'Plavi domaći dres Dinama za sezonu 2025/26.',
                'status' => 'active',
                'sizes' => [...self::ADULT],
                'images' => 3,
            ],
            [
                'name' => 'GNK Dinamo Home Kids 25/26',
                'club_or_team' => 'GNK Dinamo',
                'category' => 'football',
                'type' => 'kids',
                'season' => '25/26',
                'price' => 54.99,
                'description' => 'Dječja verzija domaćeg dresa, veličine 128–164.',
                'status' => 'active',
                'sizes' => ['128', '140', '152', '164'],
            ],
            [
                'name' => 'GNK Dinamo Away 25/26',
                'club_or_team' => 'GNK Dinamo',
                'category' => 'football',
                'type' => 'adult',
                'season' => '25/26',
                'price' => 74.99,
                'description' => 'Bijeli gostujući dres Dinama.',
                'status' => 'active',
                'sizes' => ['S', 'M', 'L', 'XL'],
                'images' => 2,
            ],
            [
                'name' => 'HNK Hajduk Home 25/26',
                'club_or_team' => 'HNK Hajduk',
                'category' => 'football',
                'type' => 'adult',
                'season' => '25/26',
                'price' => 74.99,
                'description' => 'Bijeli domaći dres Hajduka s tradicionalnim plavim detaljima.',
                'status' => 'active',
                'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
                'images' => 3,
            ],
            [
                'name' => 'HNK Hajduk Kids 25/26',
                'club_or_team' => 'HNK Hajduk',
                'category' => 'football',
                'type' => 'kids',
                'season' => '25/26',
                'price' => 52.00,
                'description' => 'Dječji domaći dres Hajduka.',
                'status' => 'active',
                'sizes' => ['128', '140', '152'],
            ],
            [
                'name' => 'FC Barcelona Home 24/25',
                'club_or_team' => 'FC Barcelona',
                'category' => 'football',
                'type' => 'adult',
                'season' => '24/25',
                'price' => 84.99,
                'description' => 'Prošlosezonski domaći dres Barcelone, zadnji komadi.',
                'status' => 'active',
                'sizes' => ['L', 'XL'],
            ],
            [
                'name' => 'Manchester City Away 24/25',
                'club_or_team' => 'Manchester City',
                'category' => 'football',
                'type' => 'adult',
                'season' => '24/25',
                'price' => 79.99,
                'description' => 'Ženski kroj gostujućeg dresa Manchester Cityja.',
                'status' => 'active',
                'sizes' => ['XS', 'S', 'M'],
                'images' => 2,
            ],
            [
                'name' => 'Liverpool FC Home 25/26',
                'club_or_team' => 'Liverpool FC',
                'category' => 'football',
                'type' => 'adult',
                'season' => '25/26',
                'price' => 86.00,
                'description' => 'Crveni domaći dres Liverpoola.',
                'status' => 'active',
                'sizes' => [...self::ADULT],
                'images' => 3,
            ],
            [
                'name' => 'Liverpool FC Third 24/25',
                'club_or_team' => 'Liverpool FC',
                'category' => 'football',
                'type' => 'adult',
                'season' => '24/25',
                'price' => 82.00,
                'description' => 'Treći dres Liverpoola — rasprodan, uskoro ponovno na stanju.',
                'status' => 'sold_out',
                'sizes' => ['M', 'L'],
            ],
            [
                'name' => 'Inter Milan Home 25/26',
                'club_or_team' => 'Inter Milan',
                'category' => 'football',
                'type' => 'adult',
                'season' => '25/26',
                'price' => 87.50,
                'description' => 'Crno-plavi domaći dres Intera — još u pripremi.',
                'status' => 'draft',
                'sizes' => ['M', 'L'],
            ],
            [
                'name' => 'Bayern München Home 25/26',
                'club_or_team' => 'Bayern München',
                'category' => 'football',
                'type' => 'adult',
                'season' => '25/26',
                'price' => 92.00,
                'description' => 'Crveni domaći dres Bayerna.',
                'status' => 'active',
                'sizes' => ['M', 'L', 'XL', 'XXL'],
            ],
            [
                'name' => 'Paris Saint-Germain Away 25/26',
                'club_or_team' => 'Paris Saint-Germain',
                'category' => 'football',
                'type' => 'adult',
                'season' => '25/26',
                'price' => 88.00,
                'description' => 'Gostujući dres PSG-a.',
                'status' => 'active',
                'sizes' => ['S', 'M', 'L'],
                'images' => 2,
            ],

            // ---------- Formula 1 ----------
            [
                'name' => 'Scuderia Ferrari Team Shirt 2025',
                'club_or_team' => 'Scuderia Ferrari',
                'category' => 'formula',
                'type' => 'adult',
                'season' => '2025',
                'price' => 109.00,
                'description' => 'Službena timska majica Ferrarija za sezonu 2025.',
                'status' => 'active',
                'sizes' => [...self::ADULT, 'XXL'],
                'images' => 4,
            ],
            [
                'name' => 'Scuderia Ferrari Team Shirt 2024',
                'club_or_team' => 'Scuderia Ferrari',
                'category' => 'formula',
                'type' => 'adult',
                'season' => '2024',
                'price' => 89.00,
                'description' => 'Timska majica iz sezone 2024, zadnji komadi.',
                'status' => 'active',
                'sizes' => ['XL', 'XXL'],
            ],
            [
                'name' => 'McLaren F1 Team Shirt 2025',
                'club_or_team' => 'McLaren F1 Team',
                'category' => 'formula',
                'type' => 'adult',
                'season' => '2025',
                'price' => 104.00,
                'description' => 'Papaya timska majica McLarena.',
                'status' => 'active',
                'sizes' => ['S', 'M', 'L'],
                'images' => 3,
            ],
            [
                'name' => 'Oracle Red Bull Racing Shirt 2025',
                'club_or_team' => 'Oracle Red Bull Racing',
                'category' => 'formula',
                'type' => 'adult',
                'season' => '2025',
                'price' => 99.00,
                'description' => 'Timska majica Red Bull Racinga.',
                'status' => 'active',
                'sizes' => ['M', 'L', 'XL'],
            ],
            [
                'name' => 'Mercedes-AMG Petronas Shirt 2025',
                'club_or_team' => 'Mercedes-AMG Petronas',
                'category' => 'formula',
                'type' => 'adult',
                'season' => '2025',
                'price' => 97.00,
                'description' => 'Ženski kroj timske majice Mercedesa.',
                'status' => 'active',
                'sizes' => ['XS', 'S', 'M'],
                'images' => 2,
            ],
            [
                'name' => 'Aston Martin Aramco Shirt 2025',
                'club_or_team' => 'Aston Martin Aramco',
                'category' => 'formula',
                'type' => 'adult',
                'season' => '2025',
                'price' => 95.00,
                'description' => 'Zelena timska majica Aston Martina.',
                'status' => 'active',
                'sizes' => ['M', 'L', 'XL'],
            ],
            [
                'name' => 'Williams Racing Shirt 2024',
                'club_or_team' => 'Williams Racing',
                'category' => 'formula',
                'type' => 'adult',
                'season' => '2024',
                'price' => 79.00,
                'description' => 'Timska majica Williamsa iz 2024. — bez fotografija u katalogu.',
                'status' => 'active',
                'sizes' => ['L', 'XL'],
                // Namjerno bez slika — frontend mora pokazati placeholder.
                'images' => 0,
            ],
            [
                'name' => 'Alpine F1 Team Shirt 2025',
                'club_or_team' => 'Alpine F1 Team',
                'category' => 'formula',
                'type' => 'adult',
                'season' => '2025',
                'price' => 91.00,
                'description' => 'Plavo-roza timska majica Alpinea — priprema se za objavu.',
                'status' => 'draft',
                'sizes' => ['M', 'L'],
            ],

            // ---------- Košarka ----------
            [
                'name' => 'Los Angeles Lakers Icon Jersey',
                'club_or_team' => 'Los Angeles Lakers',
                'category' => 'basketball',
                'type' => 'adult',
                'season' => '25/26',
                'price' => 119.00,
                'description' => 'Žuti Icon Edition dres Lakersa.',
                'status' => 'active',
                'sizes' => [...self::ADULT],
                'images' => 4,
            ],
            [
                'name' => 'Boston Celtics Association Jersey',
                'club_or_team' => 'Boston Celtics',
                'category' => 'basketball',
                'type' => 'adult',
                'season' => '25/26',
                'price' => 114.00,
                'description' => 'Bijeli Association Edition dres Celticsa.',
                'status' => 'active',
                'sizes' => ['M', 'L', 'XL', 'XXL'],
                'images' => 2,
            ],
            [
                'name' => 'KK Cibona Home Jersey',
                'club_or_team' => 'KK Cibona',
                'category' => 'basketball',
                'type' => 'adult',
                // Namjerno bez sezone — retro komad; /products/filters ovo mora preskočiti.
                'season' => null,
                'price' => 69.00,
                'description' => 'Retro domaći dres Cibone, bez oznake sezone.',
                'status' => 'active',
                'sizes' => ['S', 'M'],
            ],
            [
                'name' => 'KK Split Retro Jersey',
                'club_or_team' => 'KK Split',
                'category' => 'basketball',
                'type' => 'adult',
                'season' => '24/25',
                'price' => 72.00,
                'description' => 'Retro dres KK Splita.',
                'status' => 'active',
                'sizes' => ['M', 'L', 'XL'],
            ],
            [
                'name' => 'Chicago Bulls Statement Jersey',
                'club_or_team' => 'Chicago Bulls',
                'category' => 'basketball',
                'type' => 'kids',
                'season' => '24/25',
                'price' => 79.00,
                'description' => 'Dječji Statement Edition dres Bullsa.',
                'status' => 'active',
                'sizes' => ['128', '140', '152'],
                'images' => 2,
            ],
            [
                'name' => 'Golden State Warriors City Jersey',
                'club_or_team' => 'Golden State Warriors',
                'category' => 'basketball',
                'type' => 'adult',
                'season' => '24/25',
                'price' => 124.00,
                'description' => 'City Edition dres Warriorsa — rasprodan.',
                'status' => 'sold_out',
                'sizes' => ['M', 'L', 'XL'],
            ],
        ];
    }
}

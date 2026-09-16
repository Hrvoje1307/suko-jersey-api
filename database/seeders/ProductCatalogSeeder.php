<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Demo katalog za razvoj i QA frontenda: 26 proizvoda (22 aktivna, 2 sold_out,
 * 2 draft) kroz sve tri kategorije.
 *
 * Namjerno pokriva rubne slučajeve koje frontend mora moći prikazati:
 * sve četiri vrijednosti `personalization`, proizvod bez slika i proizvod s
 * četiri slike, varijante s nultom zalihom, dječje brojčane veličine,
 * igrače bez broja, proizvod bez sezone, te razmaknute datume kreiranja.
 *
 * Idempotentan je — ključ je `name`, pa ponovno pokretanje osvježava podatke
 * umjesto da duplicira katalog. Slike se generiraju samo proizvodima koji ih
 * još nemaju, da ponovni seed ne pobriše fotke uploadane kroz admin.
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
            $imageCount = $row['images'] ?? 1;
            unset($row['sizes'], $row['players'], $row['images']);

            $product = Product::updateOrCreate(['name' => $row['name']], $row);

            // Razmaknuti datumi kreiranja: inače cijeli katalog dijeli istu
            // sekundu pa `?sort=created_at` padne na `id desc` tiebreaker i
            // sekcija "New in stock" izgleda kao da ne radi. Determinističko
            // je namjerno — ponovni seed ne premiješa katalog.
            $product->forceFill(['created_at' => now()->subDays($index * 5)])->save();

            foreach ($sizes as $size => $stock) {
                $product->variants()->updateOrCreate(
                    ['size' => (string) $size],
                    ['stock_quantity' => $stock]
                );
            }

            // Samo ako proizvod nema nijednu sliku — tako `images: []` slučaj
            // ostaje prazan, a uploadane fotke preživljavaju ponovni seed.
            if ($product->images()->doesntExist()) {
                for ($i = 1; $i <= $imageCount; $i++) {
                    $product->images()->create([
                        'url' => $this->placeholderImage($row['name'], $i),
                        'sort_order' => $i,
                        'is_primary' => $i === 1,
                    ]);
                }
            }

            foreach ($players as $sortOrder => [$playerName, $number]) {
                $product->players()->updateOrCreate(
                    ['player_name' => $playerName],
                    ['player_number' => $number, 'sort_order' => $sortOrder + 1]
                );
            }
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
                'kit_type' => 'home',
                'audience' => 'men',
                'season' => '25/26',
                // `both` — kupac bira gotovog igrača ILI upisuje svoje ime.
                'personalization' => 'both',
                'price' => 94.99,
                'description' => 'Bijeli domaći dres Real Madrida, sezona 2025/26. Dres se isporučuje s originalnim ligaškim znakovima.',
                'status' => 'active',
                'sizes' => self::ADULT + ['XXL' => 3],
                // Galerija s više slika — thumbnails i swipe.
                'images' => 4,
                'players' => [['Kylian Mbappé', '9'], ['Jude Bellingham', '5'], ['Vinícius Júnior', '7'], ['Thibaut Courtois', '1']],
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
                'description' => 'Gostujući dres Real Madrida s mogućnošću upisa vlastitog imena i broja.',
                'status' => 'active',
                // XL rasprodan, ali veličina se i dalje prikazuje (zaključana).
                'sizes' => ['M' => 4, 'L' => 7, 'XL' => 0],
                'images' => 2,
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
                'images' => 3,
                // Zadnji igrač namjerno bez broja — frontend to mora podnijeti.
                'players' => [['Petar Sučić', '10'], ['Josip Mišić', '6'], ['Dion Drena Beljo', '9'], ['Rezervni igrač', null]],
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
                'name' => 'GNK Dinamo Away 25/26',
                'club_or_team' => 'GNK Dinamo',
                'category' => 'football',
                'kit_type' => 'away',
                'audience' => 'unisex',
                'season' => '25/26',
                'personalization' => 'custom_text',
                'price' => 74.99,
                'description' => 'Bijeli gostujući dres Dinama.',
                'status' => 'active',
                'sizes' => ['S' => 4, 'M' => 6, 'L' => 6, 'XL' => 3],
                'images' => 2,
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
                'description' => 'Bijeli domaći dres Hajduka s tradicionalnim plavim detaljima.',
                'status' => 'active',
                'sizes' => ['S' => 6, 'M' => 9, 'L' => 9, 'XL' => 4, 'XXL' => 2],
                'images' => 3,
            ],
            [
                'name' => 'HNK Hajduk Kids 25/26',
                'club_or_team' => 'HNK Hajduk',
                'category' => 'football',
                'kit_type' => 'home',
                'audience' => 'kids',
                'season' => '25/26',
                'personalization' => 'none',
                'price' => 52.00,
                'description' => 'Dječji domaći dres Hajduka.',
                'status' => 'active',
                'sizes' => ['128' => 4, '140' => 3, '152' => 0],
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
                'sizes' => ['L' => 2, 'XL' => 0],
                'players' => [['Lamine Yamal', '19'], ['Robert Lewandowski', '9'], ['Pedri', '8']],
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
                'images' => 2,
            ],
            [
                'name' => 'Liverpool FC Home 25/26',
                'club_or_team' => 'Liverpool FC',
                'category' => 'football',
                'kit_type' => 'home',
                'audience' => 'men',
                'season' => '25/26',
                'personalization' => 'preset_only',
                'price' => 86.00,
                'description' => 'Crveni domaći dres Liverpoola.',
                'status' => 'active',
                'sizes' => self::ADULT,
                'images' => 3,
                'players' => [['Mohamed Salah', '11'], ['Virgil van Dijk', '4'], ['Alexis Mac Allister', '10']],
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
                'description' => 'Crno-plavi domaći dres Intera — još u pripremi.',
                'status' => 'draft',
                'sizes' => ['M' => 4, 'L' => 4],
            ],
            [
                'name' => 'Bayern München Home 25/26',
                'club_or_team' => 'Bayern München',
                'category' => 'football',
                'kit_type' => 'home',
                'audience' => 'men',
                'season' => '25/26',
                'personalization' => 'both',
                'price' => 92.00,
                'description' => 'Crveni domaći dres Bayerna.',
                'status' => 'active',
                'sizes' => ['M' => 7, 'L' => 7, 'XL' => 5, 'XXL' => 2],
                'players' => [['Harry Kane', '9'], ['Jamal Musiala', '42'], ['Joshua Kimmich', '6']],
            ],
            [
                'name' => 'Paris Saint-Germain Away 25/26',
                'club_or_team' => 'Paris Saint-Germain',
                'category' => 'football',
                'kit_type' => 'away',
                'audience' => 'unisex',
                'season' => '25/26',
                'personalization' => 'custom_text',
                'price' => 88.00,
                'description' => 'Gostujući dres PSG-a.',
                'status' => 'active',
                'sizes' => ['S' => 5, 'M' => 5, 'L' => 5],
                'images' => 2,
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
                'images' => 4,
                'players' => [['Charles Leclerc', '16'], ['Lewis Hamilton', '44'], ['Antonio Giovinazzi', null]],
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
                'description' => 'Timska majica iz sezone 2024, zadnji komadi.',
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
                'images' => 3,
                'players' => [['Lando Norris', '4'], ['Oscar Piastri', '81'], ['Test vozač', null]],
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
                'sizes' => ['M' => 6, 'L' => 6, 'XL' => 0],
                'players' => [['Max Verstappen', '1'], ['Liam Lawson', '30'], ['Rezervni vozač', null]],
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
                'images' => 2,
            ],
            [
                'name' => 'Aston Martin Aramco Shirt 2025',
                'club_or_team' => 'Aston Martin Aramco',
                'category' => 'f1',
                'kit_type' => 'home',
                'audience' => 'unisex',
                'season' => '2025',
                'personalization' => 'both',
                'price' => 95.00,
                'description' => 'Zelena timska majica Aston Martina.',
                'status' => 'active',
                'sizes' => ['M' => 5, 'L' => 5, 'XL' => 4],
                'players' => [['Fernando Alonso', '14'], ['Lance Stroll', '18'], ['Felipe Drugovich', null]],
            ],
            [
                'name' => 'Williams Racing Shirt 2024',
                'club_or_team' => 'Williams Racing',
                'category' => 'f1',
                'kit_type' => 'away',
                'audience' => 'men',
                'season' => '2024',
                'personalization' => 'none',
                'price' => 79.00,
                'description' => 'Timska majica Williamsa iz 2024. — bez fotografija u katalogu.',
                'status' => 'active',
                'sizes' => ['L' => 4, 'XL' => 2],
                // Namjerno bez slika — frontend mora pokazati placeholder.
                'images' => 0,
            ],
            [
                'name' => 'Alpine F1 Team Shirt 2025',
                'club_or_team' => 'Alpine F1 Team',
                'category' => 'f1',
                'kit_type' => 'home',
                'audience' => 'unisex',
                'season' => '2025',
                'personalization' => 'custom_text',
                'price' => 91.00,
                'description' => 'Plavo-roza timska majica Alpinea — priprema se za objavu.',
                'status' => 'draft',
                'sizes' => ['M' => 3, 'L' => 3],
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
                'images' => 4,
                'players' => [['LeBron James', '23'], ['Luka Dončić', '77'], ['Austin Reaves', '15']],
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
                'images' => 2,
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
                'name' => 'KK Split Retro Jersey',
                'club_or_team' => 'KK Split',
                'category' => 'basketball',
                'kit_type' => 'home',
                'audience' => 'unisex',
                'season' => '24/25',
                'personalization' => 'preset_only',
                'price' => 72.00,
                'description' => 'Retro dres KK Splita.',
                'status' => 'active',
                'sizes' => ['M' => 2, 'L' => 2, 'XL' => 0],
                'players' => [['Toni Kukoč', '7'], ['Dino Rađa', '11'], ['Nepoznati igrač', null]],
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
                'images' => 2,
            ],
            [
                'name' => 'Golden State Warriors City Jersey',
                'club_or_team' => 'Golden State Warriors',
                'category' => 'basketball',
                'kit_type' => 'fourth',
                'audience' => 'men',
                'season' => '24/25',
                'personalization' => 'both',
                'price' => 124.00,
                'description' => 'City Edition dres Warriorsa — rasprodan.',
                'status' => 'sold_out',
                'sizes' => ['M' => 0, 'L' => 0, 'XL' => 0],
                'players' => [['Stephen Curry', '30'], ['Draymond Green', '23'], ['Jonathan Kuminga', null]],
            ],
        ];
    }
}

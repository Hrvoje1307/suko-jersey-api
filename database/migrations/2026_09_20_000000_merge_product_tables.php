<?php

/*
 * Spajanje product_variants / product_images / product_players u jednu
 * `products` tablicu. Zaliha nestaje — sve navedene veličine su uvijek
 * dostupne, a nedostupna se miče iz niza.
 *
 * Kao i baseline, protiv Supabasea se NIKAD ne pokreće: ondje je isti rez
 * odrađen ručno kroz docs/migration-01-single-product-table.sql, uz jsonb
 * i prave enum tipove. Ovdje su sizes/images JSON, a enumi stringovi, jer
 * sqlite nema ni jedno ni drugo.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Default je nužan: sqlite ne dopušta ADD COLUMN NOT NULL bez
            // njega na tablici koja već ima retke.
            $table->json('sizes')->default('[]');
            $table->json('images')->default('[]');
            $table->string('type')->default('adult');
            $table->dropColumn(['kit_type', 'audience', 'personalization']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->constrained();
            $table->string('size', 10)->nullable();
        });

        // Sqlite baza se gradi iz migracija za testove, pa je uvijek prazna —
        // nema podataka za prenijeti. Prava migracija podataka je u SQL-u.
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropConstrainedForeignId('product_player_id');
        });

        Schema::withoutForeignKeyConstraints(function () {
            Schema::dropIfExists('product_players');
            Schema::dropIfExists('product_variants');
            Schema::dropIfExists('product_images');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Nepovratna migracija: stare tablice se ne rekonstruiraju.');
    }
};

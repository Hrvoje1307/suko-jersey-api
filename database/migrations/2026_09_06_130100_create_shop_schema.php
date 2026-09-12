<?php

/*
 * Vjerna preslika `public` sheme koja već postoji u Supabaseu (snimljena kroz
 * `php artisan db:table --database=supabase`). Postgres enum tipovi su ovdje
 * stringovi jer sqlite nema enume; sve ostalo je 1:1, uključujući tablice koje
 * imaju samo `created_at`.
 *
 * Protiv Supabasea se NIKAD ne pokreće — ondje je označena kao već odrađena
 * (baseline). Gradi testnu bazu i služi kao zapis polazne sheme; svaka buduća
 * promjena ide kao nova migracija povrh nje.
 *
 * Ne preslikava: CHECK `chk_single_personalization` (Blueprint ga ne podržava,
 * pravilo provodi StoreOrderRequest) i perf indekse.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('club_or_team');
            $table->string('category');
            $table->string('season', 50)->nullable();
            $table->string('kit_type')->default('home');
            $table->string('audience')->default('unisex');
            $table->string('personalization')->default('none');
            $table->string('model_3d_url', 500)->nullable();
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('size', 10);
            $table->integer('stock_quantity')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'size']);
        });

        Schema::create('product_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('player_name');
            $table->string('player_number', 10)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->string('status')->default('ordered');
            $table->string('shipping_address_line1');
            $table->string('shipping_address_line2')->nullable();
            $table->string('shipping_city', 120);
            $table->string('shipping_postal_code', 20);
            $table->string('shipping_country', 2);
            $table->decimal('total_price', 10, 2);
            $table->string('tracking_number_internal', 100)->nullable();
            $table->string('order_reference', 20)->unique();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // U produkciji NOT NULL i bez ON DELETE — brisanje naručene varijante puca.
            $table->foreignId('product_variant_id')->constrained();
            $table->integer('quantity');
            $table->decimal('price_at_purchase', 10, 2);
            $table->foreignId('product_player_id')->nullable()->constrained();
            $table->string('custom_player_name')->nullable();
            $table->string('custom_player_number', 10)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            foreach (['order_items', 'orders', 'product_variants', 'product_images', 'product_players', 'products', 'customers', 'admin_users'] as $table) {
                Schema::dropIfExists($table);
            }
        });
    }
};

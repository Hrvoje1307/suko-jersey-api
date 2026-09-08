<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slika je ili uploadani fajl (`path`) ili vanjski URL (`external_url`).
     * Točno jedno od to dvoje je popunjeno.
     */
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('external_url', 2048)->nullable()->after('path');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('external_url');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('path')->nullable(false)->change();
        });
    }
};

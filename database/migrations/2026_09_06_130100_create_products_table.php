<?php

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
            $table->string('kit_type');
            $table->string('audience');
            $table->string('season')->nullable();
            $table->decimal('price', 8, 2);
            $table->text('description')->nullable();
            $table->string('model_3d_url')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index('club_or_team');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

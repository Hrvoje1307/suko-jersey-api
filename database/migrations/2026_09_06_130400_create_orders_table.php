<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_reference')->unique();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->string('shipping_line1');
            $table->string('shipping_line2')->nullable();
            $table->string('shipping_city');
            $table->string('shipping_postal_code');
            $table->string('shipping_country', 2);
            $table->string('status')->default('ordered');
            $table->decimal('total_price', 10, 2);
            $table->string('tracking_number_internal')->nullable();
            $table->timestamps();

            $table->index(['order_reference', 'customer_email']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

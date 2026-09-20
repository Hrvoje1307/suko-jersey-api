<?php

/*
 * Stripe Checkout — plaćanje kao zasebna os od `orders.status`.
 *
 * Kao i baseline (`create_shop_schema`), protiv Supabasea se NIKAD ne pokreće:
 * ondje su kolone dodane ručno kroz SQL Editor, a `payment_status` je pravi
 * Postgres enum tip. Ovdje je string jer sqlite nema enume — testna baza se
 * gradi iz migracija (vidi phpunit.xml).
 *
 * U Supabaseu se ova migracija označava odrađenom upisom retka u `migrations`.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid');
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'stripe_checkout_session_id',
                'stripe_payment_intent_id',
            ]);
        });
    }
};

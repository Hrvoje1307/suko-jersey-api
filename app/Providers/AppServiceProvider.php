<?php

namespace App\Providers;

use App\Services\Payments\CheckoutGateway;
use App\Services\Payments\StripeCheckoutGateway;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            StripeClient::class,
            fn () => new StripeClient((string) config('services.stripe.secret')),
        );

        // Testovi gateway zamjenjuju fakeom preko $this->app->instance(...).
        $this->app->bind(CheckoutGateway::class, StripeCheckoutGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

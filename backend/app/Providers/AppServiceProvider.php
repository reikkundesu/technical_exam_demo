<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\ShopifyGraphQLService::class, function ($app) {
            return new \App\Services\ShopifyGraphQLService(
                config('shopify.api_key'),
                config('shopify.api_secret')
            );
        });
    }

    public function boot(): void
    {
        //
    }
}

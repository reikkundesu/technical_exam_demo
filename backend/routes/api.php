<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WebhookProxyController;
use App\Http\Controllers\ShopifySyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal API (authenticated with X-Internal-Api-Key or api_key query)
|--------------------------------------------------------------------------
*/

Route::middleware(['internal.api.key'])->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    Route::post('/sync/products', [ShopifySyncController::class, 'syncProducts']);
    Route::post('/sync/orders', [ShopifySyncController::class, 'syncOrders']);

    Route::post('/webhooks/shopify', [WebhookProxyController::class, 'handle']);
});

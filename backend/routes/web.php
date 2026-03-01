<?php

use App\Http\Controllers\ShopifyOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/shopify/install', [ShopifyOAuthController::class, 'install'])->name('shopify.install');
Route::get('/shopify/callback', [ShopifyOAuthController::class, 'callback'])->name('shopify.callback');
Route::get('/shopify/installed', [ShopifyOAuthController::class, 'installed'])->name('shopify.installed');

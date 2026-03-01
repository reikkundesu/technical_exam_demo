<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncProductsJob;
use App\Jobs\SyncOrdersJob;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ShopifySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_async_products_dispatches_job()
    {
        $shop = Shop::factory()->create([
            'shop_domain' => 'example.myshopify.com',
            'access_token' => 'token',
        ]);

        Queue::fake();

        $response = $this->postJson("/api/sync/products?async=1&shop_id={$shop->id}", [], [
            'X-Internal-Api-Key' => config('app.internal_api_key'),
        ]);

        $response->assertStatus(202)
                 ->assertJson(['message' => 'Products sync queued']);

        Queue::assertPushed(SyncProductsJob::class, function ($job) use ($shop) {
            return $job->shopId === $shop->id;
        });
    }

    public function test_async_orders_dispatches_job()
    {
        $shop = Shop::factory()->create([
            'shop_domain' => 'example.myshopify.com',
            'access_token' => 'token',
        ]);

        Queue::fake();

        $response = $this->postJson("/api/sync/orders?async=1&shop_id={$shop->id}", [], [
            'X-Internal-Api-Key' => config('app.internal_api_key'),
        ]);

        $response->assertStatus(202)
                 ->assertJson(['message' => 'Orders sync queued']);

        Queue::assertPushed(SyncOrdersJob::class, function ($job) use ($shop) {
            return $job->shopId === $shop->id;
        });
    }
}

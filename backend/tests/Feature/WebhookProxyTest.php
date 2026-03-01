<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\Product;
use App\Models\Order;
use App\Models\ShopifyWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function postWebhook(array $data, string $topic, Shop $shop)
    {
        return $this->postJson('/api/webhooks/shopify', $data, [
            'X-Internal-Api-Key' => config('app.internal_api_key'),
            'X-Shopify-Topic' => $topic,
            'X-Shopify-Shop-Domain' => $shop->shop_domain,
        ]);
    }

    public function test_product_create_updates_database_and_records_event()
    {
        $shop = Shop::factory()->create();
        $payload = [
            'id' => 123,
            'title' => 'Test Product',
            'variants' => [],
            'images' => [],
        ];

        $response = $this->postWebhook($payload, 'products/create', $shop);
        $response->assertStatus(200)->assertJson(['accepted' => true]);

        $this->assertDatabaseHas('products', [
            'shop_id' => $shop->id,
            'shopify_product_id' => 123,
            'title' => 'Test Product',
        ]);

        $this->assertDatabaseHas('shopify_webhook_events', [
            'shop_id' => $shop->id,
            'topic' => 'products/create',
        ]);
        $this->assertNotNull(ShopifyWebhookEvent::first()->processed_at);
    }

    public function test_order_update_and_event()
    {
        $shop = Shop::factory()->create();
        $payload = [
            'id' => 456,
            'order_number' => '1001',
            'total_price' => '9.99',
        ];

        $response = $this->postWebhook($payload, 'orders/updated', $shop);
        $response->assertStatus(200)->assertJson(['accepted' => true]);

        $this->assertDatabaseHas('orders', [
            'shop_id' => $shop->id,
            'shopify_order_id' => 456,
            'order_number' => '1001',
        ]);

        $this->assertDatabaseHas('shopify_webhook_events', [
            'shop_id' => $shop->id,
            'topic' => 'orders/updated',
        ]);
    }
}

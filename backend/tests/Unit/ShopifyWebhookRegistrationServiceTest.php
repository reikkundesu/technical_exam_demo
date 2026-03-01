<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ShopifyWebhookRegistrationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyWebhookRegistrationServiceTest extends TestCase
{
    public function test_register_webhooks_creates_and_returns_ids()
    {
        $service = new ShopifyWebhookRegistrationService();

        $shopDomain = 'test-shop.myshopify.com';
        $accessToken = 'token123';
        $topics = ['products/create', 'orders/create'];
        $destination = 'https://example.com/webhooks';
        $apiVersion = '2023-10';

        // simulate successful creation responses
        Http::fakeSequence()
            ->push(Http::response([
                'webhook' => ['id' => 111],
            ], 201))
            ->push(Http::response([
                'webhooks' => [
                    ['id' => 111, 'topic' => 'products/create', 'address' => $destination],
                    ['id' => 222, 'topic' => 'orders/create', 'address' => $destination],
                ],
            ], 200));

        $result = $service->registerWebhooks($shopDomain, $accessToken, $topics, $destination, $apiVersion);

        $this->assertEquals(["products/create" => 111, "orders/create" => 222], $result);

        Http::assertSentCount(2);
    }
}

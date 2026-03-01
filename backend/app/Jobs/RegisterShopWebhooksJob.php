<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Models\ShopWebhook;
use App\Services\ShopifyWebhookRegistrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RegisterShopWebhooksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $shopId;

    /**
     * @var string[]
     */
    public array $topics;

    public function __construct(int $shopId, array $topics)
    {
        $this->shopId = $shopId;
        $this->topics = $topics;
    }

    public function handle(ShopifyWebhookRegistrationService $service)
    {
        $shop = Shop::findOrFail($this->shopId);

        $destination = rtrim(config('app.webhook_url') ?? config('app.url'), '/') . '/webhooks/shopify';
        // fallback to WEBHOOKS_SERVICE_URL env if set
        if (empty($destination) && env('WEBHOOKS_SERVICE_URL')) {
            $destination = rtrim(env('WEBHOOKS_SERVICE_URL'), '/') . '/webhooks/shopify';
        }

        $results = $service->registerWebhooks($shop->shop_domain, $shop->access_token, $this->topics, $destination);

        foreach ($results as $topic => $id) {
            ShopWebhook::updateOrCreate(
                ['shop_id' => $shop->id, 'topic' => $topic],
                ['webhook_id' => $id]
            );
        }
    }
}

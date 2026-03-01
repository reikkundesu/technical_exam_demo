<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Models\Order;
use App\Services\ShopifyGraphQLService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $shopId;
    public ?string $cursor;
    public ?string $since;

    public function __construct(int $shopId, ?string $cursor = null, ?string $since = null)
    {
        $this->shopId = $shopId;
        $this->cursor = $cursor;
        $this->since = $since;
    }

    public function handle(ShopifyGraphQLService $shopify)
    {
        $shop = Shop::findOrFail($this->shopId);
        $accessToken = $shop->access_token;
        if (! $accessToken) {
            return;
        }

        $result = $shopify->getOrders($shop->shop_domain, $accessToken, $this->cursor, $this->since);
        $orders = $result['orders'] ?? [];
        $edges = $orders['edges'] ?? [];
        $pageInfo = $orders['pageInfo'] ?? [];

        foreach ($edges as $edge) {
            $node = $edge['node'] ?? [];
            $shopifyId = (int) ($node['legacyResourceId'] ?? 0);
            if (! $shopifyId) {
                continue;
            }

            $shopMoney = $node['totalPriceSet']['shopMoney'] ?? [];
            $totalPrice = isset($shopMoney['amount']) ? (float) $shopMoney['amount'] : null;

            Order::updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'shopify_order_id' => $shopifyId,
                ],
                [
                    'order_number' => $node['name'] ?? null,
                    'financial_status' => $node['displayFinancialStatus'] ?? null,
                    'fulfillment_status' => $node['displayFulfillmentStatus'] ?? null,
                    'total_price' => $totalPrice,
                    'shopify_created_at' => $node['processedAt'] ?? null,
                ]
            );
        }

        if (! empty($pageInfo['hasNextPage']) && ! empty($pageInfo['endCursor'])) {
            self::dispatch($shop->id, $pageInfo['endCursor'], $this->since);
        }
    }
}

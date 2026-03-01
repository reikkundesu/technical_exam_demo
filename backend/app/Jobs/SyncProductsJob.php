<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Models\Product;
use App\Services\ShopifyGraphQLService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $shopId;
    public ?string $cursor;

    public function __construct(int $shopId, ?string $cursor = null)
    {
        $this->shopId = $shopId;
        $this->cursor = $cursor;
    }

    public function handle(ShopifyGraphQLService $shopify)
    {
        $shop = Shop::findOrFail($this->shopId);
        $accessToken = $shop->access_token;
        if (! $accessToken) {
            return;
        }

        $result = $shopify->getProducts($shop->shop_domain, $accessToken, $this->cursor);
        $products = $result['products'] ?? [];
        $edges = $products['edges'] ?? [];
        $pageInfo = $products['pageInfo'] ?? [];

        $synced = 0;
        foreach ($edges as $edge) {
            $node = $edge['node'] ?? [];
            $shopifyId = (int) ($node['legacyResourceId'] ?? 0);
            if (! $shopifyId) {
                continue;
            }

            $variants = [];
            foreach ($node['variants']['edges'] ?? [] as $v) {
                $variants[] = $v['node'] ?? [];
            }
            Product::updateOrCreate(
                [
                    'shop_id' => $shop->id,
                    'shopify_product_id' => $shopifyId,
                ],
                [
                    'title' => $node['title'] ?? '',
                    'vendor' => $node['vendor'] ?? '',
                    'status' => strtolower((string) ($node['status'] ?? 'active')),
                    'price' => $variants[0]['price'] ?? null,
                    'shopify_updated_at' => $node['updatedAt'] ?? null,
                ]
            );
            $synced++;
        }

        // dispatch next page if needed
        if (! empty($pageInfo['hasNextPage']) && ! empty($pageInfo['endCursor'])) {
            self::dispatch($shop->id, $pageInfo['endCursor']);
        }
    }
}

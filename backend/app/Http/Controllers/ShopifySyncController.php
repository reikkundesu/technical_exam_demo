<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Jobs\SyncProductsJob;
use App\Jobs\SyncOrdersJob;
use App\Services\ShopifyGraphQLService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopifySyncController extends Controller
{
    public function __construct(
        protected ShopifyGraphQLService $shopify
    ) {}

    /**
     * Sync all products from Shopify for a shop (by shop_id or shop domain).
     */
    public function syncProducts(Request $request): JsonResponse
    {
        $request->validate([
            'cursor' => 'nullable|string',
            'async' => 'sometimes|boolean',
        ]);

        $shop = $this->resolveShop($request);
        $accessToken = $shop->access_token;
        if (! $accessToken) {
            return response()->json(['error' => 'Shop has no access token'], 400);
        }

        // if async flag is present we dispatch a queued job instead of doing the work inline
        if ($request->boolean('async')) {
            $dispatchCursor = $request->input('cursor');
            if ($dispatchCursor === '') {
                $dispatchCursor = null;
            }

            SyncProductsJob::dispatch(
                $shop->id,
                $dispatchCursor
            );

            return response()->json([
                'message' => 'Products sync queued',
                'shop_id' => $shop->id,
            ], 202);
        }

        // synchronous sync (legacy behaviour), still supports optional cursor parameter
        // pull the raw input and coerce empty string to null; the Stringable instance
        // returned by $request->string() does not support ->nullable() which caused
        // a BadMethodCallException during testing.
        $cursor = $request->input('cursor');
        if ($cursor === '') {
            $cursor = null;
        }
        $synced = 0;

        do {
            $result = $this->shopify->getProducts($shop->shop_domain, $accessToken, $cursor);
            $products = $result['products'] ?? [];
            $edges = $products['edges'] ?? [];
            $pageInfo = $products['pageInfo'] ?? [];

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

            $cursor = $pageInfo['endCursor'] ?? null;
        } while (! empty($pageInfo['hasNextPage']) && $cursor);

        return response()->json([
            'message' => 'Products synced',
            'synced' => $synced,
            'shop_id' => $shop->id,
        ]);
    }

    /**
     * Sync all orders from Shopify for a shop.
     */
    public function syncOrders(Request $request): JsonResponse
    {
        $request->validate([
            'cursor' => 'nullable|string',
            'since' => 'nullable|date_format:Y-m-d',
            'async' => 'sometimes|boolean',
        ]);

        $shop = $this->resolveShop($request);
        $accessToken = $shop->access_token;
        if (! $accessToken) {
            return response()->json(['error' => 'Shop has no access token'], 400);
        }

        $since = $request->input('since');

        if ($request->boolean('async')) {
            $dispatchCursor = $request->input('cursor');
            if ($dispatchCursor === '') {
                $dispatchCursor = null;
            }

            SyncOrdersJob::dispatch(
                $shop->id,
                $dispatchCursor,
                $since
            );

            return response()->json([
                'message' => 'Orders sync queued',
                'shop_id' => $shop->id,
            ], 202);
        }

        $cursor = $request->input('cursor');
        if ($cursor === '') {
            $cursor = null;
        }
        $synced = 0;

        do {
            $result = $this->shopify->getOrders($shop->shop_domain, $accessToken, $cursor, $since);
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
                $synced++;
            }

            $cursor = $pageInfo['endCursor'] ?? null;
        } while (! empty($pageInfo['hasNextPage']) && $cursor);

        return response()->json([
            'message' => 'Orders synced',
            'synced' => $synced,
            'shop_id' => $shop->id,
        ]);
    }

    protected function resolveShop(Request $request): Shop
    {
        $shopId = $request->query('shop_id') ?? $request->input('shop_id');
        $shopDomain = $request->query('shop') ?? $request->input('shop');

        if ($shopId) {
            $shop = Shop::find($shopId);
        } elseif ($shopDomain) {
            $shop = Shop::where('shop_domain', $shopDomain)->first();
        } else {
            $shop = Shop::first();
        }

        if (! $shop) {
            abort(404, 'Shop not found. Provide shop_id or shop domain.');
        }

        return $shop;
    }
}

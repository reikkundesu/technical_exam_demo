<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives webhook payloads from the Node.js webhooks service (after HMAC verification).
 * All requests must include X-Internal-Api-Key for authentication.
 */
class WebhookProxyController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $topic = $request->header('X-Shopify-Topic');
        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        $webhookId = $request->header('X-Shopify-Webhook-Id');
        $payload = $request->all();

        if (! $shopDomain) {
            return response()->json(['error' => 'Missing X-Shopify-Shop-Domain'], 400);
        }

        $shop = Shop::where('shop_domain', $shopDomain)->first();
        if (! $shop) {
            Log::warning("Webhook for unknown shop: {$shopDomain}");
            return response()->json(['accepted' => true]);
        }

        if ($webhookId) {
            $alreadyHandled = \App\Models\ShopifyWebhookEvent::query()
                ->where('shop_id', $shop->id)
                ->where('webhook_id', $webhookId)
                ->exists();

            if ($alreadyHandled) {
                return response()->json(['accepted' => true, 'duplicate' => true]);
            }
        }

        // persist incoming payload for auditing / retry
        $event = \App\Models\ShopifyWebhookEvent::create([
            'shop_id' => $shop->id,
            'topic' => $topic,
            'shop_domain' => $shopDomain,
            'webhook_id' => $webhookId,
            'payload' => $payload,
            'received_at' => now(),
            'processing_status' => 'received',
        ]);

        try {
            match ($topic) {
                'products/create', 'products/update' => $this->upsertProduct($shop, $payload),
                'products/delete' => $this->deleteProduct($shop, $payload),
                'orders/create', 'orders/updated' => $this->upsertOrder($shop, $payload),
                'orders/cancelled' => $this->cancelOrder($shop, $payload),
                default => null,
            };

            $event->processed_at = now();
            $event->processing_status = 'processed';
            $event->save();
        } catch (\Throwable $e) {
            Log::error("Webhook processing failed: {$topic}", ['error' => $e->getMessage()]);
            $event->error = $e->getMessage();
            $event->processing_status = 'failed';
            $event->save();
            return response()->json(['error' => 'Processing failed'], 500);
        }

        return response()->json(['accepted' => true]);
    }

    protected function upsertProduct(Shop $shop, array $payload): void
    {
        $id = (int) ($payload['id'] ?? 0);
        if (! $id) {
            return;
        }

        $variants = $payload['variants'] ?? [];
        $firstVariant = $variants[0] ?? [];

        Product::updateOrCreate(
            [
                'shop_id' => $shop->id,
                'shopify_product_id' => $id,
            ],
            [
                'title' => $payload['title'] ?? '',
                'vendor' => $payload['vendor'] ?? '',
                'status' => $payload['status'] ?? 'active',
                'price' => $firstVariant['price'] ?? null,
                'shopify_updated_at' => $payload['updated_at'] ?? null,
            ]
        );
    }

    protected function deleteProduct(Shop $shop, array $payload): void
    {
        $id = (int) ($payload['id'] ?? 0);
        if ($id) {
            Product::where('shop_id', $shop->id)->where('shopify_product_id', $id)->delete();
        }
    }

    protected function upsertOrder(Shop $shop, array $payload): void
    {
        $id = (int) ($payload['id'] ?? 0);
        if (! $id) {
            return;
        }

        Order::updateOrCreate(
            [
                'shop_id' => $shop->id,
                'shopify_order_id' => $id,
            ],
            [
                'order_number' => $payload['order_number'] ?? $payload['name'] ?? null,
                'financial_status' => $payload['financial_status'] ?? null,
                'fulfillment_status' => $payload['fulfillment_status'] ?? null,
                'total_price' => isset($payload['total_price']) ? (float) $payload['total_price'] : null,
                'shopify_created_at' => $payload['created_at'] ?? null,
            ]
        );
    }

    protected function cancelOrder(Shop $shop, array $payload): void
    {
        $id = (int) ($payload['id'] ?? 0);
        if ($id) {
            Order::where('shop_id', $shop->id)->where('shopify_order_id', $id)->update([
                'fulfillment_status' => 'cancelled',
            ]);
        }
    }
}

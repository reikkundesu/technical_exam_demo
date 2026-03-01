<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * List orders (optionally filtered by shop_id or shop domain).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_id' => 'sometimes|integer|exists:shops,id',
            'shop' => 'sometimes|string|exists:shops,shop_domain',
            'financial_status' => 'sometimes|string|max:255',
            'fulfillment_status' => 'sometimes|string|max:255',
            'created_from' => 'sometimes|date',
            'created_to' => 'sometimes|date',
            'search' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Order::query()->with('shop:id,shop_domain');
        if (isset($validated['shop_id'])) {
            $query->where('shop_id', $validated['shop_id']);
        }
        if (isset($validated['shop'])) {
            $shop = Shop::where('shop_domain', $validated['shop'])->first();
            if ($shop) {
                $query->where('shop_id', $shop->id);
            }
        }
        if (isset($validated['financial_status'])) {
            $query->where('financial_status', $validated['financial_status']);
        }
        if (isset($validated['fulfillment_status'])) {
            $query->where('fulfillment_status', $validated['fulfillment_status']);
        }
        if (isset($validated['created_from'])) {
            $query->where('shopify_created_at', '>=', $validated['created_from']);
        }
        if (isset($validated['created_to'])) {
            $query->where('shopify_created_at', '<=', $validated['created_to']);
        }
        if (isset($validated['search'])) {
            $query->where(function($q) use ($validated) {
                $q->where('order_number', 'like', '%'.$validated['search'].'%');
            });
        }
        $perPage = $validated['per_page'] ?? 15;
        $orders = $query->orderByDesc('id')->paginate($perPage);
        return response()->json($orders);
    }

    /**
     * Show a single order.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load('shop:id,shop_domain');

        return response()->json($order);
    }
}

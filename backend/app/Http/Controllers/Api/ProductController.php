<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * List products (optionally filtered by shop_id or shop domain).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_id' => 'sometimes|integer|exists:shops,id',
            'shop' => 'sometimes|string|exists:shops,shop_domain',
            'vendor' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|in:active,archived,draft',
            'updated_from' => 'sometimes|date',
            'updated_to' => 'sometimes|date',
            'search' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Product::query()->with('shop:id,shop_domain');
        if (isset($validated['shop_id'])) {
            $query->where('shop_id', $validated['shop_id']);
        }
        if (isset($validated['shop'])) {
            $shop = Shop::where('shop_domain', $validated['shop'])->first();
            if ($shop) {
                $query->where('shop_id', $shop->id);
            }
        }
        if (isset($validated['vendor'])) {
            $query->where('vendor', $validated['vendor']);
        }
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (isset($validated['updated_from'])) {
            $query->where('shopify_updated_at', '>=', $validated['updated_from']);
        }
        if (isset($validated['updated_to'])) {
            $query->where('shopify_updated_at', '<=', $validated['updated_to']);
        }
        if (isset($validated['search'])) {
            $query->where(function($q) use ($validated) {
                $q->where('title', 'like', '%'.$validated['search'].'%')
                  ->orWhere('vendor', 'like', '%'.$validated['search'].'%');
            });
        }
        $perPage = $validated['per_page'] ?? 15;
        $products = $query->orderBy('id')->paginate($perPage);
        return response()->json($products);
    }

    /**
     * Show a single product.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load('shop:id,shop_domain');

        return response()->json($product);
    }
}

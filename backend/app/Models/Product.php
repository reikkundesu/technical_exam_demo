<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'shop_id',
        'shopify_product_id',
        'title',
        'vendor',
        'status',
        'price',
        'shopify_updated_at',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'shopify_product_id' => 'integer',
        'price' => 'decimal:2',
        'shopify_updated_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}

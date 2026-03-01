<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'shop_id',
        'shopify_order_id',
        'order_number',
        'financial_status',
        'fulfillment_status',
        'total_price',
        'shopify_created_at',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'shopify_order_id' => 'integer',
        'total_price' => 'decimal:2',
        'shopify_created_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}

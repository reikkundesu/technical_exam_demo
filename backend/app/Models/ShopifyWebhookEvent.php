<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyWebhookEvent extends Model
{
    protected $fillable = ['shop_id', 'topic', 'shop_domain', 'webhook_id', 'payload', 'received_at', 'processing_status', 'processed_at', 'error'];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}

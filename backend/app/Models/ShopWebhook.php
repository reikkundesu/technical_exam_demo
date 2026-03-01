<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopWebhook extends Model
{
    protected $fillable = ['shop_id', 'topic', 'webhook_id'];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}

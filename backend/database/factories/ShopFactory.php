<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShopFactory extends Factory
{
    protected $model = Shop::class;

    public function definition()
    {
        return [
            'shop_domain' => $this->faker->domainName . '.myshopify.com',
            'access_token' => 'token',
            'scope' => 'read_products,read_orders',
        ];
    }
}

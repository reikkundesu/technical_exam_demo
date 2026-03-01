<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('shopify_order_id')->index();
            $table->string('order_number')->nullable();
            $table->string('financial_status')->nullable();
            $table->string('fulfillment_status')->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('email')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('line_items')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'shopify_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

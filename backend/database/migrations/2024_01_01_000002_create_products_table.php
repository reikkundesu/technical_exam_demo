<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('shopify_product_id')->index();
            $table->string('title');
            $table->text('body_html')->nullable();
            $table->string('handle')->nullable();
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            $table->string('status')->default('active');
            $table->json('variants')->nullable();
            $table->json('images')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'shopify_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

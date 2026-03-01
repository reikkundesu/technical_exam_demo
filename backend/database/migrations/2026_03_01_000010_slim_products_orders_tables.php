<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'body_html', 'handle', 'product_type', 'variants', 'images', 'raw', 'created_at', 'updated_at'
            ]);
            // Add price and shopify_updated_at if not present
            if (!Schema::hasColumn('products', 'price')) {
                $table->decimal('price', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('products', 'shopify_updated_at')) {
                $table->timestamp('shopify_updated_at')->nullable();
            }
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'currency', 'email', 'line_items', 'raw', 'created_at', 'updated_at'
            ]);
            // Add shopify_created_at if not present
            if (!Schema::hasColumn('orders', 'shopify_created_at')) {
                $table->timestamp('shopify_created_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('body_html')->nullable();
            $table->string('handle')->nullable();
            $table->string('product_type')->nullable();
            $table->json('variants')->nullable();
            $table->json('images')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
            $table->dropColumn(['price', 'shopify_updated_at']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('currency', 3)->nullable();
            $table->string('email')->nullable();
            $table->json('line_items')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
            $table->dropColumn(['shopify_created_at']);
        });
    }
};

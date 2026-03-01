<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_webhook_events', function (Blueprint $table) {
            if (! Schema::hasColumn('shopify_webhook_events', 'webhook_id')) {
                $table->string('webhook_id')->nullable()->after('shop_domain');
            }

            $table->unique(['shop_id', 'webhook_id'], 'shopify_webhook_events_shop_webhook_unique');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_webhook_events', function (Blueprint $table) {
            $table->dropUnique('shopify_webhook_events_shop_webhook_unique');

            if (Schema::hasColumn('shopify_webhook_events', 'webhook_id')) {
                $table->dropColumn('webhook_id');
            }
        });
    }
};

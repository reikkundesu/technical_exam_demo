<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_webhook_events', function (Blueprint $table) {
            if (! Schema::hasColumn('shopify_webhook_events', 'shop_domain')) {
                $table->string('shop_domain')->nullable()->after('topic');
            }

            if (! Schema::hasColumn('shopify_webhook_events', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('shop_domain');
            }

            if (! Schema::hasColumn('shopify_webhook_events', 'processing_status')) {
                $table->string('processing_status')->default('received')->after('received_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shopify_webhook_events', function (Blueprint $table) {
            $drop = [];

            if (Schema::hasColumn('shopify_webhook_events', 'processing_status')) {
                $drop[] = 'processing_status';
            }

            if (Schema::hasColumn('shopify_webhook_events', 'received_at')) {
                $drop[] = 'received_at';
            }

            if (Schema::hasColumn('shopify_webhook_events', 'shop_domain')) {
                $drop[] = 'shop_domain';
            }

            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};

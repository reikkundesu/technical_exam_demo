<?php

return [
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    'scopes' => env('SHOPIFY_SCOPES'),
    'api_version' => env('SHOPIFY_API_VERSION'),
    'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
    'skip_ssl_verify' => (bool) env('SHOPIFY_SKIP_SSL_VERIFY', false),
    // list of webhook topics that should be registered for each shop
    'webhook_topics' => [
        'products/create',
        'products/update',
        'products/delete',
        'orders/create',
        'orders/updated',
        'orders/cancelled',
    ],
    // optional explicit webhook service URL; falls back to app.url
    'webhook_url' => env('WEBHOOKS_SERVICE_URL'),
];

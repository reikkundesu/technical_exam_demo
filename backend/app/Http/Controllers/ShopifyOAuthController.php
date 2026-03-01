<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Jobs\RegisterShopWebhooksJob;

class ShopifyOAuthController extends Controller
{
    /**
     * Shopify shop domain must be: subdomain.myshopify.com (subdomain: alphanumeric and hyphens only).
     */
    private const SHOP_DOMAIN_REGEX = '/^[a-zA-Z0-9][a-zA-Z0-9-]*\.myshopify\.com$/';

    /**
     * GET /shopify/install
     * Accepts shop domain as parameter and redirects user to Shopify authorization screen.
     */
    public function install(Request $request): RedirectResponse
    {
        $request->validate(['shop' => ['required', 'string', 'max:255']]);

        $shop = $this->normalizeAndValidateShopDomain($request->input('shop'));

        $state = Str::random(40);
        session(['shopify_oauth_state' => $state, 'shopify_shop' => $shop]);

        $appUrl = rtrim(config('app.url') ?? '', '/');
        $redirectUri = $appUrl !== '' ? $appUrl . '/shopify/callback' : route('shopify.callback');

        $params = http_build_query([
            'client_id' => config('shopify.api_key'),
            'scope' => config('shopify.scopes'),
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return redirect("https://{$shop}/admin/oauth/authorize?{$params}");
    }

    /**
     * GET /shopify/callback
     * Validates shop, verifies HMAC, validates state (CSRF), exchanges code for token, stores token securely.
     */
    public function callback(Request $request): RedirectResponse
    {
        $hmac = $request->query('hmac');
        $shop = $request->query('shop');
        $code = $request->query('code');
        $state = $request->query('state');

        if (empty($hmac) || empty($shop) || empty($code) || empty($state)) {
            abort(400, 'Missing required parameters.');
        }

        if (! $this->verifyCallbackHmac($request->query(), $hmac)) {
            abort(401, 'Invalid request signature.');
        }

        $shop = $this->normalizeAndValidateShopDomain($shop);

        $sessionState = session('shopify_oauth_state');
        if ($sessionState === null || ! hash_equals((string) $sessionState, (string) $state)) {
            abort(403, 'Invalid state parameter.');
        }

        if (session('shopify_shop') !== $shop) {
            abort(400, 'Shop does not match session.');
        }

        $httpClient = Http::when(
            config('shopify.skip_ssl_verify'),
            fn($http) => $http->withoutVerifying()
        );

        $response = $httpClient->post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => config('shopify.api_key'),
            'client_secret' => config('shopify.api_secret'),
            'code' => $code,
        ]);

        if (! $response->successful()) {
            abort(502, 'Token exchange failed.');
        }

        $data = $response->json();
        $accessToken = $data['access_token'] ?? null;
        $scope = $data['scope'] ?? null;

        if (empty($accessToken)) {
            abort(502, 'Token exchange failed.');
        }

        $shopModel = Shop::updateOrCreate(
            ['shop_domain' => $shop],
            [
                'access_token' => $accessToken,
                'scope' => $scope,
            ]
        );

        // enqueue webhook registration using configured topics
        $topics = config('shopify.webhook_topics', []);
        if (!empty($topics)) {
            RegisterShopWebhooksJob::dispatch($shopModel->id, $topics);
        }

        // Automatically dispatch initial sync jobs for products and orders
        \App\Jobs\SyncProductsJob::dispatch($shopModel->id);
        \App\Jobs\SyncOrdersJob::dispatch($shopModel->id);

        session()->forget(['shopify_oauth_state', 'shopify_shop']);

        return redirect()->route('shopify.installed')->with('success', 'Store connected successfully.');
    }

    /**
     * Simple success page after install.
     */
    public function installed()
    {
        return response()->json([
            'message' => 'Shop connected. Use the internal API to sync products and orders.',
            'endpoints' => [
                'sync_products' => 'POST /api/sync/products',
                'sync_orders' => 'POST /api/sync/orders',
                'products' => 'GET /api/products',
                'orders' => 'GET /api/orders',
            ],
        ]);
    }

    /**
     * Normalize to subdomain.myshopify.com and validate format strictly.
     */
    private function normalizeAndValidateShopDomain(string $input): string
    {
        $input = strtolower(trim($input));
        $input = Str::replace('.myshopify.com', '', $input);
        $input = preg_replace('/[^a-z0-9-]/', '', $input);
        $input = trim($input, '-');
        if ($input === '' || strlen($input) > 63) {
            abort(400, 'Invalid shop domain.');
        }
        $shop = $input . '.myshopify.com';

        if (! preg_match(self::SHOP_DOMAIN_REGEX, $shop)) {
            abort(400, 'Invalid shop domain format.');
        }

        return $shop;
    }

    /**
     * Verify Shopify OAuth callback HMAC (request authenticity).
     * Params (excluding hmac) sorted alphabetically, joined as key=value&key2=value2; HMAC-SHA256 with API secret, hex.
     */
    private function verifyCallbackHmac(array $query, string $receivedHmac): bool
    {
        $secret = config('shopify.api_secret');
        if (empty($secret)) {
            return false;
        }

        unset($query['hmac']);
        ksort($query);
        $message = http_build_query($query, '', '&');

        $expected = hash_hmac('sha256', $message, $secret);

        return hash_equals($expected, $receivedHmac);
    }
}

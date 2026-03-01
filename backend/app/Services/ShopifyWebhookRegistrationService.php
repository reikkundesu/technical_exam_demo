<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

class ShopifyWebhookRegistrationService
{
    /**
     * Register a set of webhook topics for a given shop using its access token.
     * Returns array of topic => webhook_id results.
     *
     * @param string $shopDomain
     * @param string $accessToken
     * @param array $topics
     * @param string $destinationUrl
     * @param string $apiVersion
     * @return array<string,string|null>
     * @throws \RuntimeException
     */
    public function registerWebhooks(string $shopDomain, string $accessToken, array $topics, string $destinationUrl, string $apiVersion = null): array
    {
        $apiVersion = $apiVersion ?: config('shopify.api_version');
        if (empty($apiVersion)) {
            throw new \InvalidArgumentException('Shopify API version not configured');
        }

        $results = [];

        foreach ($topics as $topic) {
            // use REST endpoint to create or update a webhook
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://{$shopDomain}/admin/api/{$apiVersion}/webhooks.json", [
                'webhook' => [
                    'topic' => $topic,
                    'address' => $destinationUrl,
                    'format' => 'json',
                ],
            ]);

            if (! $response->successful() && $response->status() !== 422) {
                // 422 may indicate already exists; we'll fetch existing
                throw new \RuntimeException("Failed to register webhook {$topic}: " . $response->body());
            }

            $data = $response->json();
            $id = Arr::get($data, 'webhook.id');

            // if 422 (already exists) we need to attempt find existing
            if (!$id && $response->status() === 422) {
                // list webhooks and match by topic/address
                $list = Http::withHeaders([
                    'X-Shopify-Access-Token' => $accessToken,
                ])->get("https://{$shopDomain}/admin/api/{$apiVersion}/webhooks.json");
                if ($list->successful()) {
                    foreach (Arr::get($list->json(), 'webhooks', []) as $wh) {
                        if (Arr::get($wh, 'topic') === $topic && Arr::get($wh, 'address') === $destinationUrl) {
                            $id = Arr::get($wh, 'id');
                            break;
                        }
                    }
                }
            }

            $results[$topic] = $id;
        }

        return $results;
    }
}

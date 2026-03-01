<?php

namespace App\Services;

use App\Exceptions\ShopifyApiException;
use Illuminate\Support\Facades\Http;

class ShopifyGraphQLService
{
    public function __construct(
        protected ?string $apiKey,
        protected ?string $apiSecret
    ) {}

    /**
     * Execute a GraphQL query against Shopify Admin API for a given shop.
     */
    public function graphql(string $shopDomain, string $accessToken, string $query, array $variables = []): array
    {
        $version = config('shopify.api_version');
        if (empty($version)) {
            throw new \InvalidArgumentException('SHOPIFY_API_VERSION is not set in configuration.');
        }
        $url = "https://{$shopDomain}/admin/api/{$version}/graphql.json";

        $attempts = 0;
        $maxAttempts = 5;
        $delayMs = 200;

        do {
            $attempts++;

            $client = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $accessToken,
            ]);

            // Allow disabling SSL verification for local development via env.
            // Set SHOPIFY_SKIP_SSL_VERIFY=1 to skip verification (not for production).
            $skipVerify = filter_var(env('SHOPIFY_SKIP_SSL_VERIFY', false), FILTER_VALIDATE_BOOLEAN);
            if ($skipVerify) {
                $client = $client->withOptions(['verify' => false]);
            }

            $response = $client->post($url, [
                'query' => $query,
                'variables' => $variables,
            ]);

            // handle throttling
            if ($response->status() === 429) {
                $retryAfter = $response->header('Retry-After');
                $wait = $retryAfter ? ((int) $retryAfter) * 1000 : $delayMs;
                usleep($wait * 1000);
                $delayMs *= 2;
                continue;
            }

            // retry on server error
            if ($response->serverError() && $attempts < $maxAttempts) {
                usleep($delayMs * 1000);
                $delayMs *= 2;
                continue;
            }

            // for other errors allow throwing
            $response->throw();

            $data = $response->json();
            if (isset($data['errors'])) {
                throw new ShopifyApiException('Shopify GraphQL request failed.');
            }

            return $data['data'] ?? [];
        } while ($attempts < $maxAttempts);

        throw new ShopifyApiException('GraphQL request failed after ' . $maxAttempts . ' attempts');
    }

    /**
     * Fetch products with cursor-based pagination.
     */
    public function getProducts(string $shopDomain, string $accessToken, ?string $cursor = null): array
    {
        $query = <<<'GRAPHQL'
        query GetProducts($first: Int!, $after: String) {
            products(first: $first, after: $after, query: "status:active") {
                pageInfo {
                    hasNextPage
                    endCursor
                }
                edges {
                    node {
                        id
                        legacyResourceId
                        title
                        updatedAt
                        bodyHtml
                        handle
                        vendor
                        productType
                        status
                        variants(first: 50) {
                            edges {
                                node {
                                    id
                                    legacyResourceId
                                    title
                                    price
                                    sku
                                }
                            }
                        }
                        images(first: 10) {
                            edges {
                                node {
                                    url
                                    altText
                                }
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $variables = [
            'first' => 50,
            'after' => $cursor,
        ];

        return $this->graphql($shopDomain, $accessToken, $query, $variables);
    }

    /**
     * Fetch orders with cursor-based pagination.
     */
    public function getOrders(string $shopDomain, string $accessToken, ?string $cursor = null, ?string $since = null): array
    {
        $query = <<<'GRAPHQL'
        query GetOrders($first: Int!, $after: String, $query: String) {
            orders(first: $first, after: $after, sortKey: CREATED_AT, reverse: true, query: $query) {
                pageInfo {
                    hasNextPage
                    endCursor
                }
                edges {
                    node {
                        id
                        legacyResourceId
                        name
                        email
                        totalPriceSet {
                            shopMoney {
                                amount
                                currencyCode
                            }
                        }
                        displayFinancialStatus
                        displayFulfillmentStatus
                        processedAt
                        lineItems(first: 50) {
                            edges {
                                node {
                                    title
                                    quantity
                                    originalUnitPriceSet {
                                        shopMoney {
                                            amount
                                            currencyCode
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $variables = [
            'first' => 50,
            'after' => $cursor,
            'query' => $since ? "created_at:>={$since}" : null,
        ];

        return $this->graphql($shopDomain, $accessToken, $query, $variables);
    }
}

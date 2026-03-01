# EXPLANATION

## Why GraphQL was used

Shopify GraphQL Admin API was used because it allows selecting only the fields needed for local persistence in a single query, reducing over-fetching and request count compared with REST endpoints. It also supports cursor-based pagination natively through `edges`, `pageInfo`, and `endCursor`, which maps directly to the exam requirement.

## How OAuth verification works

OAuth flow is handled in Laravel (`ShopifyOAuthController`):

1. `/shopify/install` validates and normalizes the shop domain.
2. A random `state` token is generated and stored in session.
3. User is redirected to Shopify authorize URL.
4. `/shopify/callback` verifies:
   - required callback params (`hmac`, `shop`, `code`, `state`)
   - HMAC signature using Shopify API secret
   - `state` matches session token (CSRF protection)
   - shop consistency against session value
5. Laravel exchanges authorization `code` for access token and stores it encrypted in `shops` table.

## How webhook verification works

Webhook verification happens in the Node service:

1. Express stores the raw request body (`req.rawBody`) before JSON parsing.
2. HMAC-SHA256 is computed from raw body using `SHOPIFY_API_SECRET`.
3. Computed base64 signature is compared using timing-safe comparison against `X-Shopify-Hmac-Sha256`.
4. On success, service returns HTTP 200 immediately, then forwards payload to Laravel internal webhook API.

Laravel then validates internal API key and persists webhook event + processing status.

## How pagination is implemented

### Shopify side
- Cursor-based pagination is used in GraphQL queries:
  - `first`
  - `after`
  - `pageInfo.hasNextPage`
  - `pageInfo.endCursor`

### Local API side
- Laravel uses paginator for local listing endpoints:
  - `/api/products`
  - `/api/orders`
- Supports `per_page` and page query params.

## How rate limiting is handled

In `ShopifyGraphQLService`:

1. Detects throttling on HTTP `429`.
2. Reads `Retry-After` header when present; otherwise uses exponential backoff delay.
3. Retries up to a bounded max attempt count (`maxAttempts = 5`).
4. Also retries transient server errors with backoff.

This prevents uncontrolled retry loops.

## What broke during development and how it was fixed

Main issues encountered:

1. **OAuth callback / route issues and local tunnel mismatch**
   - Fixed by aligning `APP_URL`, ngrok callback URL, and Shopify app redirect URL.

2. **SSL certificate errors (`cURL error 60`) in local environment**
   - Added env-based `SHOPIFY_SKIP_SSL_VERIFY` handling in HTTP client for local development.

3. **Database schema mismatch after slimming tables**
   - Sync jobs/controllers still wrote old columns (`body_html`, `raw`, etc.).
   - Fixed by aligning all upsert mappings to current slim schema fields.

4. **Order GraphQL field changes**
   - `financialStatus`/`fulfillmentStatus` were invalid for current API schema.
   - Replaced with `displayFinancialStatus`/`displayFulfillmentStatus`.

5. **Webhook HMAC validation failing in Postman**
   - Root cause: wrong secret formatting in Postman env.
   - Fixed with robust pre-request script normalization and validation.

6. **Deduplication missing**
   - Implemented using `X-Shopify-Webhook-Id`, persisted `webhook_id`, and unique index on `(shop_id, webhook_id)`.

## What would change for a multi-tenant production environment

For production multi-tenant scale, the following changes are recommended:

1. **Queue and workers**
   - Dedicated queue backend (Redis/SQS), worker autoscaling, dead-letter queues, retry policies per job type.

2. **Tenant isolation and authorization**
   - Strong tenant scoping in every query and webhook path.
   - Per-tenant API credentials and role-based internal access.

3. **Secret management**
   - Move secrets from `.env` files to a secret manager (AWS Secrets Manager/Azure Key Vault/GCP Secret Manager).
   - Rotate keys and tokens regularly.

4. **Observability**
   - Structured logs with correlation IDs (request ID, webhook ID, shop ID).
   - Metrics and dashboards for sync durations, failure rates, webhook lag.
   - Alerting on repeated failures and throttling spikes.

5. **Resilience**
   - Idempotency keys for all asynchronous processing.
   - Circuit breaker around Shopify API calls.
   - Backpressure/rate-aware dispatching per shop.

6. **Data lifecycle and compliance**
   - Data retention policies by tenant.
   - Soft-delete and purge workflows.
   - Encryption at rest and in transit with strict access audit trails.

7. **Deployment architecture**
   - Separate web and worker processes.
   - Blue/green or canary deployments.
   - Horizontal scaling of webhook Node service and Laravel API behind load balancers.

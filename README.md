# Shopify Integration Demo

This project contains two services:

- Laravel (PHP) backend for OAuth, product/order sync, persistence, and internal API
- Node.js (Express) webhook receiver for HMAC verification and forwarding

The implementation supports Shopify OAuth, GraphQL sync with cursor pagination, internal API security, webhook verification, deduplication, and webhook event tracking.

## Setup Steps

## 1) Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8+
- ngrok (or equivalent tunnel)

## 2) Backend Setup (Laravel)

```bash
cd backend
composer install
php artisan key:generate
php artisan migrate
```

## 3) Webhooks Service Setup (Node)

```bash
cd webhooks-service
npm install
```

## Environment Configuration

## Laravel (`backend/.env`)

Configure at minimum:

```env
APP_ENV=local
APP_DEBUG=false
APP_URL=https://YOUR_BACKEND_NGROK_URL

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shopify_integration
DB_USERNAME=root
DB_PASSWORD=root

SHOPIFY_API_KEY=...
SHOPIFY_API_SECRET=...
SHOPIFY_SCOPES=read_products,write_products,read_orders,write_orders
SHOPIFY_API_VERSION=2024-01
SHOPIFY_WEBHOOK_SECRET=...

INTERNAL_API_KEY=...
SHOPIFY_SKIP_SSL_VERIFY=1
WEBHOOKS_SERVICE_URL=http://localhost:3000
```

Notes:
- Keep `INTERNAL_API_KEY` identical between Laravel and Node services.
- `APP_DEBUG=false` is recommended for safer error output.

## Node (`webhooks-service/.env`)

```env
PORT=3000
SHOPIFY_API_SECRET=...
LARAVEL_API_URL=https://YOUR_BACKEND_NGROK_URL/api
INTERNAL_API_KEY=...
```

## How to Run PHP Service

```bash
cd backend
php artisan serve --port=8000
```

## How to Run Node Service

```bash
cd webhooks-service
node server.js
```

## How to Configure Shopify App

In Shopify Partners Dashboard:

1. Open your app
2. Set **App URL** to your backend public URL (ngrok), for example:
   - `https://YOUR_BACKEND_NGROK_URL`
3. Add **Allowed redirection URL**:
   - `https://YOUR_BACKEND_NGROK_URL/shopify/callback`
4. Ensure app scopes match backend `.env` (`SHOPIFY_SCOPES`)
5. Reinstall app whenever scopes change

OAuth install URL format:

```text
https://YOUR_BACKEND_NGROK_URL/shopify/install?shop=your-store.myshopify.com
```

## How to Configure Webhooks

Use the Node service public URL (tunnel to port `3000`) and register:

- `products/update` -> `https://YOUR_NODE_NGROK_URL/webhooks/products/update`
- `orders/create` -> `https://YOUR_NODE_NGROK_URL/webhooks/orders/create`

Behavior:
- Node verifies `X-Shopify-Hmac-Sha256` using raw request body
- Returns `200` immediately after verification
- Forwards payload to Laravel internal endpoint
- Laravel persists webhook event and processes upsert logic

## How to Test the Application

## A) OAuth

1. Open install URL in browser
2. Authorize app in Shopify
3. Confirm success response from `/shopify/installed`

## B) Internal API (with internal key)

```bash
curl -X POST "http://127.0.0.1:8000/api/sync/products" \
  -H "X-Internal-Api-Key: YOUR_INTERNAL_API_KEY"

curl -X POST "http://127.0.0.1:8000/api/sync/orders?since=2026-01-01" \
  -H "X-Internal-Api-Key: YOUR_INTERNAL_API_KEY"

curl "http://127.0.0.1:8000/api/products?page=1&per_page=50" \
  -H "X-Internal-Api-Key: YOUR_INTERNAL_API_KEY"

curl "http://127.0.0.1:8000/api/orders?page=1&per_page=50" \
  -H "X-Internal-Api-Key: YOUR_INTERNAL_API_KEY"
```

## C) Webhooks

Option 1: Postman collection:
- Import `webhooks-service/postman-webhooks.collection.json`
- Set `shopify_api_secret` in Postman environment
- Send:
  - `POST /webhooks/products/update`
  - `POST /webhooks/orders/create`

Option 2: cURL (signed body) for Node endpoint testing.

## D) Deduplication check

Send same webhook twice with identical `X-Shopify-Webhook-Id`.
Expected behavior:
- first delivery: processed
- second delivery: accepted as duplicate and skipped

## API Summary

### Backend API

- `GET /shopify/install?shop=...`
- `GET /shopify/callback`
- `GET /shopify/installed`
- `POST /api/sync/products`
- `POST /api/sync/orders?since=YYYY-MM-DD`
- `GET /api/products?page=1&per_page=50`
- `GET /api/orders?page=1&per_page=50`

### Node Webhooks API

- `POST /webhooks/products/update`
- `POST /webhooks/orders/create`

Legacy compatibility endpoint also exists:

- `POST /webhooks/shopify`

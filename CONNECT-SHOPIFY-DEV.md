# Connect to Your Shopify Dev App

Follow these steps to connect this project to your Shopify development app and a dev store.

---

## 1. Get your app credentials from Shopify Partners

1. Go to [partners.shopify.com](https://partners.shopify.com) and sign in.
2. Open **Apps** → your app (or **Create app** → **Create app manually**).
3. Go to **App setup** (or **Configuration**).
4. Note:
   - **Client ID** → this is your `SHOPIFY_API_KEY`
   - **Client secret** → **Show** and copy → this is your `SHOPIFY_API_SECRET`

---
   
## 2. Expose your local app (required for OAuth)

Shopify must be able to reach your Laravel app for the OAuth callback. Use a tunnel so `localhost` gets a public HTTPS URL.

### Option A: ngrok (recommended)

1. Install ngrok: [ngrok.com/download](https://ngrok.com/download) or `winget install ngrok.ngrok`
2. Start your Laravel app:
   ```bash
   cd backend
   php artisan serve
   ```
   (Runs at `http://localhost:8000`.)

3. In another terminal, run:
   ```bash
   ngrok http 8000
   ```
4. Copy the **HTTPS** URL ngrok shows (e.g. `https://abc123.ngrok-free.app`).  
   This is your **public app URL** for the next steps.

### Option B: Other tunnels

You can use **laragon**, **expose**, **cloudflared**, or any tunnel that gives you an HTTPS URL pointing to `http://localhost:8000`. Use that HTTPS URL wherever this guide says “your tunnel URL”.

---

## 3. Configure the app in Shopify Partners

1. In Partners, go to your app → **App setup** (or **Configuration**).
2. Set:

   | Field | Value |
   |-------|--------|
   | **App URL** | `https://YOUR-TUNNEL-URL` (e.g. `https://abc123.ngrok-free.app`) — no trailing slash |
   | **Allowed redirection URL(s)** | Add: `https://YOUR-TUNNEL-URL/shopify/callback` |

   Example:
   - App URL: `https://abc123.ngrok-free.app`
   - Redirect URL: `https://abc123.ngrok-free.app/shopify/callback`

3. (Optional) For webhooks later:
   - You’ll point webhooks to your **Node.js webhooks service** (e.g. a second ngrok URL for port 3000). You can add that when you set up webhooks.

4. Save.

---

## 4. Configure Laravel (.env)

1. In the project:
   ```bash
   cd backend
   cp .env.example .env
   ```

2. Generate keys:
   ```bash
   php artisan key:generate
   php -r "echo 'INTERNAL_API_KEY=' . bin2hex(random_bytes(16)) . PHP_EOL;"
   ```
   Copy the `INTERNAL_API_KEY=...` line.

3. Edit `backend/.env` and set at least:

   ```env
   APP_URL=https://YOUR-TUNNEL-URL
   
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=shopify_integration
   DB_USERNAME=root
   DB_PASSWORD=your_mysql_password

   SHOPIFY_API_KEY=your_client_id_from_partners
   SHOPIFY_API_SECRET=your_client_secret_from_partners
   SHOPIFY_SCOPES=read_products,write_products,read_orders,write_orders
   SHOPIFY_API_VERSION=2024-01

   INTERNAL_API_KEY=paste_the_generated_key_here
   ```

   - Replace `YOUR-TUNNEL-URL` with your ngrok (or tunnel) host, e.g. `abc123.ngrok-free.app` (no `https://` in `APP_URL` is OK; Laravel will use it for redirects).
   - Use the **Client ID** and **Client secret** from step 1 for `SHOPIFY_API_KEY` and `SHOPIFY_API_SECRET`.

4. Run migrations:
   ```bash
   php artisan migrate
   ```

---

## 5. Connect your dev store

1. Make sure:
   - Laravel is running: `php artisan serve`
   - Your tunnel (e.g. ngrok) is running and points at port 8000.

2. Get your dev store hostname:
   - In Partners: **Stores** → your dev store → the hostname is like `your-store.myshopify.com`.

3. In the browser, open (use your real tunnel URL if different):
   ```
   https://YOUR-TUNNEL-URL/shopify/install?shop=your-store.myshopify.com
   ```
   Example:
   ```
   https://abc123.ngrok-free.app/shopify/install?shop=my-dev-store.myshopify.com
   ```

4. You should be redirected to Shopify’s authorization screen. Click **Install** (or **Allow**).

5. Shopify redirects back to your app. You should see a JSON response like:
   ```json
   { "message": "Shop connected. Use the internal API to sync products and orders.", "endpoints": [...] }
   ```

6. The store’s access token is now stored (encrypted) in the `shops` table.

---

## 6. Verify the connection

- In Laravel Tinker or your DB client:
  ```bash
  php artisan tinker
  >>> \App\Models\Shop::first();
  ```
  You should see a row with your `shop_domain` (and no `access_token` in the output, as it’s hidden).

- Sync products (requires internal API key in the request):
  ```bash
  curl -X POST "https://YOUR-TUNNEL-URL/api/sync/products" -H "X-Internal-Api-Key: YOUR_INTERNAL_API_KEY"
  ```

---

## Troubleshooting

| Issue | What to check |
|-------|----------------|
| **Redirect URI mismatch** | Redirect URL in Partners must be exactly `https://YOUR-TUNNEL-URL/shopify/callback` (same protocol, host, path). |
| **Invalid state parameter** | Session is required. Ensure Laravel session is working (e.g. no cookie/session issues in the browser, same browser for install and callback). |
| **Invalid request signature** | `SHOPIFY_API_SECRET` in `.env` must match the app’s **Client secret** in Partners. |
| **App URL / 404** | `APP_URL` should match the URL you use in the browser (tunnel URL). Keep tunnel and `php artisan serve` running. |
| **Database errors** | Run `php artisan migrate` and confirm DB_* in `.env` are correct. |

---

## Optional: Webhooks (Node.js service)

To receive Shopify webhooks:

1. Run the webhooks service and expose it (e.g. second ngrok on port 3000).
2. In `webhooks-service/.env` set:
   - `SHOPIFY_API_SECRET` = same as Laravel
   - `LARAVEL_API_URL` = `https://YOUR-TUNNEL-URL/api`
   - `INTERNAL_API_KEY` = same as Laravel.
3. In Partners, under your app’s **Event subscriptions** (or Webhooks), set the webhook URL to:
   `https://YOUR-WEBHOOKS-TUNNEL-URL/webhooks/shopify`.

After that, your Laravel app will receive verified webhook payloads from the Node service.

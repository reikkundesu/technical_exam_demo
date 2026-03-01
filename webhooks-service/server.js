/**
 * Shopify Webhooks Service (Node.js + Express)
 * - Receives webhooks from Shopify
 * - Verifies HMAC-SHA256 signature using raw body
 * - Forwards verified payloads to Laravel internal API
 */

require('dotenv').config();

const express = require('express');
const crypto = require('crypto');
const axios = require('axios');

const app = express();
const PORT = process.env.PORT || 3000;
const SHOPIFY_API_SECRET = process.env.SHOPIFY_API_SECRET || '';
const LARAVEL_API_URL = (process.env.LARAVEL_API_URL || '').replace(/\/$/, '');
const LARAVEL_BASE_URL = (
  process.env.LARAVEL_BASE_URL || LARAVEL_API_URL.replace(/\/api$/i, '')
).replace(/\/$/, '');
const INTERNAL_API_KEY = process.env.INTERNAL_API_KEY || '';

// Raw body is required for HMAC verification - must be before any JSON parser
app.use(
  express.json({
    verify: (req, res, buf) => {
      req.rawBody = buf;
    },
  })
);

function verifyShopifyHmac(rawBody, hmacHeader) {
  if (!SHOPIFY_API_SECRET || !hmacHeader) return false;
  const normalizedHeader = String(hmacHeader).trim().replace(/^sha256=/i, '');
  const generated = crypto
    .createHmac('sha256', SHOPIFY_API_SECRET)
    .update(rawBody)
    .digest('base64');
  const a = Buffer.from(generated.trim(), 'base64');
  const b = Buffer.from(normalizedHeader, 'base64');
  if (a.length !== b.length) return false;
  return crypto.timingSafeEqual(a, b);
}

async function forwardToLaravel(topic, shopDomain, webhookId, payload) {
  if (!INTERNAL_API_KEY || !LARAVEL_API_URL) {
    console.error('INTERNAL_API_KEY or LARAVEL_API_URL not set');
    return { ok: false, error: 'Config missing' };
  }
  try {
    const { data, status } = await axios.post(
      `${LARAVEL_API_URL}/webhooks/shopify`,
      payload,
      {
        headers: {
          'Content-Type': 'application/json',
          'X-Internal-Api-Key': INTERNAL_API_KEY,
          'X-Shopify-Topic': topic,
          'X-Shopify-Shop-Domain': shopDomain,
          'X-Shopify-Webhook-Id': webhookId,
        },
        timeout: 10000,
        validateStatus: () => true,
      }
    );
    if (status >= 400) {
      console.error('Laravel API error', status, data);
      return { ok: false, status, data };
    }
    return { ok: true, data };
  } catch (err) {
    console.error('Forward to Laravel failed', err.message);
    return { ok: false, error: err.message };
  }
}

async function proxyToLaravel(req, res) {
  if (!LARAVEL_BASE_URL) {
    return res.status(500).send('Laravel base URL not configured');
  }

  try {
    const targetUrl = `${LARAVEL_BASE_URL}${req.originalUrl}`;
    const method = String(req.method || 'GET').toUpperCase();
    const headers = { ...req.headers };

    delete headers.host;
    delete headers['content-length'];

    const response = await axios({
      method,
      url: targetUrl,
      headers,
      data: method === 'GET' || method === 'HEAD' ? undefined : req.body,
      timeout: 15000,
      validateStatus: () => true,
      responseType: 'arraybuffer',
      maxRedirects: 0,
    });

    for (const [key, value] of Object.entries(response.headers || {})) {
      if (key.toLowerCase() === 'transfer-encoding') continue;
      if (typeof value !== 'undefined') {
        res.setHeader(key, value);
      }
    }

    return res.status(response.status).send(Buffer.from(response.data));
  } catch (err) {
    console.error('Proxy to Laravel failed', err.message);
    return res.status(502).send('Bad Gateway');
  }
}

function createWebhookHandler(defaultTopic) {
  return (req, res) => {
    const hmac = req.headers['x-shopify-hmac-sha256'];
    const topic = req.headers['x-shopify-topic'] || defaultTopic;
    const shopDomain = req.headers['x-shopify-shop-domain'];
    const webhookId = req.headers['x-shopify-webhook-id'];

    if (!req.rawBody) {
      return res.status(400).send('Bad Request');
    }

    if (!verifyShopifyHmac(req.rawBody, hmac)) {
      return res.status(401).send('Webhook verification failed');
    }

    if (!topic || !shopDomain) {
      return res.status(400).send('Missing required webhook headers');
    }

    const payload = typeof req.body === 'object' ? req.body : {};

    // Required: acknowledge quickly after verification
    res.status(200).send('OK');

    setImmediate(async () => {
      const result = await forwardToLaravel(topic, shopDomain, webhookId, payload);
      if (!result.ok) {
        console.error('Downstream processing failed', { topic, shopDomain, result });
      }
    });
  };
}

app.post('/webhooks/products/update', createWebhookHandler('products/update'));
app.post('/webhooks/orders/create', createWebhookHandler('orders/create'));
app.post('/webhooks/shopify', createWebhookHandler());

app.get('/health', (req, res) => {
  res.json({ status: 'ok', service: 'shopify-webhooks' });
});

app.use((req, res) => {
  proxyToLaravel(req, res);
});

app.listen(PORT, () => {
  console.log(`Webhooks service listening on port ${PORT}`);
  if (!SHOPIFY_API_SECRET) console.warn('SHOPIFY_API_SECRET is not set');
  if (!INTERNAL_API_KEY) console.warn('INTERNAL_API_KEY is not set');
  if (!LARAVEL_API_URL) console.warn('LARAVEL_API_URL is not set');
  if (!LARAVEL_BASE_URL) console.warn('LARAVEL_BASE_URL is not set');
});

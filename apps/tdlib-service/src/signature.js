import { createHmac } from 'node:crypto';

/**
 * Inbound webhook signature shared with Laravel's VerifyTelegramWebhook
 * middleware: hex HMAC-SHA256 over "{timestamp}.{rawBody}".
 *
 * Keep the byte format in lockstep with
 * apps/api/app/Http/Middleware/VerifyTelegramWebhook.php — the fixture in
 * test/signature.test.js pins both sides to the same vector.
 *
 * @param {string} secret
 * @param {string} timestamp unix seconds as a string
 * @param {string} rawBody
 * @returns {string} hex digest
 */
export function signPayload(secret, timestamp, rawBody) {
  return createHmac('sha256', secret).update(`${timestamp}.${rawBody}`).digest('hex');
}

/**
 * @param {string} secret
 * @param {string} rawBody
 * @param {string} [timestamp]
 * @returns {Record<string, string>}
 */
export function buildWebhookHeaders(secret, rawBody, timestamp = String(Math.floor(Date.now() / 1000))) {
  return {
    'content-type': 'application/json',
    'x-telegram-timestamp': timestamp,
    'x-telegram-signature': signPayload(secret, timestamp, rawBody),
  };
}

import { buildWebhookHeaders } from './signature.js';

/**
 * Forward an inbound Telegram message to Laravel with the signed webhook
 * envelope. Retries with exponential backoff — a dropped message must not
 * lose supplier chat history silently.
 *
 * @param {{ webhookUrl: string, webhookSecret: string, webhookRetries: number }} config
 * @param {{ account_id: string|null, chat_id: number, message_id: number, from_user_id?: number, text: string, sent_at?: string }} payload
 * @param {typeof fetch} [fetchImpl]
 * @returns {Promise<boolean>} delivered or not
 */
export async function postInbound(config, payload, fetchImpl = fetch) {
  if (!config.webhookUrl) return false;

  const rawBody = JSON.stringify(payload);

  for (let attempt = 0; attempt < config.webhookRetries; attempt += 1) {
    try {
      const res = await fetchImpl(config.webhookUrl, {
        method: 'POST',
        headers: buildWebhookHeaders(config.webhookSecret, rawBody),
        body: rawBody,
        signal: AbortSignal.timeout(10_000),
      });
      if (res.ok) return true;
      console.error(
        JSON.stringify({
          event: 'inbound_webhook_rejected',
          status: res.status,
          attempt: attempt + 1,
        }),
      );
    } catch (err) {
      console.error(
        JSON.stringify({ event: 'inbound_webhook_error', error: String(err), attempt: attempt + 1 }),
      );
    }
    if (attempt < config.webhookRetries - 1) {
      await new Promise((resolve) => setTimeout(resolve, 1000 * 3 ** attempt));
    }
  }

  return false;
}

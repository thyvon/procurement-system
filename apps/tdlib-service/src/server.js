import { createServer as createHttpServer } from 'node:http';
import { timingSafeEqual } from 'node:crypto';

/**
 * Constant-time bearer token comparison (never trust == for secrets).
 *
 * @param {string} headerValue raw Authorization header
 * @param {string} expected configured SERVICE_TOKEN
 */
function bearerMatches(headerValue, expected) {
  if (!expected || !headerValue) return false;
  const [scheme, token] = headerValue.split(' ');
  if (scheme !== 'Bearer' || !token) return false;
  const a = Buffer.from(token);
  const b = Buffer.from(expected);
  if (a.length !== b.length) return false;
  return timingSafeEqual(a, b);
}

function readJsonBody(req, limit = 100 * 1024) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    let size = 0;
    req.on('data', (chunk) => {
      size += chunk.length;
      if (size > limit) {
        reject(new Error('payload_too_large'));
        req.destroy();
        return;
      }
      chunks.push(chunk);
    });
    req.on('end', () => {
      if (chunks.length === 0) return resolve({});
      try {
        resolve(JSON.parse(Buffer.concat(chunks).toString('utf8')));
      } catch {
        reject(new Error('invalid_json'));
      }
    });
    req.on('error', reject);
  });
}

function sendJson(res, status, body) {
  const raw = JSON.stringify(body);
  res.writeHead(status, { 'content-type': 'application/json', 'content-length': Buffer.byteLength(raw) });
  res.end(raw);
}

/**
 * HTTP surface consumed by Modules/Telegram/Services/TelegramGateway.php:
 *   GET  /health        liveness + authorization state (no auth: probe only)
 *   POST /send          { chat_id?|phone?, text }  -> 200 | 429 (FLOOD_WAIT) | 4xx
 *   POST /auth/code     { code }                   -> { state }
 *   POST /auth/password { password }               -> { state }
 * All POSTs require `Authorization: Bearer <SERVICE_TOKEN>`.
 *
 * @param {{ serviceToken: string, accountId: string|null }} config
 * @param {Awaited<ReturnType<import('./client.js').createClient>>} client
 */
export function createServer(config, client) {
  return createHttpServer(async (req, res) => {
    try {
      const url = new URL(req.url, 'http://localhost');
      const path = url.pathname;

      if (req.method === 'GET' && path === '/health') {
        const status = await client.getStatus();
        return sendJson(res, 200, { ...status, account_id: config.accountId });
      }

      if (req.method === 'POST' && (path === '/send' || path === '/auth/code' || path === '/auth/password')) {
        if (!bearerMatches(req.headers.authorization, config.serviceToken)) {
          return sendJson(res, 401, { error: 'unauthorized' });
        }

        let body;
        try {
          body = await readJsonBody(req);
        } catch (err) {
          return sendJson(res, err.message === 'payload_too_large' ? 413 : 400, { error: err.message });
        }

        if (path === '/send') {
          const text = typeof body.text === 'string' ? body.text.trim() : '';
          if (!text) return sendJson(res, 400, { error: 'text_required' });
          if (!body.chat_id && !body.phone && !body.username) {
            return sendJson(res, 400, { error: 'chat_id_phone_or_username_required' });
          }

          const result = await client.sendMessage({
            chatId: body.chat_id ?? null,
            phone: body.phone ?? null,
            username: typeof body.username === 'string' ? body.username : null,
            text,
          });

          if (result.ok) return sendJson(res, 200, { ok: true, message_id: result.message_id, chat_id: result.chat_id });
          if (result.code === 'FLOOD_WAIT') {
            res.setHeader('retry-after', String(result.retryAfter ?? 60));
            return sendJson(res, 429, { error: 'FLOOD_WAIT', retryAfter: result.retryAfter ?? 60 });
          }
          return sendJson(res, 400, { error: result.code });
        }

        if (path === '/auth/code') {
          const code = typeof body.code === 'string' ? body.code.trim() : '';
          if (!code) return sendJson(res, 400, { error: 'code_required' });
          const result = await client.checkCode(code);
          if (!result.ok) return sendJson(res, 400, { error: result.error ?? 'invalid_code' });
          const status = await client.getStatus();
          return sendJson(res, 200, { state: status.state });
        }

        // /auth/password
        const password = typeof body.password === 'string' ? body.password : '';
        if (!password) return sendJson(res, 400, { error: 'password_required' });
        const result = await client.checkPassword(password);
        if (!result.ok) return sendJson(res, 400, { error: result.error ?? 'invalid_password' });
        const status = await client.getStatus();
        return sendJson(res, 200, { state: status.state });
      }

      return sendJson(res, 404, { error: 'not_found' });
    } catch (err) {
      console.error(JSON.stringify({ event: 'http_handler_error', error: String(err) }));
      return sendJson(res, 500, { error: 'internal_error' });
    }
  });
}

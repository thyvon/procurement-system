import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { createServer as createHttpServer } from 'node:http';
import { postInbound } from '../src/webhook.js';
import { signPayload } from '../src/signature.js';

let capture;
let received;
let base;

before(async () => {
  capture = createHttpServer((req, res) => {
    const chunks = [];
    req.on('data', (c) => chunks.push(c));
    req.on('end', () => {
      if (req.url !== '/api/internal/telegram/inbound') {
        res.writeHead(404).end('{}');
        return;
      }
      received = {
        url: req.url,
        timestamp: req.headers['x-telegram-timestamp'],
        signature: req.headers['x-telegram-signature'],
        body: Buffer.concat(chunks).toString('utf8'),
      };
      res.writeHead(202).end('{}');
    });
  });
  await new Promise((resolve) => capture.listen(0, resolve));
  base = `http://127.0.0.1:${capture.address().port}`;
});

after(() => capture.close());

const config = (overrides = {}) => ({
  webhookUrl: `${base}/api/internal/telegram/inbound`,
  webhookSecret: 'test-webhook-secret',
  webhookRetries: 1,
  ...overrides,
});

test('postInbound signs the body so VerifyTelegramWebhook accepts it', async () => {
  const payload = { account_id: '01ACC', chat_id: 1, message_id: 9, text: 'hello supplier' };
  const delivered = await postInbound(config(), payload);

  assert.equal(delivered, true);
  assert.equal(received.url, '/api/internal/telegram/inbound');
  assert.equal(received.body, JSON.stringify(payload));
  assert.equal(
    received.signature,
    signPayload('test-webhook-secret', received.timestamp, received.body),
  );
});

test('postInbound returns false when no webhook URL is configured', async () => {
  const delivered = await postInbound(config({ webhookUrl: '' }), { chat_id: 1, text: 'x' });
  assert.equal(delivered, false);
});

test('postInbound returns false after the target rejects', async () => {
  const delivered = await postInbound(config({ webhookUrl: `${base}/missing` }), { chat_id: 1, text: 'x' });
  assert.equal(delivered, false);
});

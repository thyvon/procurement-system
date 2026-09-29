import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from '../src/server.js';
import { createMockClient } from '../src/client.js';

const TOKEN = 'test-service-token';
let server;
let base;

before(async () => {
  const client = createMockClient();
  server = createServer({ serviceToken: TOKEN, accountId: '01ACC' }, client);
  await new Promise((resolve) => server.listen(0, resolve));
  base = `http://127.0.0.1:${server.address().port}`;
});

after(() => server.close());

const post = (path, body, token = TOKEN) =>
  fetch(`${base}${path}`, {
    method: 'POST',
    headers: {
      'content-type': 'application/json',
      ...(token ? { authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify(body),
  });

test('GET /health is open and reports state', async () => {
  const res = await fetch(`${base}/health`);
  assert.equal(res.status, 200);
  const json = await res.json();
  assert.equal(json.state, 'ready');
  assert.equal(json.mock, true);
  assert.equal(json.account_id, '01ACC');
});

test('POST /send rejects a missing bearer token', async () => {
  const res = await post('/send', { chat_id: 1, text: 'hi' }, null);
  assert.equal(res.status, 401);
});

test('POST /send rejects a wrong bearer token', async () => {
  const res = await post('/send', { chat_id: 1, text: 'hi' }, 'wrong-token');
  assert.equal(res.status, 401);
});

test('POST /send requires text and a target', async () => {
  let res = await post('/send', { chat_id: 1, text: '   ' });
  assert.equal(res.status, 400);
  const body = await res.json();
  assert.equal(body.error, 'text_required');

  res = await post('/send', { text: 'hello' });
  assert.equal(res.status, 400);
  assert.equal((await res.json()).error, 'chat_id_phone_or_username_required');
});

test('POST /send resolves a username target', async () => {
  const res = await post('/send', { username: '@vunthypro', text: 'handle test' });
  assert.equal(res.status, 200);
  const json = await res.json();
  assert.equal(json.ok, true);
  assert.ok(json.chat_id > 0);
  assert.ok(json.message_id > 0);
});

test('POST /send delivers through the mock client', async () => {
  const res = await post('/send', { chat_id: 424242, text: 'RFQ-2041: please quote' });
  assert.equal(res.status, 200);
  const json = await res.json();
  assert.equal(json.ok, true);
  assert.ok(json.message_id > 0);
  assert.equal(json.chat_id, 424242);
});

test('POST /auth/code transitions to ready', async () => {
  const client = createMockClient({ initialState: 'waiting_code' });
  const s = createServer({ serviceToken: TOKEN, accountId: null }, client);
  await new Promise((resolve) => s.listen(0, resolve));
  const port = s.address().port;

  const res = await fetch(`http://127.0.0.1:${port}/auth/code`, {
    method: 'POST',
    headers: { 'content-type': 'application/json', authorization: `Bearer ${TOKEN}` },
    body: JSON.stringify({ code: '12345' }),
  });
  assert.equal(res.status, 200);
  assert.equal((await res.json()).state, 'ready');
  s.close();
});

test('unknown routes return 404', async () => {
  const res = await fetch(`${base}/nope`);
  assert.equal(res.status, 404);
});

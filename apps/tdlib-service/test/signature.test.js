import { test } from 'node:test';
import assert from 'node:assert/strict';
import { signPayload, buildWebhookHeaders } from '../src/signature.js';

/**
 * Cross-language parity vector — the SAME assertion exists in
 * apps/api/Modules/Telegram/Tests/Feature/TelegramInboundTest.php
 * ("it matches the cross-language signature fixture").
 * If either side changes the signing format, one of the two suites fails.
 */
const FIXTURE = {
  secret: 'test-webhook-secret',
  timestamp: '1700000000',
  body: '{"account_id":"01ARZ3NDEKTSV4RRFFQ69G5FAV","chat_id":1,"message_id":1,"text":"hi"}',
  expected: 'c55564bf3c84b7970b6e2b11d2f48fbd30e3750a28ec49e14c3065a1c1eca02b',
};

test('HMAC-SHA256 matches the PHP VerifyTelegramWebhook fixture', () => {
  assert.equal(signPayload(FIXTURE.secret, FIXTURE.timestamp, FIXTURE.body), FIXTURE.expected);
});

test('buildWebhookHeaders signs exactly the timestamp it advertises', () => {
  const headers = buildWebhookHeaders(FIXTURE.secret, FIXTURE.body, FIXTURE.timestamp);
  assert.equal(headers['x-telegram-timestamp'], FIXTURE.timestamp);
  assert.equal(headers['x-telegram-signature'], FIXTURE.expected);
  assert.equal(headers['content-type'], 'application/json');
});

test('signature changes when the timestamp changes (replay guard input)', () => {
  const a = signPayload(FIXTURE.secret, '1700000000', FIXTURE.body);
  const b = signPayload(FIXTURE.secret, '1700000001', FIXTURE.body);
  assert.notEqual(a, b);
});

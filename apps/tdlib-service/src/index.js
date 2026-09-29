import { loadConfig } from './config.js';
import { createClient } from './client.js';
import { createServer } from './server.js';
import { postInbound } from './webhook.js';

const config = loadConfig();
const client = await createClient(config);

client.onMessage((message) => {
  postInbound(config, {
    account_id: config.accountId,
    chat_id: message.chatId,
    message_id: message.messageId,
    text: message.text,
    sent_at: new Date().toISOString(),
  }).then((delivered) => {
    if (!delivered) {
      console.error(JSON.stringify({ event: 'inbound_dropped', chat_id: message.chatId }));
    }
  });
});

const server = createServer(config, client);

server.listen(config.port, () => {
  console.log(
    JSON.stringify({
      event: 'tdlib_service_started',
      mode: config.mode,
      port: config.port,
      account_id: config.accountId,
      webhook: Boolean(config.webhookUrl),
    }),
  );
});

for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => {
    console.log(JSON.stringify({ event: 'tdlib_service_stopping', signal }));
    server.close(() => process.exit(0));
    setTimeout(() => process.exit(0), 3000).unref();
  });
}

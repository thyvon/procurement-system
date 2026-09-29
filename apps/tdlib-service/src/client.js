const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Parse a TDLib error into a structured code so the HTTP layer can map
 * FLOOD_WAIT_* to 429 with Retry-After semantics.
 *
 * @param {unknown} err
 * @returns {{ code: string, retryAfter?: number, message: string }}
 */
export function classifyTelegramError(err) {
  const message = err instanceof Error ? err.message : String(err);
  const flood = message.match(/FLOOD_WAIT_(\d+)/);
  if (flood) return { code: 'FLOOD_WAIT', retryAfter: Number(flood[1]), message };
  return { code: message.replace(/^[A-Z_]+: /, '') || 'TELEGRAM_ERROR', message };
}

/**
 * In-memory client used when TG_API_ID/TG_API_HASH are unset (development and
 * the node:test suite) — same interface as the real one, no network.
 *
 * @param {{ initialState?: string }} [options]
 */
export function createMockClient(options = {}) {
  let state = options.initialState ?? 'ready';
  let connection = 'ready';
  let sentCount = 0;
  const listeners = [];

  return {
    mock: true,
    async getStatus() {
      return { state, connection, mock: true };
    },
    async sendMessage({ chatId, phone }) {
      if (state !== 'ready') {
        return { ok: false, code: 'AUTHORIZATION_PENDING', error: `state:${state}` };
      }
      sentCount += 1;
      const resolvedChat = chatId ?? (phone ? Number(String(phone).replace(/\D/g, '').slice(-9)) : sentCount);
      return { ok: true, message_id: sentCount, chat_id: resolvedChat };
    },
    async requestCode() {
      if (state !== 'ready') state = 'waiting_code';
      return { state };
    },
    async checkCode(code) {
      if (!code) return { ok: false, error: 'code_required' };
      state = 'ready';
      return { ok: true, state };
    },
    async checkPassword(password) {
      if (!password) return { ok: false, error: 'password_required' };
      state = 'ready';
      return { ok: true, state };
    },
    onMessage(cb) {
      listeners.push(cb);
    },
    /** test helper: simulate an incoming Telegram message */
    emitInbound(chatId, text) {
      for (const cb of listeners) cb({ chatId, messageId: Date.now() % 1_000_000, text });
    },
    sleep,
  };
}

/**
 * Real TDLib client. tdl is imported lazily so mock mode never touches the
 * native library (keeps tests hermetic and install optional in CI).
 *
 * @param {import('./config.js').loadConfig extends (...a:any)=>infer R ? R : never} config
 */
export async function createRealClient(config) {
  const tdl = (await import('tdl')).default;
  const fs = await import('node:fs');
  const path = await import('node:path');

  fs.mkdirSync(config.tg.sessionDir, { recursive: true });
  tdl.configure({ verbosityLevel: 0 });

  const client = tdl.createClient({
    apiId: config.tg.apiId,
    apiHash: config.tg.apiHash,
    databaseDirectory: path.join(config.tg.sessionDir, 'db'),
    filesDirectory: path.join(config.tg.sessionDir, 'files'),
    databaseEncryptionKey: config.tg.dbKey,
  });

  let state = 'connecting';
  let connection = 'connecting';
  const messageListeners = [];

  const setState = (next) => {
    state = next;
  };

  const handleAuthState = async (authState) => {
    switch (authState._) {
      case 'authorizationStateWaitPhoneNumber': {
        if (config.tg.phone) {
          setState('sending_phone');
          await client.invoke({ _: 'setAuthenticationPhoneNumber', phone_number: config.tg.phone });
        } else {
          setState('waiting_phone');
        }
        break;
      }
      case 'authorizationStateWaitCode':
        setState('waiting_code');
        break;
      case 'authorizationStateWaitPassword':
        setState('waiting_password');
        break;
      case 'authorizationStateReady':
        setState('ready');
        connection = 'ready';
        break;
      case 'authorizationStateClosing':
      case 'authorizationStateClosed':
      case 'authorizationStateLoggingOut':
        setState('disconnected');
        connection = 'disconnected';
        break;
      case 'authorizationStateWaitEncryptionKey':
        await client.invoke({ _: 'checkDatabaseEncryptionKey', key: config.tg.dbKey });
        break;
      default:
        break;
    }
  };

  client.on('update', (update) => {
    if (update._ === 'updateAuthorizationState') {
      handleAuthState(update.authorization_state).catch((err) => {
        console.error(JSON.stringify({ event: 'tdlib_auth_state_error', error: String(err) }));
      });
      return;
    }
    if (update._ === 'updateConnectionState') {
      connection = String(update.state).replace('connectionState', '').toLowerCase();
      return;
    }
    if (update._ === 'updateNewMessage') {
      const message = update.message;
      if (message?.is_outgoing) return;
      const content = message?.content;
      const text = content && content._ === 'messageText' ? content.text : null;
      if (!text) return;
      for (const cb of messageListeners) {
        cb({ chatId: message.chat_id, messageId: message.id, text });
      }
    }
  });

  await client.connect();

  const resolveChatId = async ({ chatId, phone }) => {
    if (chatId) return chatId;
    if (!phone) throw Object.assign(new Error('chat_id or phone required'), { code: 'MISSING_TARGET' });
    const hash = String(Date.now());
    const result = await client.invoke({
      _: 'contacts.importContacts',
      contacts: [{ _: 'inputPhoneContact', client_input_hash: hash, phone, first_name: 'Supplier', last_name: '' }],
    });
    const user = result?.users?.[0];
    if (!user) throw Object.assign(new Error('PHONE_NUMBER_BANNED'), { code: 'USER_NOT_FOUND' });
    const chat = await client.invoke({ _: 'createPrivateChat', user_id: user.id, force: false });
    return chat.id;
  };

  return {
    mock: false,
    async getStatus() {
      return { state, connection, mock: false };
    },
    async sendMessage({ chatId, phone, text }) {
      try {
        const target = await resolveChatId({ chatId, phone });
        const result = await client.invoke({
          _: 'sendMessage',
          chat_id: target,
          input_message_content: { _: 'inputMessageText', text, disable_web_page_preview: true },
        });
        return {
          ok: true,
          message_id: result?.message?.id ?? null,
          chat_id: result?.message?.chat_id ?? target,
        };
      } catch (err) {
        const classified = classifyTelegramError(err);
        return { ok: false, code: classified.code, retryAfter: classified.retryAfter, error: classified.message };
      }
    },
    async requestCode() {
      // The phone number is configured via TG_PHONE; re-invoking keeps the
      // flow retryable if the code request expired.
      if (config.tg.phone && state !== 'ready') {
        await client.invoke({ _: 'resendAuthenticationCode' }).catch(() => {});
      }
      return { state };
    },
    async checkCode(code) {
      try {
        await client.invoke({ _: 'checkAuthenticationCode', code });
        return { ok: true };
      } catch (err) {
        const classified = classifyTelegramError(err);
        return { ok: false, error: classified.message };
      }
    },
    async checkPassword(password) {
      try {
        await client.invoke({ _: 'checkAuthenticationPassword', password });
        return { ok: true };
      } catch (err) {
        const classified = classifyTelegramError(err);
        return { ok: false, error: classified.message };
      }
    },
    onMessage(cb) {
      messageListeners.push(cb);
    },
  };
}

/**
 * @param {ReturnType<typeof loadConfig>} config
 */
export async function createClient(config) {
  if (config.mode === 'real') return createRealClient(config);
  return createMockClient();
}

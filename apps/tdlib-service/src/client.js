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
  const prebuilt = await import('prebuilt-tdlib');
  const getTdjson = prebuilt.getTdjson ?? prebuilt.default.getTdjson;
  const fs = await import('node:fs');
  const path = await import('node:path');

  fs.mkdirSync(config.tg.sessionDir, { recursive: true });
  // Point tdl at the prebuilt libtdjson.so (its default lookup finds nothing).
  tdl.configure({ verbosityLevel: 0, tdjson: getTdjson() });

  const client = tdl.createClient({
    apiId: config.tg.apiId,
    apiHash: config.tg.apiHash,
    databaseDirectory: path.join(config.tg.sessionDir, 'db'),
    filesDirectory: path.join(config.tg.sessionDir, 'files'),
    databaseEncryptionKey: config.tg.dbKey,
  });

  let state = 'connecting';
  let connection = 'connecting';
  let stateSeq = 0;
  const messageListeners = [];
  let codeResolver = null;
  let passwordResolver = null;

  const setState = (next) => {
    state = next;
    stateSeq += 1;
  };

  /**
   * The auth flow is driven by tdl's login(): it blocks on these callbacks
   * and our HTTP endpoints (/auth/code, /auth/password) resolve them.
   */
  const waitForCode = () => {
    setState('waiting_code');
    return new Promise((resolve) => {
      codeResolver = resolve;
    });
  };

  const waitForPassword = (hint) => {
    setState('waiting_password');
    return new Promise((resolve) => {
      passwordResolver = resolve;
    });
  };

  /** Wait until TDLib reacts to a submitted credential (or time out). */
  const waitForStateChange = async (seqBefore, timeoutMs = 10_000) => {
    const deadline = Date.now() + timeoutMs;
    while (stateSeq === seqBefore && Date.now() < deadline) {
      await new Promise((resolve) => setTimeout(resolve, 100));
    }
    return stateSeq !== seqBefore;
  };

  client.on('update', (update) => {
    if (update._ === 'updateAuthorizationState') {
      const authState = update.authorization_state._;
      if (authState === 'authorizationStateReady') {
        setState('ready');
        connection = 'ready';
      } else if (
        authState === 'authorizationStateClosing' ||
        authState === 'authorizationStateClosed' ||
        authState === 'authorizationStateLoggingOut'
      ) {
        setState('disconnected');
        connection = 'disconnected';
      }
      return;
    }
    if (update._ === 'updateConnectionState') {
      // New TDLib sends an object {_: 'connectionStateReady'}; older ones a string.
      const raw = typeof update.state === 'string' ? update.state : update.state?._;
      connection = String(raw ?? 'unknown').replace('connectionState', '').toLowerCase();
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

  // Kick off the authorization flow; resolves when the account is Ready.
  client
    .login({
      type: 'user',
      getPhoneNumber: () => {
        if (!config.tg.phone) throw new Error('TG_PHONE is not set');
        setState('sending_phone');
        return config.tg.phone;
      },
      getAuthCode: () => waitForCode(),
      getPassword: (hint) => waitForPassword(hint),
      getName: () => ({ firstName: 'Supplier', lastName: 'Assistant' }),
      getEmailAddress: () => Promise.reject(new Error('email_login_unsupported')),
      getEmailCode: () => Promise.reject(new Error('email_login_unsupported')),
      confirmOnAnotherDevice: (link) =>
        console.log(JSON.stringify({ event: 'tdlib_confirm_on_another_device', link })),
    })
    .then(() => {
      setState('ready');
      connection = 'ready';
    })
    .catch((err) => {
      setState('disconnected');
      console.error(JSON.stringify({ event: 'tdlib_login_error', error: String(err) }));
    });

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
      if (!codeResolver) return { ok: false, error: 'not_awaiting_code' };
      const seqBefore = stateSeq;
      const resolve = codeResolver;
      codeResolver = null;
      resolve(code);
      const changed = await waitForStateChange(seqBefore);
      if (!changed) return { ok: false, error: 'auth_timeout' };
      // A rejected code makes login() re-enter getAuthCode() → same state.
      if (state === 'waiting_code') return { ok: false, error: 'invalid_code' };
      return { ok: true, state };
    },
    async checkPassword(password) {
      if (!passwordResolver) return { ok: false, error: 'not_awaiting_password' };
      const seqBefore = stateSeq;
      const resolve = passwordResolver;
      passwordResolver = null;
      resolve(password);
      const changed = await waitForStateChange(seqBefore);
      if (!changed) return { ok: false, error: 'auth_timeout' };
      if (state === 'waiting_password') return { ok: false, error: 'invalid_password' };
      return { ok: true, state };
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

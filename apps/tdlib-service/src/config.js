/**
 * Environment-driven configuration. The service runs in "mock" mode whenever
 * TG_API_ID / TG_API_HASH are absent, so the whole pipeline is testable before
 * credentials and a SIM exist.
 *
 * @param {NodeJS.ProcessEnv} env
 */
export function loadConfig(env = process.env) {
  const apiId = env.TG_API_ID ? Number(env.TG_API_ID) : null;
  const apiHash = env.TG_API_HASH || null;

  return {
    port: Number(env.PORT ?? 3080),
    accountId: env.ACCOUNT_ID || null,
    serviceToken: env.SERVICE_TOKEN || '',
    webhookUrl: env.LARAVEL_WEBHOOK_URL || '',
    webhookSecret: env.WEBHOOK_HMAC_SECRET || '',
    webhookRetries: Number(env.WEBHOOK_RETRIES ?? 3),
    mode: apiId && apiHash ? 'real' : 'mock',
    tg: {
      apiId,
      apiHash,
      phone: env.TG_PHONE || null,
      dbKey: env.TG_DB_KEY || 'dev-database-key',
      sessionDir: env.TG_SESSION_DIR || './session',
    },
  };
}

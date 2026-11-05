const ACCESS_KEY = "procurement.access_token";
const REFRESH_KEY = "procurement.refresh_token";
const EXPIRES_KEY = "procurement.access_expires_at";

export function getAccessToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(ACCESS_KEY);
}

export function getRefreshToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(REFRESH_KEY);
}

/** Epoch millis the current access token stops being accepted by the API. */
export function getAccessTokenExpiresAt(): number | null {
  if (typeof window === "undefined") return null;
  const raw = window.localStorage.getItem(EXPIRES_KEY);
  const value = Number(raw);
  return raw !== null && Number.isFinite(value) ? value : null;
}

/**
 * True when the access token expires within `marginMs` — a missing stored
 * expiry (a session from before expiry tracking) reads as "already expired"
 * so it is rotated on the very first check.
 */
export function isAccessTokenExpiringSoon(marginMs = 60_000): boolean {
  const expiresAt = getAccessTokenExpiresAt();
  return expiresAt === null || expiresAt - Date.now() <= marginMs;
}

export function saveTokens(
  accessToken: string,
  refreshToken: string,
  expiresInSeconds?: number | string,
): void {
  window.localStorage.setItem(ACCESS_KEY, accessToken);
  window.localStorage.setItem(REFRESH_KEY, refreshToken);

  const seconds = Number(expiresInSeconds);
  if (Number.isFinite(seconds) && seconds > 0) {
    window.localStorage.setItem(EXPIRES_KEY, String(Date.now() + seconds * 1000));
  } else {
    window.localStorage.removeItem(EXPIRES_KEY);
  }
}

export function clearTokens(): void {
  window.localStorage.removeItem(ACCESS_KEY);
  window.localStorage.removeItem(REFRESH_KEY);
  window.localStorage.removeItem(EXPIRES_KEY);
}

export function authHeaders(): HeadersInit {
  const token = getAccessToken();
  return token ? { Authorization: `Bearer ${token}` } : {};
}

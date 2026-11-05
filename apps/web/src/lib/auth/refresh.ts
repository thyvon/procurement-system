import { authRefresh } from "@/lib/api/auth/auth";
import { ApiError, unwrap } from "@/lib/api-client";
import {
  clearTokens,
  getAccessToken,
  getRefreshToken,
  isAccessTokenExpiringSoon,
  saveTokens,
} from "./token-store";

type RefreshPayload = {
  access_token?: string;
  refresh_token?: string;
  expires_in?: number | string;
};

/** Rotate this long before the access token actually expires. */
const MARGIN_MS = 60_000;
/** How often an open tab checks whether the token needs rotating. */
const CHECK_INTERVAL_MS = 30_000;
/**
 * Serialises rotation across tabs. The API revokes the whole token family
 * when one refresh token is presented twice (reuse detection), so two tabs
 * racing on the same stored token would log the user out everywhere.
 */
const REFRESH_LOCK = "procurement.refresh-token";

let installed = false;
let inflight: Promise<boolean> | null = null;

/**
 * Proactive rotation (every CHECK_INTERVAL_MS) plus a fetch interceptor that
 * replays a 401 once after rotating. Idempotent; browser only — call it at
 * module scope so the very first request of a page load is already covered.
 */
export function installAuthRefresh(): void {
  if (installed || typeof window === "undefined") return;
  installed = true;

  patchFetch();

  window.setInterval(() => {
    if (getRefreshToken() === null) return;
    void refreshAuth();
  }, CHECK_INTERVAL_MS);
}

/**
 * Rotates the token pair when the current access token is (about to be)
 * unusable. Resolves true when a valid access token is stored afterwards —
 * either this call rotated it or another tab already did.
 *
 * `force` skips the expiry-margin check: the caller just saw a 401, so the
 * server considers the token dead even if the local clock disagrees.
 */
export function refreshAuth(options?: { force?: boolean }): Promise<boolean> {
  inflight ??= withRefreshLock(() => rotate(options?.force ?? false)).finally(() => {
    inflight = null;
  });

  return inflight;
}

async function rotate(force: boolean): Promise<boolean> {
  // Queued behind another tab? It may already have rotated the pair.
  if (!force && !isAccessTokenExpiringSoon(MARGIN_MS)) return true;

  const refreshToken = getRefreshToken();
  if (refreshToken === null) return false;

  try {
    const payload = unwrap<RefreshPayload>(await authRefresh({ refresh_token: refreshToken }));

    if (!payload.access_token || !payload.refresh_token) return false;

    saveTokens(payload.access_token, payload.refresh_token, payload.expires_in);

    return true;
  } catch (error) {
    // 401/422 = the family is dead (expired, reused or revoked): end the
    // session. Network and 5xx failures keep it — the next tick retries.
    if (error instanceof ApiError && (error.statusCode === 401 || error.statusCode === 422)) {
      endSession();
    }

    return false;
  }
}

async function withRefreshLock(task: () => Promise<boolean>): Promise<boolean> {
  if (typeof navigator !== "undefined" && "locks" in navigator) {
    // `await` also flattens the DOM overload's nested promise.
    return await navigator.locks.request(REFRESH_LOCK, () => task());
  }

  return await task();
}

function endSession(): void {
  clearTokens();

  if (!window.location.pathname.startsWith("/login")) {
    window.location.replace("/login");
  }
}

function patchFetch(): void {
  const original = window.fetch.bind(window);

  window.fetch = async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
    const response = await original(input, init);

    if (response.status !== 401 || !isRefreshable(input)) return response;

    const usedToken = authorizationToken(input, init);
    if (usedToken === null || getRefreshToken() === null) return response;

    // The token on the failing request is no longer the stored one when
    // another tab already rotated — just replay with the stored pair.
    const usable =
      usedToken !== getAccessToken() ? true : await refreshAuth({ force: true });

    if (!usable) return response;

    const freshToken = getAccessToken();
    if (freshToken === null || freshToken === usedToken) return response;

    const replay = replayInit(input, init, freshToken);
    if (replay === null) return response;

    return original(replay.input, replay.init);
  };
}

/** Never intercept the auth endpoints themselves (bad password must 401). */
function isRefreshable(input: RequestInfo | URL): boolean {
  const url = input instanceof Request ? input.url : String(input);

  return !/\/auth\/(login|company-login|refresh)$/.test(url);
}

function authorizationToken(input: RequestInfo | URL, init?: RequestInit): string | null {
  const headers = new Headers(
    init?.headers ?? (input instanceof Request ? input.headers : undefined),
  );
  const value = headers.get("Authorization");

  if (value === null) return null;

  return value.startsWith("Bearer ") ? value.slice("Bearer ".length) : value;
}

function replayInit(
  input: RequestInfo | URL,
  init: RequestInit | undefined,
  token: string,
): { input: RequestInfo | URL; init: RequestInit } | null {
  const headers = new Headers(
    init?.headers ?? (input instanceof Request ? input.headers : undefined),
  );
  headers.set("Authorization", `Bearer ${token}`);

  if (init) {
    // A streaming body can only be read once — orval sends JSON strings and
    // FormData, both replayable; streams skip the retry instead.
    if (init.body instanceof ReadableStream) return null;
    return { input, init: { ...init, headers } };
  }

  if (input instanceof Request && input.body !== null) return null;

  return { input, init: { headers } };
}

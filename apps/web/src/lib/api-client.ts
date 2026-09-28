import { authHeaders } from "@/lib/auth/token-store";

/**
 * Error thrown for any `{statusCode, message, error}` failure envelope.
 * Keeps the status and error code so callers can branch on the API contract
 * (e.g. `EPurchaseSessionExpired`) instead of matching message text.
 */
export class ApiError extends Error {
  readonly statusCode: number;
  readonly error?: string;

  constructor(statusCode: number, message: string, error?: string) {
    super(message);
    this.name = "ApiError";
    this.statusCode = statusCode;
    this.error = error;
  }
}

function assertOk(response: { status: number; data: unknown }): void {
  if (response.status >= 400) {
    const body = response.data as { message?: string; error?: string };
    throw new ApiError(
      response.status,
      body?.message ?? `Request failed (${response.status})`,
      body?.error,
    );
  }
}

/**
 * Unwraps the standard API envelope `{ data: ... }` and narrows on status.
 */
export function unwrap<T>(response: { status: number; data: unknown }): T {
  assertOk(response);
  return (response.data as { data: T }).data;
}

/**
 * Like `unwrap`, but also returns optional `meta` (e.g. pagination) from the envelope.
 */
export function unwrapWithMeta<T, M = unknown>(response: {
  status: number;
  data: unknown;
}): { data: T; meta?: M } {
  assertOk(response);
  return response.data as { data: T; meta?: M };
}

export function withAuth(options?: RequestInit): RequestInit {
  return { ...options, headers: { ...authHeaders(), ...options?.headers } };
}

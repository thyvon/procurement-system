import { authHeaders } from "@/lib/auth/token-store";

function assertOk(response: { status: number; data: unknown }): void {
  if (response.status >= 400) {
    const body = response.data as { message?: string };
    throw new Error(body?.message ?? `Request failed (${response.status})`);
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

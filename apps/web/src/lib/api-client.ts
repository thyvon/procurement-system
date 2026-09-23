import { authHeaders } from "@/lib/auth/token-store";

/**
 * Unwraps the standard API envelope `{ data: ... }` and narrows on status.
 */
export function unwrap<T>(response: { status: number; data: unknown }): T {
  if (response.status >= 400) {
    const body = response.data as { message?: string };
    throw new Error(body?.message ?? `Request failed (${response.status})`);
  }
  return (response.data as { data: T }).data;
}

export function withAuth(options?: RequestInit): RequestInit {
  return { ...options, headers: { ...authHeaders(), ...options?.headers } };
}

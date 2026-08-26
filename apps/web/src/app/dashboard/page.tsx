"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { authLogout, authMe } from "@/lib/api/auth/auth";
import type { UserResource } from "@/lib/api/model";
import {
  authHeaders,
  clearTokens,
  getAccessToken,
} from "@/lib/auth/token-store";

export default function DashboardPage() {
  const router = useRouter();

  const meQuery = useQuery({
    queryKey: ["me"],
    queryFn: () => authMe({ headers: authHeaders() }),
    enabled: typeof window !== "undefined",
  });

  const logoutMutation = useMutation({
    mutationFn: () => authLogout({ headers: authHeaders() }),
    onSettled: () => {
      clearTokens();
      router.push("/login");
    },
  });

  useEffect(() => {
    if (typeof window !== "undefined" && !getAccessToken()) {
      router.replace("/login");
    }
  }, [router]);

  useEffect(() => {
    if (meQuery.isError) {
      clearTokens();
      router.replace("/login");
    }
  }, [meQuery.isError, router]);

  const raw = meQuery.data;
  const user =
    raw?.status === 200 ? (raw.data as { data: UserResource }).data : null;

  return (
    <main className="min-h-screen bg-gray-50">
      <header className="border-b border-gray-200 bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
          <span className="text-lg font-semibold text-gray-900">
            Procurement
          </span>
          <div className="flex items-center gap-4">
            {user && (
              <span className="text-sm text-gray-600">{user.email}</span>
            )}
            <button
              onClick={() => logoutMutation.mutate()}
              disabled={logoutMutation.isPending}
              className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-100 disabled:opacity-60"
            >
              Sign out
            </button>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-6 py-10">
        <h1 className="text-xl font-semibold text-gray-900">Dashboard</h1>

        {meQuery.isPending && (
          <p className="mt-6 text-sm text-gray-500">Loading your account…</p>
        )}

        {user && (
          <dl className="mt-6 grid max-w-md grid-cols-[auto_1fr] gap-x-6 gap-y-2 rounded-xl border border-gray-200 bg-white p-6 text-sm shadow-sm">
            <dt className="font-medium text-gray-500">Name</dt>
            <dd className="text-gray-900">{user.name}</dd>
            <dt className="font-medium text-gray-500">Email</dt>
            <dd className="text-gray-900">{user.email}</dd>
            <dt className="font-medium text-gray-500">Entity ID</dt>
            <dd className="font-mono text-xs text-gray-700">
              {user.entityId ?? "—"}
            </dd>
            <dt className="font-medium text-gray-500">Status</dt>
            <dd>
              <span
                className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                  user.isActive
                    ? "bg-green-50 text-green-700"
                    : "bg-red-50 text-red-700"
                }`}
              >
                {user.isActive ? "Active" : "Inactive"}
              </span>
            </dd>
          </dl>
        )}

        <p className="mt-8 max-w-md text-xs leading-relaxed text-gray-400">
          Procurement modules (catalog, requisitions, approvals) will appear
          here as they are built. You are authenticated with a bearer access
          token; refresh tokens rotate automatically via the API.
        </p>
      </main>
    </main>
  );
}

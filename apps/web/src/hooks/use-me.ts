"use client";

import { useQuery } from "@tanstack/react-query";
import { authMe } from "@/lib/api/auth/auth";
import type { UserResource } from "@/lib/api/model";
import { unwrap, withAuth } from "@/lib/api-client";

export function useMe(options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: ["me"],
    queryFn: async () => unwrap<UserResource>(await authMe(withAuth())),
    staleTime: 5 * 60 * 1000,
    retry: false,
    ...options,
  });
}

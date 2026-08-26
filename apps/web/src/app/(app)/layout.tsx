"use client";

import { useQuery } from "@tanstack/react-query";
import { authMe } from "@/lib/api/auth/auth";
import { authHeaders } from "@/lib/auth/token-store";
import { AppSidebar } from "@/components/layout/app-sidebar";
import { Topbar } from "@/components/layout/topbar";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import { Skeleton } from "@/components/ui/skeleton";

export default function AppLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const meQuery = useQuery({
    queryKey: ["me"],
    queryFn: () => authMe({ headers: authHeaders() }),
    staleTime: 5 * 60 * 1000,
  });

  const user =
    meQuery.data?.status === 200
      ? (meQuery.data.data as { data: import("@/lib/api/model").UserResource })
          .data
      : null;

  return (
    <SidebarProvider>
      <AppSidebar />
      <SidebarInset>
        <Topbar user={user} />
        <div className="flex-1 p-6">
          {meQuery.isPending ? (
            <div className="space-y-3">
              <Skeleton className="h-6 w-40" />
              <Skeleton className="h-32 w-full max-w-md" />
            </div>
          ) : (
            children
          )}
        </div>
      </SidebarInset>
    </SidebarProvider>
  );
}

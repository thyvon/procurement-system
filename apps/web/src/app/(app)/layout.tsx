"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { authMe } from "@/lib/api/auth/auth";
import { authHeaders, getAccessToken } from "@/lib/auth/token-store";
import { AppSidebar } from "@/components/layout/app-sidebar";
import { Topbar } from "@/components/layout/topbar";
import { BreadcrumbBar } from "@/components/layout/breadcrumb-bar";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import { TooltipProvider } from "@/components/ui/tooltip";
import { Toaster } from "@/components/ui/toast";

export default function AppLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();
  const [hasToken] = useState<boolean | null>(() =>
    typeof window === "undefined" ? null : !!getAccessToken(),
  );

  useEffect(() => {
    if (hasToken === false) {
      router.replace("/login");
    }
  }, [hasToken, router]);

  const meQuery = useQuery({
    queryKey: ["me"],
    queryFn: () => authMe({ headers: authHeaders() }),
    staleTime: 5 * 60 * 1000,
    retry: false,
    enabled: hasToken === true,
  });

  const user =
    meQuery.data?.status === 200
      ? (meQuery.data.data as { data: import("@/lib/api/model").UserResource })
          .data
      : null;

  useEffect(() => {
    if (meQuery.isError || (meQuery.isFetched && !user)) {
      router.replace("/login");
    }
  }, [meQuery.isError, meQuery.isFetched, user, router]);

  if (!hasToken || meQuery.isPending || !user) {
    return null;
  }

  return (
    <TooltipProvider>
      <SidebarProvider>
        <AppSidebar />
        <SidebarInset>
          <Topbar user={user} />
          <BreadcrumbBar />
          <div className="flex-1 p-6">{children}</div>
          <footer className="border-t px-6 py-3 text-center text-xs text-muted-foreground">
            &copy; {new Date().getFullYear()} Procurement System. All rights reserved.
          </footer>
        </SidebarInset>
        <Toaster />
      </SidebarProvider>
    </TooltipProvider>
  );
}

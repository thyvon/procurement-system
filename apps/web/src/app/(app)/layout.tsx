"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { getAccessToken } from "@/lib/auth/token-store";
import { useMe } from "@/hooks/use-me";
import { AppSidebar } from "@/components/layout/app-sidebar";
import { Topbar } from "@/components/layout/topbar";
import { BreadcrumbBar } from "@/components/layout/breadcrumb-bar";
import { AppPreloader } from "@/components/layout/app-preloader";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import { TooltipProvider } from "@/components/ui/tooltip";

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

  const meQuery = useMe({ enabled: hasToken === true });

  const user = meQuery.data ?? null;

  useEffect(() => {
    if (meQuery.isError || (meQuery.isFetched && !user)) {
      router.replace("/login");
    }
  }, [meQuery.isError, meQuery.isFetched, user, router]);

  // SSR renders with hasToken === null while the client may already know
  // hasToken === false — both must produce the same tree (the preloader),
  // otherwise hydration fails and the redirect effect never runs.
  if (hasToken === null || !user) {
    return <AppPreloader />;
  }

  return (
    <TooltipProvider>
      <SidebarProvider>
        <AppSidebar />
        <SidebarInset>
          <Topbar user={user} />
          <BreadcrumbBar />
          <div className="min-w-0 flex-1 p-6 print:p-0">{children}</div>
          <footer className="border-t px-6 py-3 text-center text-xs text-muted-foreground print:hidden">
            &copy; {new Date().getFullYear()} Procurement System. All rights reserved.
          </footer>
        </SidebarInset>
      </SidebarProvider>
    </TooltipProvider>
  );
}

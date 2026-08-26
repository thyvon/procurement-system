"use client";

import { useTranslations } from "next-intl";
import { usePathname } from "next/navigation";
import { Bell } from "lucide-react";
import { SidebarTrigger } from "@/components/ui/sidebar";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
} from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { ThemeToggle } from "@/components/layout/theme-toggle";
import { LocaleSwitch } from "@/components/layout/locale-switch";
import { UserMenu } from "@/components/layout/user-menu";
import type { UserResource } from "@/lib/api/model";

type Props = {
  user: UserResource | null;
};

const NAV_KEYS = [
  "dashboard",
  "catalog",
  "suppliers",
  "requisitions",
  "approvals",
  "reports",
  "settings",
] as const;

function useBreadcrumbLabel(segment: string): string | null {
  const tNav = useTranslations("nav");
  const key = NAV_KEYS.find((k) => k === segment);
  return key ? tNav(key) : null;
}

function CurrentBreadcrumb() {
  const pathname = usePathname();
  const segment = pathname.split("/").filter(Boolean).at(-1) ?? "";
  const label = useBreadcrumbLabel(segment);

  return (
    <Breadcrumb>
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbPage>{label ?? segment}</BreadcrumbPage>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>
  );
}

export function Topbar({ user }: Props) {
  return (
    <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-3 border-b bg-background px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
      <SidebarTrigger />
      <Separator orientation="vertical" className="h-5" />

      <div className="flex flex-1 items-center">
        <CurrentBreadcrumb />
      </div>

      <div className="flex items-center gap-1.5">
        <LocaleSwitch />
        <ThemeToggle />
        <Button variant="ghost" size="icon" disabled aria-label="Notifications">
          <Bell className="h-4 w-4" />
        </Button>
        <UserMenu user={user} />
      </div>
    </header>
  );
}

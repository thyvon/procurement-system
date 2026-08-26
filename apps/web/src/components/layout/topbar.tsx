"use client";

import { useTranslations } from "next-intl";
import { Bell } from "lucide-react";
import { SidebarTrigger } from "@/components/ui/sidebar";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { ThemeToggle } from "@/components/layout/theme-toggle";
import { LocaleSwitch } from "@/components/layout/locale-switch";
import { UserMenu } from "@/components/layout/user-menu";
import type { UserResource } from "@/lib/api/model";

type Props = {
  user: UserResource | null;
};

export function Topbar({ user }: Props) {
  const t = useTranslations("common");

  return (
    <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-3 border-b bg-background px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
      <SidebarTrigger />
      <Separator orientation="vertical" className="h-5" />

      {/* Breadcrumb slot — pages render their own trail here later */}
      <div className="flex-1 text-sm text-muted-foreground">
        {t("loading")}
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

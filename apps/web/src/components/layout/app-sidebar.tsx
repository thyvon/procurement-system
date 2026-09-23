"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  Boxes,
  ClipboardList,
  FileCheck2,
  LayoutDashboard,
  Settings,
  Store,
  BarChart3,
  Users,
} from "lucide-react";
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
} from "@/components/ui/sidebar";

type NavItem = {
  key: string;
  href: string;
  icon: React.ComponentType<{ className?: string }>;
  enabled: boolean;
};

const navItems: NavItem[] = [
  { key: "dashboard", href: "/", icon: LayoutDashboard, enabled: true },
  { key: "products", href: "/products", icon: Boxes, enabled: true },
  { key: "users", href: "/users", icon: Users, enabled: true },
  { key: "suppliers", href: "#", icon: Store, enabled: false },
  { key: "requisitions", href: "#", icon: ClipboardList, enabled: false },
  { key: "approvals", href: "#", icon: FileCheck2, enabled: false },
  { key: "reports", href: "#", icon: BarChart3, enabled: false },
  { key: "settings", href: "#", icon: Settings, enabled: false },
];

export function AppSidebar() {
  const t = useTranslations("nav");
  const tCommon = useTranslations("common");
  const pathname = usePathname();

  return (
    <Sidebar collapsible="icon">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              size="lg"
              tooltip="Procurement"
              render={<Link href="/" />}
              className="group-data-[collapsible=icon]:size-8! group-data-[collapsible=icon]:p-2!"
            >
              <div className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground group-data-[collapsible=icon]:rounded-none group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:text-sidebar-foreground">
                <ClipboardList className="size-4" />
              </div>
              <span className="group-data-[collapsible=icon]:hidden">Procurement</span>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupContent>
            <SidebarMenu>
              {navItems.map((item) => (
                <SidebarMenuItem key={item.key}>
                  {item.enabled ? (
                    <SidebarMenuButton
                      render={<Link href={item.href} />}
                      isActive={item.href === "/" ? pathname === "/" : pathname.startsWith(item.href)}
                      tooltip={t(item.key)}
                    >
                      <item.icon />
                      <span>{t(item.key)}</span>
                    </SidebarMenuButton>
                  ) : (
                    <SidebarMenuButton
                      disabled
                      tooltip={`${t(item.key)} — ${tCommon("soon")}`}
                    >
                      <item.icon />
                      <span>{t(item.key)}</span>
                    </SidebarMenuButton>
                  )}
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarRail />
    </Sidebar>
  );
}


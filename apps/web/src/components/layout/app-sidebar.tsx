"use client";

import * as React from "react";
import { useQuery } from "@tanstack/react-query";
import { Collapsible } from "@base-ui/react/collapsible";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  BarChart3,
  Boxes,
  ChevronRight,
  ClipboardList,
  FileCheck2,
  FileText,
  LayoutDashboard,
  Settings,
  ShoppingBag,
  Store,
  Users,
} from "lucide-react";
import { approvalsInboxCount } from "@/lib/api/approval-request/approval-request";
import { unwrap, withAuth } from "@/lib/api-client";
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  SidebarRail,
  useSidebar,
} from "@/components/ui/sidebar";

type NavChild = {
  key: string;
  href: string;
  enabled: boolean;
};

type NavItem = {
  key: string;
  href?: string;
  icon: React.ComponentType<{ className?: string }>;
  enabled: boolean;
  children?: NavChild[];
};

const navItems: NavItem[] = [
  { key: "dashboard", href: "/", icon: LayoutDashboard, enabled: true },
  {
    key: "products",
    icon: Boxes,
    enabled: true,
    children: [
      { key: "products", href: "/products", enabled: true },
      { key: "categories", href: "/products/categories", enabled: true },
      { key: "brands", href: "/products/brands", enabled: true },
      { key: "groups", href: "/products/groups", enabled: true },
      { key: "uoms", href: "/products/uoms", enabled: true },
      {
        key: "variationTemplates",
        href: "/products/variation-templates",
        enabled: true,
      },
    ],
  },
  {
    key: "epurchase",
    icon: ShoppingBag,
    enabled: true,
    children: [
      { key: "epurchaseItems", href: "/epurchase/items", enabled: true },
      { key: "epurchaseSuppliers", href: "/epurchase/suppliers", enabled: true },
    ],
  },
  {
    key: "purchaseOrders",
    icon: FileText,
    enabled: true,
    children: [
      { key: "purchaseOrdersList", href: "/purchase-orders/list", enabled: true },
      { key: "evaluations", href: "/purchase-orders/evaluations", enabled: true },
    ],
  },
  {
    key: "users",
    icon: Users,
    enabled: true,
    children: [
      { key: "users", href: "/users", enabled: true },
      { key: "roles", href: "/users/roles", enabled: true },
    ],
  },
  { key: "suppliers", href: "#", icon: Store, enabled: false },
  { key: "requisitions", href: "#", icon: ClipboardList, enabled: false },
  { key: "approvals", href: "/approvals", icon: FileCheck2, enabled: true },
  { key: "reports", href: "#", icon: BarChart3, enabled: false },
  { key: "settings", href: "#", icon: Settings, enabled: false },
];

function useApprovalsInboxCount(): number | null {
  const query = useQuery({
    queryKey: ["approvals", "inbox", "count"],
    queryFn: async () =>
      unwrap<{ count: number }>(
        await approvalsInboxCount(undefined, withAuth())
      ),
    staleTime: 30_000,
  });

  return query.data?.count ?? null;
}

function isPathActive(href: string, pathname: string) {
  return href === "/" ? pathname === "/" : pathname.startsWith(href);
}

/**
 * The most specific matching child wins: `/products/categories` highlights
 * only "Categories", while `/products/create` still lights up "Products".
 */
function findActiveChild(children: NavChild[] | undefined, pathname: string) {
  return (children ?? [])
    .filter((child) => child.enabled && isPathActive(child.href, pathname))
    .reduce<NavChild | undefined>(
      (best, child) =>
        !best || child.href.length > best.href.length ? child : best,
      undefined
    );
}

function NavGroup({ item }: { item: NavItem }) {
  const t = useTranslations("nav");
  const pathname = usePathname();
  const { state, toggleSidebar } = useSidebar();
  const activeChild = findActiveChild(item.children, pathname);
  const active = item.enabled && activeChild !== undefined;
  const [open, setOpen] = React.useState(active);
  const [wasActive, setWasActive] = React.useState(active);

  if (active !== wasActive) {
    setWasActive(active);
    if (active) setOpen(true);
  }

  const handleOpenChange = (nextOpen: boolean) => {
    if (state === "collapsed") {
      setOpen(true);
      toggleSidebar();
      return;
    }
    setOpen(nextOpen);
  };

  if (!item.enabled) {
    return (
      <SidebarMenuItem>
        <SidebarMenuButton disabled tooltip={t(item.key)}>
          <item.icon />
          <span>{t(item.key)}</span>
        </SidebarMenuButton>
      </SidebarMenuItem>
    );
  }

  return (
    <SidebarMenuItem>
      <Collapsible.Root open={open} onOpenChange={handleOpenChange}>
        <Collapsible.Trigger
          render={<SidebarMenuButton isActive={active} tooltip={t(item.key)} />}
        >
          <item.icon />
          <span>{t(item.key)}</span>
          <ChevronRight
            className={
              open
                ? "ml-auto size-4 shrink-0 rotate-90 transition-transform duration-200"
                : "ml-auto size-4 shrink-0 transition-transform duration-200"
            }
          />
        </Collapsible.Trigger>
        <Collapsible.Panel>
          <SidebarMenuSub>
            {(item.children ?? []).map((child) =>
              child.enabled ? (
                <SidebarMenuSubItem key={child.key}>
                  <SidebarMenuSubButton
                    render={<Link href={child.href} />}
                    isActive={activeChild?.href === child.href}
                  >
                    <span>{t(child.key)}</span>
                  </SidebarMenuSubButton>
                </SidebarMenuSubItem>
              ) : (
                <SidebarMenuSubItem key={child.key}>
                  <SidebarMenuSubButton
                    render={<button type="button" disabled />}
                  >
                    <span>{t(child.key)}</span>
                  </SidebarMenuSubButton>
                </SidebarMenuSubItem>
              )
            )}
          </SidebarMenuSub>
        </Collapsible.Panel>
      </Collapsible.Root>
    </SidebarMenuItem>
  );
}

function NavLinkItem({
  item,
  badge,
}: {
  item: NavItem;
  badge?: number | null;
}) {
  const t = useTranslations("nav");
  const tCommon = useTranslations("common");
  const pathname = usePathname();
  const href = item.href ?? "#";
  const active = item.enabled && isPathActive(href, pathname);

  if (!item.enabled) {
    return (
      <SidebarMenuItem>
        <SidebarMenuButton disabled tooltip={`${t(item.key)} — ${tCommon("soon")}`}>
          <item.icon />
          <span>{t(item.key)}</span>
        </SidebarMenuButton>
      </SidebarMenuItem>
    );
  }

  return (
    <SidebarMenuItem>
      <SidebarMenuButton
        render={<Link href={href} />}
        isActive={active}
        tooltip={t(item.key)}
      >
        <item.icon />
        <span>{t(item.key)}</span>
        {badge != null && badge > 0 ? (
          <span className="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-medium text-primary-foreground group-data-[collapsible=icon]:hidden">
            {badge > 99 ? "99+" : badge}
          </span>
        ) : null}
      </SidebarMenuButton>
    </SidebarMenuItem>
  );
}

export function AppSidebar() {
  const approvalsCount = useApprovalsInboxCount();

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
              {navItems.map((item) =>
                item.children ? (
                  <NavGroup key={item.key} item={item} />
                ) : (
                  <NavLinkItem
                    key={item.key}
                    item={item}
                    badge={item.key === "approvals" ? approvalsCount : null}
                  />
                )
              )}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarRail />
    </Sidebar>
  );
}

"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { Bell, CheckCheck } from "lucide-react";
import {
  notificationsIndex,
  notificationsRead,
  notificationsReadAll,
  notificationsUnreadCount,
} from "@/lib/api/notification/notification";
import type { NotificationResource } from "@/lib/api/model/notificationResource";
import { unwrap, withAuth } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

type NotificationData = {
  type?: string;
  requestId?: string;
  documentCode?: string | null;
  stepLabel?: string;
  status?: string;
};

const QUERIES = {
  list: ["notifications"],
  unread: ["notifications", "unread-count"],
} as const;

function useUnreadCount() {
  return useQuery({
    queryKey: QUERIES.unread,
    queryFn: async () =>
      unwrap<{ count: number }>(await notificationsUnreadCount(withAuth())),
    staleTime: 30_000,
  });
}

export function NotificationsMenu() {
  const t = useTranslations("notifications");
  const router = useRouter();
  const qc = useQueryClient();

  const unreadQuery = useUnreadCount();
  const listQuery = useQuery({
    queryKey: QUERIES.list,
    queryFn: async () =>
      unwrap<NotificationResource[]>(await notificationsIndex(withAuth())),
  });

  const readMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(await notificationsRead(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: QUERIES.unread });
      qc.invalidateQueries({ queryKey: QUERIES.list });
    },
  });

  const readAllMutation = useMutation({
    mutationFn: async () =>
      unwrap(await notificationsReadAll(withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: QUERIES.unread });
      qc.invalidateQueries({ queryKey: QUERIES.list });
    },
  });

  const unread = unreadQuery.data?.count ?? 0;
  const notifications = listQuery.data ?? [];

  const labelFor = (notification: NotificationResource): string => {
    const data = notification.data as NotificationData;
    const code = data.documentCode ?? "";

    if (data.type === "approval.action_required") {
      return t("actionRequired", { step: data.stepLabel ?? "", code });
    }
    if (data.type === "approval.result") {
      if (data.status === "approved") return t("approved", { code });
      if (data.status === "rejected") return t("rejected", { code });
      if (data.status === "returned") return t("returned", { code });
    }
    return data.type ?? t("title");
  };

  const handleOpen = (notification: NotificationResource) => {
    const data = notification.data as NotificationData;

    if (!notification.readAt) {
      readMutation.mutate(notification.id);
    }
    if (data.requestId) {
      router.push(`/approvals/${data.requestId}`);
    }
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={
          <Button
            variant="ghost"
            size="icon"
            aria-label={t("title")}
            className="relative"
          />
        }
      >
        <Bell className="h-4 w-4" />
        {unread > 0 ? (
          <span className="absolute right-1 top-1 inline-flex min-w-4 items-center justify-center rounded-full bg-primary px-1 py-px text-[10px] leading-none font-medium text-primary-foreground">
            {unread > 9 ? "9+" : unread}
          </span>
        ) : null}
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80">
        <DropdownMenuGroup>
          <DropdownMenuLabel className="flex items-center justify-between">
            <span>{t("title")}</span>
            {unread > 0 ? (
              <span className="text-xs font-normal text-muted-foreground">
                {t("unread", { count: unread })}
              </span>
            ) : null}
          </DropdownMenuLabel>
        </DropdownMenuGroup>
        <DropdownMenuSeparator />

        {notifications.length === 0 ? (
          <div className="px-2 py-6 text-center text-sm text-muted-foreground">
            {t("empty")}
          </div>
        ) : (
          notifications.map((notification) => (
            <DropdownMenuItem
              key={notification.id}
              onClick={() => handleOpen(notification)}
              className="items-start"
            >
              <span className="flex flex-col gap-0.5">
                <span
                  className={
                    notification.readAt
                      ? "text-sm"
                      : "text-sm font-medium"
                  }
                >
                  {labelFor(notification)}
                </span>
                <span className="text-xs text-muted-foreground">
                  {notification.createdAt}
                </span>
              </span>
            </DropdownMenuItem>
          ))
        )}

        {unread > 0 ? (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              onClick={() => readAllMutation.mutate()}
              disabled={readAllMutation.isPending}
            >
              <CheckCheck className="mr-2 size-4" />
              {t("markAllRead")}
            </DropdownMenuItem>
          </>
        ) : null}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocale, useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { format, formatDistanceToNow, isValid, parseISO } from "date-fns";
import { enUS, km } from "date-fns/locale";
import {
  Bell,
  BellOff,
  CheckCheck,
  CheckCircle2,
  ClipboardCheck,
  Undo2,
  X,
  XCircle,
  type LucideIcon,
} from "lucide-react";
import {
  notificationsIndex,
  notificationsRead,
  notificationsReadAll,
  notificationsUnreadCount,
} from "@/lib/api/notification/notification";
import type { NotificationResource } from "@/lib/api/model/notificationResource";
import { unwrap, withAuth } from "@/lib/api-client";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";

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

type Filter = "all" | "unread";

type Tone = "primary" | "destructive" | "muted";

const TONE_CLASS: Record<Tone, string> = {
  primary: "bg-primary/10 text-primary",
  destructive: "bg-destructive/10 text-destructive",
  muted: "bg-muted text-muted-foreground",
};

function appearanceFor(data: NotificationData): {
  icon: LucideIcon;
  tone: Tone;
} {
  if (data.type === "approval.action_required") {
    return { icon: ClipboardCheck, tone: "primary" };
  }
  if (data.type === "approval.result") {
    if (data.status === "approved") {
      return { icon: CheckCircle2, tone: "primary" };
    }
    if (data.status === "rejected") {
      return { icon: XCircle, tone: "destructive" };
    }
    if (data.status === "returned") {
      return { icon: Undo2, tone: "muted" };
    }
    if (data.status === "cancelled") {
      return { icon: Undo2, tone: "muted" };
    }
  }
  return { icon: Bell, tone: "muted" };
}

export function NotificationsDrawer() {
  const t = useTranslations("notifications");
  const locale = useLocale();
  const router = useRouter();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [filter, setFilter] = useState<Filter>("all");

  const dateLocale = locale === "km" ? km : enUS;

  const unreadQuery = useQuery({
    queryKey: QUERIES.unread,
    queryFn: async () =>
      unwrap<{ count: number }>(await notificationsUnreadCount(withAuth())),
    staleTime: 30_000,
    refetchInterval: 60_000,
  });

  const listQuery = useQuery({
    queryKey: QUERIES.list,
    queryFn: async () =>
      unwrap<NotificationResource[]>(await notificationsIndex(withAuth())),
    enabled: open,
    staleTime: 15_000,
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
    mutationFn: async () => unwrap(await notificationsReadAll(withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: QUERIES.unread });
      qc.invalidateQueries({ queryKey: QUERIES.list });
    },
  });

  const unread = unreadQuery.data?.count ?? 0;
  const notifications = listQuery.data ?? [];
  const visible =
    filter === "unread"
      ? notifications.filter((notification) => !notification.readAt)
      : notifications;

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
      if (data.status === "cancelled") return t("cancelled", { code });
    }
    return data.type ?? t("title");
  };

  const formatTime = (
    value?: string | null
  ): { relative: string; exact: string } | null => {
    if (!value) return null;

    const date = parseISO(value);
    if (!isValid(date)) return null;

    return {
      relative: formatDistanceToNow(date, {
        addSuffix: true,
        locale: dateLocale,
      }),
      exact: format(date, "PPpp", { locale: dateLocale }),
    };
  };

  const handleOpen = (notification: NotificationResource) => {
    const data = notification.data as NotificationData;

    if (!notification.readAt) {
      readMutation.mutate(notification.id);
    }
    if (data.requestId) {
      setOpen(false);
      router.push(`/approvals/${data.requestId}`);
    }
  };

  return (
    <Sheet open={open} onOpenChange={setOpen}>
      <SheetTrigger
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
      </SheetTrigger>

      <SheetContent
        className="gap-0 overflow-hidden p-0 sm:max-w-[26rem]"
        showCloseButton={false}
      >
        <SheetHeader className="gap-2.5 border-b">
          <div className="flex items-center gap-2">
            <SheetTitle className="min-w-0 flex-1">{t("title")}</SheetTitle>
            <SheetClose
              render={
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t("close")}
                />
              }
            >
              <X className="h-4 w-4" />
            </SheetClose>
          </div>
          <div className="flex items-center justify-between gap-3">
            <SheetDescription className="min-w-0">
              {unread > 0 ? t("unread", { count: unread }) : t("allRead")}
            </SheetDescription>
            {unread > 0 ? (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => readAllMutation.mutate()}
                disabled={readAllMutation.isPending}
                className="shrink-0 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
              >
                <CheckCheck className="h-3.5 w-3.5" />
                {t("markAllRead")}
              </Button>
            ) : null}
          </div>
        </SheetHeader>

        <div className="border-b px-4 py-2.5">
          <div className="flex items-center gap-0.5 rounded-lg bg-muted p-0.5">
            {(["all", "unread"] as const).map((value) => (
              <button
                key={value}
                type="button"
                onClick={() => setFilter(value)}
                aria-pressed={filter === value}
                className={cn(
                  "flex-1 rounded-md px-2.5 py-1 text-xs font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring",
                  filter === value
                    ? "bg-background text-foreground shadow-sm"
                    : "text-muted-foreground hover:text-foreground"
                )}
              >
                {value === "all" ? t("filterAll") : t("filterUnread")}
                <span className="ml-1 font-normal text-muted-foreground">
                  {value === "all" ? notifications.length : unread}
                </span>
              </button>
            ))}
          </div>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain">
          {listQuery.isPending ? (
            <div className="flex flex-col gap-4 p-4">
              {[0, 1, 2].map((index) => (
                <div key={index} className="flex items-start gap-3">
                  <Skeleton className="size-8 shrink-0 rounded-full" />
                  <div className="flex flex-1 flex-col gap-2 pt-1">
                    <Skeleton className="h-3.5 w-3/4" />
                    <Skeleton className="h-3 w-1/3" />
                  </div>
                </div>
              ))}
            </div>
          ) : listQuery.isError ? (
            <div className="flex min-h-48 items-center justify-center px-6 py-12 text-center text-sm text-destructive">
              {listQuery.error.message || t("loadFailed")}
            </div>
          ) : visible.length === 0 ? (
            <div className="flex min-h-48 flex-col items-center justify-center gap-2 px-6 py-12 text-center">
              <span className="flex size-10 items-center justify-center rounded-full bg-muted">
                {filter === "unread" ? (
                  <CheckCheck className="h-5 w-5 text-muted-foreground" />
                ) : (
                  <BellOff className="h-5 w-5 text-muted-foreground" />
                )}
              </span>
              <p className="text-sm font-medium">
                {filter === "unread" ? t("emptyUnread") : t("empty")}
              </p>
              <p className="text-xs text-muted-foreground">{t("emptyHint")}</p>
            </div>
          ) : (
            <ul className="divide-y divide-border/60">
              {visible.map((notification) => {
                const data = notification.data as NotificationData;
                const { icon: Icon, tone } = appearanceFor(data);
                const time = formatTime(notification.createdAt);

                return (
                  <li key={notification.id} className="group relative">
                    <button
                      type="button"
                      onClick={() => handleOpen(notification)}
                      className={cn(
                        "flex w-full items-start gap-3 px-4 py-3 text-left transition-colors outline-none hover:bg-muted/60 focus-visible:bg-muted/60",
                        !notification.readAt && "bg-muted/40"
                      )}
                    >
                      <span
                        className={cn(
                          "flex size-8 shrink-0 items-center justify-center rounded-full",
                          TONE_CLASS[tone]
                        )}
                      >
                        <Icon className="h-4 w-4" />
                      </span>
                      <span className="flex min-w-0 flex-1 flex-col gap-1 pr-5">
                        <span
                          className={cn(
                            "text-sm leading-snug",
                            notification.readAt
                              ? "text-foreground/90"
                              : "font-medium"
                          )}
                        >
                          {labelFor(notification)}
                        </span>
                        {time ? (
                          <span
                            className="text-xs text-muted-foreground"
                            title={time.exact}
                          >
                            {time.relative}
                          </span>
                        ) : null}
                      </span>
                      {!notification.readAt ? (
                        <span
                          className="absolute right-4 top-1/2 size-2 -translate-y-1/2 rounded-full bg-primary"
                          aria-hidden="true"
                        />
                      ) : null}
                    </button>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}

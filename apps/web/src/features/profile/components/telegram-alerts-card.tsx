"use client";

import { useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { Check, Copy, ExternalLink } from "lucide-react";
import { toast } from "sonner";
import { telegramLink } from "@/lib/api/telegram-link/telegram-link";
import type { TelegramLink200Data, UserResource } from "@/lib/api/model";
import { unwrap, withAuth } from "@/lib/api-client";
import { buttonVariants, Button } from "@/components/ui/button";
import { cn } from "cn";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

type Props = {
  user: UserResource;
};

/**
 * Personal /start deep link: issues a short-lived single-use code and hands
 * the t.me URL to the user, who redeems it in the bot chat to bind
 * users.telegram_chat_id. Every call issues a fresh code, so "New link"
 * simply replaces the displayed one. Errors surface through the global
 * MutationCache toast.
 */
export function TelegramAlertsCard({ user }: Props) {
  const t = useTranslations("profile");
  const [link, setLink] = useState<TelegramLink200Data | null>(null);
  const linked = user.telegramChatId != null;

  const issueMutation = useMutation({
    mutationFn: async () =>
      unwrap<TelegramLink200Data>(await telegramLink(withAuth())),
    onSuccess: setLink,
  });

  const handleCopy = async () => {
    if (!link) return;
    await navigator.clipboard.writeText(link.url);
    toast.success(t("telegramCopied"));
  };

  return (
    <Card size="sm">
      <CardHeader>
        <CardTitle>{t("sectionTelegram")}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        <p className="text-xs text-muted-foreground">{t("telegramHint")}</p>
        <div className="flex items-center gap-3">
          <div className="flex size-10 shrink-0 items-center justify-center rounded-md border border-border">
            {linked ? (
              <Check className="size-4 text-primary" />
            ) : (
              <span className="text-xs text-muted-foreground">—</span>
            )}
          </div>
          <div className="min-w-0 flex-1">
            <p className="text-sm font-medium">
              {linked ? t("telegramLinked") : t("telegramNotLinked")}
            </p>
            <p className="truncate text-xs text-muted-foreground">
              {link
                ? t("telegramLinkExpiry", {
                    time: new Date(link.expiresAt).toLocaleString(),
                  })
                : t("telegramConnectStep")}
            </p>
          </div>
          {link ? (
            <>
              <Button
                type="button"
                variant="outline"
                size="icon"
                className="shrink-0"
                onClick={handleCopy}
              >
                <Copy className="size-4" />
                <span className="sr-only">{t("telegramCopy")}</span>
              </Button>
              <a
                href={link.url}
                target="_blank"
                rel="noreferrer"
                className={cn(buttonVariants({ variant: "outline", size: "icon" }))}
              >
                <ExternalLink className="size-4" />
                <span className="sr-only">{t("telegramOpen")}</span>
              </a>
              <Button
                type="button"
                variant="outline"
                className="shrink-0"
                disabled={issueMutation.isPending}
                onClick={() => issueMutation.mutate()}
              >
                {t("telegramRegenerate")}
              </Button>
            </>
          ) : (
            <Button
              type="button"
              variant="outline"
              className="shrink-0"
              disabled={issueMutation.isPending}
              onClick={() => issueMutation.mutate()}
            >
              {t("telegramGenerate")}
            </Button>
          )}
        </div>
      </CardContent>
    </Card>
  );
}

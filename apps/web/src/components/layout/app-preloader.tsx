"use client";

import { useTranslations } from "next-intl";
import { ClipboardList, Loader2 } from "lucide-react";

export function AppPreloader() {
  const t = useTranslations("common");

  return (
    <div className="flex min-h-svh flex-col items-center justify-center gap-4">
      <div className="flex size-12 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-lg shadow-primary/25">
        <ClipboardList className="size-6" />
      </div>
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <Loader2 className="size-4 animate-spin" />
        <span>{t("loading")}</span>
      </div>
    </div>
  );
}

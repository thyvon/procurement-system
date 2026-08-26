"use client";

import { useLocale, useTranslations } from "next-intl";
import { Languages } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { LOCALE_COOKIE, locales, type Locale } from "@/i18n/config";
import { useTransition } from "react";

const localeLabels: Record<Locale, string> = {
  en: "English",
  km: "ខ្មែរ",
};

export function LocaleSwitch() {
  const t = useTranslations("topbar");
  const locale = useLocale();
  const [isPending, startTransition] = useTransition();

  const changeLocale = (next: Locale) => {
    if (next === locale) return;
    startTransition(() => {
      document.cookie = `${LOCALE_COOKIE}=${next}; path=/; max-age=31536000; samesite=lax`;
      window.location.reload();
    });
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={
          <Button
            variant="ghost"
            size="sm"
            disabled={isPending}
            aria-label={t("language")}
            className="gap-1.5"
          />
        }
      >
        <Languages className="h-4 w-4" />
        <span className="text-xs font-medium uppercase">{locale}</span>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {locales.map((loc) => (
          <DropdownMenuItem
            key={loc}
            onClick={() => changeLocale(loc)}
            data-active={loc === locale}
            className={loc === locale ? "bg-accent" : ""}
          >
            {localeLabels[loc]}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

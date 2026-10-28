"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { BrandingTab } from "./components/branding-tab";
import { TelegramTab } from "./components/telegram-tab";

type SettingsTab = "branding" | "telegram";

export function SettingsPage() {
  const t = useTranslations("settings");
  const [tab, setTab] = useState<SettingsTab>("branding");

  return (
    <div className="mt-1 min-w-0 space-y-6">
      <div>
        <h1 className="text-xl font-semibold tracking-tight">{t("title")}</h1>
        <p className="text-sm text-muted-foreground">{t("description")}</p>
      </div>

      <Tabs
        value={tab}
        onValueChange={(value) => setTab(value as SettingsTab)}
      >
        <TabsList>
          <TabsTrigger value="branding">{t("tabs.branding")}</TabsTrigger>
          <TabsTrigger value="telegram">{t("tabs.telegram")}</TabsTrigger>
        </TabsList>
      </Tabs>

      {tab === "branding" ? <BrandingTab /> : <TelegramTab />}
    </div>
  );
}

"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { ArrowLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { FlowsTab } from "./components/flows-tab";
import { AuthorityTab } from "./components/authority-tab";

type SettingsTab = "flows" | "authority";

export function ApprovalSettingsPage() {
  const t = useTranslations("approvals.settings");
  const router = useRouter();
  const [tab, setTab] = useState<SettingsTab>("flows");

  return (
    <div className="mt-1 min-w-0 space-y-6">
      <div className="flex items-center gap-3">
        <Button
          variant="outline"
          size="icon"
          onClick={() => router.push("/approvals")}
          aria-label={t("back")}
        >
          <ArrowLeft />
        </Button>
        <div>
          <h1 className="text-xl font-semibold tracking-tight">{t("title")}</h1>
          <p className="text-sm text-muted-foreground">{t("description")}</p>
        </div>
      </div>

      <Tabs
        value={tab}
        onValueChange={(value) => setTab(value as SettingsTab)}
      >
        <TabsList>
          <TabsTrigger value="flows">{t("tabs.flows")}</TabsTrigger>
          <TabsTrigger value="authority">{t("tabs.authority")}</TabsTrigger>
        </TabsList>
      </Tabs>

      {tab === "flows" ? <FlowsTab /> : <AuthorityTab />}
    </div>
  );
}

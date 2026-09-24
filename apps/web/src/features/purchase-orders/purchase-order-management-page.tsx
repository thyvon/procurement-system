"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { PurchaseOrdersTab } from "./purchase-orders-tab";
import { EvaluationsTab } from "./evaluations-tab";

export function PurchaseOrderManagementPage() {
  const t = useTranslations("nav");
  const [tab, setTab] = useState<string>("orders");

  return (
    <div className="min-w-0">
      <Tabs value={tab} onValueChange={setTab} className="mt-1 min-w-0">
        <TabsList>
          <TabsTrigger value="orders">{t("purchaseOrders")}</TabsTrigger>
          <TabsTrigger value="evaluations">{t("evaluations")}</TabsTrigger>
        </TabsList>

        <TabsContent value="orders">
          <PurchaseOrdersTab />
        </TabsContent>
        <TabsContent value="evaluations">
          <EvaluationsTab />
        </TabsContent>
      </Tabs>
    </div>
  );
}

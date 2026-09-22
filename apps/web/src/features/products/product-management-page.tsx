"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { LookupTab } from "./lookup-tab";
import { ProductsTab } from "./products-tab";

export function ProductManagementPage() {
  const t = useTranslations("nav");
  const [tab, setTab] = useState<string>("products");

  return (
    <div>
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">{t("products")}</h1>
      </div>

      <Tabs value={tab} onValueChange={setTab} className="mt-4">
        <TabsList>
          <TabsTrigger value="products">{t("products")}</TabsTrigger>
          <TabsTrigger value="categories">{t("categories")}</TabsTrigger>
          <TabsTrigger value="brands">{t("brands")}</TabsTrigger>
          <TabsTrigger value="groups">{t("groups")}</TabsTrigger>
          <TabsTrigger value="uoms">{t("uoms")}</TabsTrigger>
        </TabsList>

        <TabsContent value="products">
          <ProductsTab />
        </TabsContent>
        <TabsContent value="categories">
          <LookupTab kind="categories" />
        </TabsContent>
        <TabsContent value="brands">
          <LookupTab kind="brands" />
        </TabsContent>
        <TabsContent value="groups">
          <LookupTab kind="groups" />
        </TabsContent>
        <TabsContent value="uoms">
          <LookupTab kind="uoms" />
        </TabsContent>
      </Tabs>
    </div>
  );
}


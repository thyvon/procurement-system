"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { LookupTab } from "./lookup-tab";
import { ProductsTab } from "./products-tab";
import { CategoriesTab } from "./categories-tab";

export function ProductManagementPage() {
  const t = useTranslations("nav");
  const [tab, setTab] = useState<string>("products");

  return (
    <div>
      <Tabs value={tab} onValueChange={setTab} className="mt-1">
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
          <CategoriesTab />
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


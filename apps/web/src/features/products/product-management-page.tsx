"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ProductsTab } from "./products-tab";
import { CategoriesTab } from "./categories-tab";
import { BrandsTab } from "./brands-tab";
import { GroupsTab } from "./groups-tab";
import { UomsTab } from "./uoms-tab";
import { VariationTemplatesTab } from "./variation-templates-tab";

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
          <TabsTrigger value="variation-templates">{t("variationTemplates")}</TabsTrigger>
        </TabsList>

        <TabsContent value="products">
          <ProductsTab />
        </TabsContent>
        <TabsContent value="categories">
          <CategoriesTab />
        </TabsContent>
        <TabsContent value="brands">
          <BrandsTab />
        </TabsContent>
        <TabsContent value="groups">
          <GroupsTab />
        </TabsContent>
        <TabsContent value="uoms">
          <UomsTab />
        </TabsContent>
        <TabsContent value="variation-templates">
          <VariationTemplatesTab />
        </TabsContent>
      </Tabs>
    </div>
  );
}


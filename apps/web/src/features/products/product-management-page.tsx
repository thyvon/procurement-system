"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { LookupTab } from "./lookup-tab";
import { ProductsTab } from "./products-tab";

export function ProductManagementPage() {
  const t = useTranslations("nav");
  const tCommon = useTranslations("common");
  const [tab, setTab] = useState<string>("products");

  return (
    <div>
      <h1 className="text-xl font-semibold">{t("products")}</h1>

      <Tabs value={tab} onValueChange={setTab} className="mt-4">
        <TabsList>
          <TabsTrigger value="products">{t("products")}</TabsTrigger>

          <Tooltip>
            <TooltipTrigger render={<div />}>
              <div>
                <TabsTrigger value="categories" disabled>
                  {t("categories")}
                </TabsTrigger>
              </div>
            </TooltipTrigger>
            <TooltipContent>{tCommon("soon")}</TooltipContent>
          </Tooltip>
          <TabsTrigger value="brands">{t("brands")}</TabsTrigger>
          <TabsTrigger value="groups">{t("groups")}</TabsTrigger>

          <Tooltip>
            <TooltipTrigger render={<div />}>
              <div>
                <TabsTrigger value="uoms" disabled>
                  {t("uoms")}
                </TabsTrigger>
              </div>
            </TooltipTrigger>
            <TooltipContent>{tCommon("soon")}</TooltipContent>
          </Tooltip>
        </TabsList>

        <TabsContent value="products">
          <ProductsTab />
        </TabsContent>
        <TabsContent value="brands">
          <LookupTab kind="brands" />
        </TabsContent>
        <TabsContent value="groups">
          <LookupTab kind="groups" />
        </TabsContent>
      </Tabs>
    </div>
  );
}


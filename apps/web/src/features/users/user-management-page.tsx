"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { UsersTab } from "./users-tab";
import { RolesTab } from "./roles-tab";

export function UserManagementPage() {
  const t = useTranslations("nav");
  const [tab, setTab] = useState<string>("users");

  return (
    <div>
      <Tabs value={tab} onValueChange={setTab} className="mt-1">
        <TabsList>
          <TabsTrigger value="users">{t("users")}</TabsTrigger>
          <TabsTrigger value="roles">{t("roles")}</TabsTrigger>
        </TabsList>

        <TabsContent value="users">
          <UsersTab />
        </TabsContent>
        <TabsContent value="roles">
          <RolesTab />
        </TabsContent>
      </Tabs>
    </div>
  );
}

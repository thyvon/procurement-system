"use client";

import { useTranslations } from "next-intl";
import { useMe } from "@/hooks/use-me";
import { PageSkeleton } from "@/components/layout/page-skeleton";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

export default function HomePage() {
  const t = useTranslations("dashboard");

  const meQuery = useMe();

  const user = meQuery.data ?? null;

  if (meQuery.isPending) {
    return <PageSkeleton />;
  }

  return (
    <div>
      <Card className="max-w-md">
        <CardHeader>
          <CardTitle>{user?.name}</CardTitle>
          <CardDescription>{t("greeting")}</CardDescription>
        </CardHeader>
        <CardContent>
          <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
            <dt className="font-medium text-muted-foreground">
              {t("email")}
            </dt>
            <dd>{user?.email}</dd>
            <dt className="font-medium text-muted-foreground">
              {t("entityId")}
            </dt>
            <dd className="font-mono text-xs">{user?.entityId ?? "—"}</dd>
            <dt className="font-medium text-muted-foreground">
              {t("status")}
            </dt>
            <dd>
              <span
                className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                  user?.isActive
                    ? "bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400"
                    : "bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400"
                }`}
              >
                {user?.isActive ? t("statusActive") : t("statusInactive")}
              </span>
            </dd>
          </dl>
        </CardContent>
      </Card>
    </div>
  );
}

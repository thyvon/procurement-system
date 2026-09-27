"use client";

import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { entitiesLogo } from "@/lib/api/entity/entity";
import type { EntityResource } from "@/lib/api/model";
import { unwrap, withAuth } from "@/lib/api-client";
import { useEntity } from "@/hooks/use-entity";
import { useMe } from "@/hooks/use-me";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export function SettingsPage() {
  const t = useTranslations("settings");
  const queryClient = useQueryClient();
  const meQuery = useMe();
  const permissions = (meQuery.data?.permissions ?? []) as string[];
  const canManage = permissions.includes("entities.manage");
  const entityQuery = useEntity();
  const entity = entityQuery.data;
  const [file, setFile] = useState<File | null>(null);

  const uploadMutation = useMutation({
    mutationFn: async (image: File) => {
      if (!entity) {
        throw new Error("No entity");
      }

      return unwrap<EntityResource>(
        await entitiesLogo(entity.id, { image }, withAuth())
      );
    },
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ["entities", data.id] });
      setFile(null);
    },
  });

  return (
    <div className="mt-1 min-w-0 space-y-6">
      <div>
        <h1 className="text-xl font-semibold tracking-tight">{t("title")}</h1>
        <p className="text-sm text-muted-foreground">{t("description")}</p>
      </div>

      <Card size="sm" className="max-w-xl">
        <CardHeader>
          <CardTitle>{t("logoTitle")}</CardTitle>
          <CardDescription>{t("logoDescription")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center gap-4">
            {entity?.logo ? (
              <img
                src={entity.logo}
                alt={t("logoTitle")}
                className="h-14 w-28 shrink-0 rounded-md border border-border bg-background object-contain p-1"
              />
            ) : (
              <div className="flex h-14 w-28 shrink-0 items-center justify-center rounded-md border border-dashed border-border text-center text-xs text-muted-foreground">
                {t("logoEmpty")}
              </div>
            )}
            <p className="text-xs text-muted-foreground">{t("logoHint")}</p>
          </div>

          {canManage ? (
            <div className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="logo-file">{t("uploadLabel")}</Label>
                <Input
                  id="logo-file"
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                />
                <p className="text-xs text-muted-foreground">
                  {t("uploadHint")}
                </p>
              </div>

              <div className="flex justify-end">
                <Button
                  disabled={!file || !entity || uploadMutation.isPending}
                  onClick={() => file && uploadMutation.mutate(file)}
                >
                  {uploadMutation.isPending ? t("uploading") : t("upload")}
                </Button>
              </div>
            </div>
          ) : null}
        </CardContent>
      </Card>
    </div>
  );
}

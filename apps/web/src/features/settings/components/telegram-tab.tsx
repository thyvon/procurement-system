"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { RotateCcw } from "lucide-react";
import {
  systemSettingsIndex,
  systemSettingsUpdate,
} from "@/lib/api/system-setting/system-setting";
import type { UpdateSystemSettingRequest } from "@/lib/api/model";
import { unwrap, withAuth } from "@/lib/api-client";
import { useMe } from "@/hooks/use-me";
import { Badge } from "@/components/ui/badge";
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
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";

type SettingItem = {
  key: string;
  label: string;
  type: "string" | "url" | "integer" | "boolean";
  secret: boolean;
  env: string;
  configured: boolean;
  value: string | number | boolean | null;
  min: number | null;
  max: number | null;
};

type SettingsGroup = {
  group: string;
  label: string;
  items: SettingItem[];
};

type SettingValue = string | number | boolean | null;

/**
 * Scramble types `UpdateSystemSettingRequest.values` as `string[]` although
 * the payload is a key→value record (same generator quirk class as
 * TocaEntryResource.users) — cast locally at the call site, never edit the
 * generated contract.
 */
const asRequest = (
  values: Record<string, SettingValue>
): UpdateSystemSettingRequest =>
  ({ values }) as unknown as UpdateSystemSettingRequest;

const isOn = (value: SettingItem["value"]): boolean =>
  value === true || value === 1 || value === "1" || value === "true";

const asText = (value: SettingItem["value"]): string =>
  value === null || value === undefined ? "" : String(value);

export function TelegramTab() {
  const t = useTranslations("settings.telegram");
  const qc = useQueryClient();
  const meQuery = useMe();
  const permissions = (meQuery.data?.permissions ?? []) as string[];
  const canManage = permissions.includes("settings.manage");

  const query = useQuery({
    queryKey: ["systemSettings", "telegram"],
    queryFn: async () =>
      unwrap<SettingsGroup>(await systemSettingsIndex("telegram", withAuth())),
  });

  // Only user edits are stored; untouched fields derive from the server
  // payload, so a refetch after save resets the form without effect-driven
  // state sync.
  const [drafts, setDrafts] = useState<Record<string, string>>({});
  const [secretDrafts, setSecretDrafts] = useState<Record<string, string>>({});
  const [clearing, setClearing] = useState<string[]>([]);
  const [booleans, setBooleans] = useState<Record<string, boolean>>({});

  const items = query.data?.items;

  const collectChanges = (): Record<string, SettingValue> => {
    const values: Record<string, SettingValue> = {};
    if (!items) return values;

    for (const item of items) {
      if (item.secret) {
        const typed = secretDrafts[item.key] ?? "";
        if (typed !== "") {
          values[item.key] = typed;
        } else if (clearing.includes(item.key)) {
          values[item.key] = null;
        }
        continue;
      }

      if (item.type === "boolean") {
        const original = isOn(item.value);
        const current = booleans[item.key] ?? original;
        if (current !== original) {
          values[item.key] = current;
        }
        continue;
      }

      const current = drafts[item.key] ?? asText(item.value);
      const original = asText(item.value);
      if (current === original) continue;

      // Empty input means "clear the override" so the environment value
      // applies again; the API treats null exactly that way.
      values[item.key] =
        current === ""
          ? null
          : item.type === "integer"
            ? Number(current)
            : current;
    }

    return values;
  };

  const hasChanges = Object.keys(collectChanges()).length > 0;

  const saveMutation = useMutation({
    mutationFn: async (values: Record<string, SettingValue>) =>
      unwrap<SettingsGroup>(
        await systemSettingsUpdate("telegram", asRequest(values), withAuth())
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["systemSettings", "telegram"] });
      toast.success(t("saved"));
    },
  });

  if (query.isPending) {
    return (
      <Card size="sm" className="max-w-2xl">
        <CardHeader>
          <Skeleton className="h-5 w-48" />
          <Skeleton className="h-4 w-72" />
        </CardHeader>
        <CardContent className="space-y-5">
          {[1, 2, 3, 4].map((line) => (
            <div key={line} className="space-y-2">
              <Skeleton className="h-4 w-40" />
              <Skeleton className="h-9 w-full" />
            </div>
          ))}
        </CardContent>
      </Card>
    );
  }

  return (
    <Card size="sm" className="max-w-2xl">
      <CardHeader>
        <CardTitle>{t("cardTitle")}</CardTitle>
        <CardDescription>{t("cardDescription")}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        {items?.map((item) => (
          <div key={item.key} className="space-y-2">
            <div className="flex items-center justify-between gap-3">
              <Label htmlFor={`setting-${item.key}`}>{item.label}</Label>
              {item.secret && item.configured ? (
                <Badge variant="outline">{t("configured")}</Badge>
              ) : null}
            </div>

            {item.type === "boolean" ? (
              <Switch
                checked={booleans[item.key] ?? isOn(item.value)}
                onCheckedChange={(checked) =>
                  setBooleans((b) => ({ ...b, [item.key]: checked === true }))
                }
                disabled={!canManage}
                aria-label={item.label}
              />
            ) : item.secret ? (
              <div className="flex items-center gap-2">
                <Input
                  id={`setting-${item.key}`}
                  type="password"
                  autoComplete="new-password"
                  value={secretDrafts[item.key] ?? ""}
                  placeholder={
                    item.configured
                      ? t("secretConfiguredPlaceholder")
                      : t("secretEmptyPlaceholder")
                  }
                  disabled={!canManage}
                  onChange={(e) => {
                    const value = e.target.value;
                    setSecretDrafts((s) => ({ ...s, [item.key]: value }));
                    if (value !== "") {
                      setClearing((c) => c.filter((k) => k !== item.key));
                    }
                  }}
                />
                {canManage && item.configured ? (
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="shrink-0"
                    aria-pressed={clearing.includes(item.key)}
                    onClick={() => {
                      setSecretDrafts((s) => ({ ...s, [item.key]: "" }));
                      setClearing((c) =>
                        c.includes(item.key)
                          ? c.filter((k) => k !== item.key)
                          : [...c, item.key]
                      );
                    }}
                  >
                    <RotateCcw className="mr-1 size-3.5" />
                    {clearing.includes(item.key)
                      ? t("clearQueued")
                      : t("clear")}
                  </Button>
                ) : null}
              </div>
            ) : (
              <Input
                id={`setting-${item.key}`}
                type={item.type === "integer" ? "number" : "text"}
                min={item.min ?? undefined}
                max={item.max ?? undefined}
                value={drafts[item.key] ?? asText(item.value)}
                disabled={!canManage}
                onChange={(e) =>
                  setDrafts((d) => ({ ...d, [item.key]: e.target.value }))
                }
              />
            )}

            <p className="text-xs text-muted-foreground">
              {item.type === "integer"
                ? t("rangeHint", {
                    min: String(item.min ?? 0),
                    max: String(item.max ?? 0),
                    env: item.env,
                  })
                : item.configured
                  ? t("overriddenHint", { env: item.env })
                  : t("envFallbackHint", { env: item.env })}
            </p>
          </div>
        ))}

        {canManage ? (
          <div className="flex justify-end border-t border-border pt-4">
            <Button
              onClick={() => saveMutation.mutate(collectChanges())}
              disabled={saveMutation.isPending || !hasChanges}
            >
              {saveMutation.isPending ? t("saving") : t("save")}
            </Button>
          </div>
        ) : null}
      </CardContent>
    </Card>
  );
}

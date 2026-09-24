"use client";

import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useTranslations } from "next-intl";
import { Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RequiredMark } from "@/components/required-mark";
import { Switch } from "@/components/ui/switch";
import { unwrap, withAuth } from "@/lib/api-client";
import {
  productsVariationTemplatesStore,
  productsVariationTemplatesUpdate,
} from "@/lib/api/variation/variation";
import type { VariationTemplateResource } from "@/lib/api/model/variationTemplateResource";

type OptionRow = { id?: string; value: string; sortOrder: string };

interface VariationTemplateDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  editing?: VariationTemplateResource | null;
  onSaved?: (id: string) => void;
}

function toOptionRows(editing?: VariationTemplateResource | null): OptionRow[] {
  const rows = (editing?.options ?? []).map((o) => ({
    id: o.id,
    value: o.value,
    sortOrder: String(o.sortOrder ?? 0),
  }));
  return rows.length > 0 ? rows : [{ value: "", sortOrder: "10" }];
}

export function VariationTemplateDialog({
  open,
  onOpenChange,
  editing,
  onSaved,
}: VariationTemplateDialogProps) {
  const t = useTranslations("products.variationTemplates");
  const qc = useQueryClient();

  const [name, setName] = useState(editing?.name ?? "");
  const [isActive, setIsActive] = useState(editing?.isActive ?? true);
  const [options, setOptions] = useState<OptionRow[]>(toOptionRows(editing));

  const updateOption = (index: number, patch: Partial<OptionRow>) => {
    setOptions((prev) =>
      prev.map((row, i) => (i === index ? { ...row, ...patch } : row))
    );
  };

  const hasInvalidOption = options.some((row) => !row.value.trim());
  const canSave = name.trim().length > 0 && !hasInvalidOption;

  const saveMutation = useMutation({
    mutationFn: async () => {
      const rows = options.map((row, i) => ({
        ...(row.id ? { id: row.id } : {}),
        value: row.value.trim(),
        sort_order:
          row.sortOrder !== "" ? Number(row.sortOrder) : (i + 1) * 10,
      }));
      if (editing) {
        const data = unwrap<{ id: string }>(
          await productsVariationTemplatesUpdate(
            editing.id,
            { name: name.trim(), is_active: isActive, options: rows },
            withAuth()
          )
        );
        return data;
      }
      const data = unwrap<{ id: string }>(
        await productsVariationTemplatesStore(
          { name: name.trim(), is_active: isActive, options: rows },
          withAuth()
        )
      );
      return data;
    },
    onSuccess: (data) => {
      qc.invalidateQueries({ queryKey: ["variation-templates"] });
      toast.success(editing ? t("updated") : t("created"));
      onOpenChange(false);
      if (data?.id) onSaved?.(data.id);
      else onSaved?.(editing?.id ?? "");
    },
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{editing ? t("editTitle") : t("newTitle")}</DialogTitle>
          <DialogDescription>
            {editing ? t("editDescription") : t("newDescription")}
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          <div className="flex items-center gap-3">
            <Label
              htmlFor="vt-name"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("name")} <RequiredMark />
            </Label>
            <Input
              id="vt-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder={t("namePlaceholder")}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="vt-active"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("active")}
            </Label>
            <Switch
              id="vt-active"
              checked={isActive}
              onCheckedChange={setIsActive}
            />
          </div>

          <div className="space-y-2 rounded-md border p-3">
            <div className="flex items-center justify-between gap-2">
              <div>
                <Label className="text-sm font-medium">{t("options")}</Label>
                <p className="text-xs text-muted-foreground">
                  {t("valuesHint")}
                </p>
              </div>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() =>
                  setOptions((prev) => [
                    ...prev,
                    { value: "", sortOrder: String((prev.length + 1) * 10) },
                  ])
                }
              >
                <Plus className="mr-1 size-3" />
                {t("addOption")}
              </Button>
            </div>
            <div className="space-y-2">
              {options.map((row, index) => (
                <div
                  key={row.id ?? `new-${index}`}
                  className="grid grid-cols-[1fr_6rem_auto] items-center gap-2"
                >
                  <Input
                    value={row.value}
                    onChange={(e) => updateOption(index, { value: e.target.value })}
                    placeholder={t("optionValuePlaceholder")}
                    className="h-8"
                    aria-label={t("optionValue")}
                  />
                  <Input
                    type="number"
                    min={0}
                    value={row.sortOrder}
                    onChange={(e) =>
                      updateOption(index, { sortOrder: e.target.value })
                    }
                    placeholder={t("sortOrder")}
                    className="h-8 font-mono"
                    aria-label={t("sortOrder")}
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8 text-destructive disabled:opacity-40"
                    disabled={options.length <= 1}
                    onClick={() =>
                      setOptions((prev) => prev.filter((_, i) => i !== index))
                    }
                  >
                    <Trash2 className="size-4" />
                  </Button>
                </div>
              ))}
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={saveMutation.isPending}
          >
            {t("cancel")}
          </Button>
          <Button
            onClick={() => saveMutation.mutate()}
            disabled={saveMutation.isPending || !canSave}
          >
            {saveMutation.isPending ? t("saving") : t("save")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

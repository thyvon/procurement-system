"use client";

import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useTranslations } from "next-intl";
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
import { productsBrandsStore, productsBrandsUpdate } from "@/lib/api/brand/brand";
import type { StoreBrandRequest } from "@/lib/api/model/storeBrandRequest";

export type Brand = {
  id: string;
  code: string;
  name: string;
  description: string | null;
  isActive: boolean;
};

type BrandForm = {
  name: string;
  description: string;
  isActive: boolean;
};

const EMPTY_FORM: BrandForm = {
  name: "",
  description: "",
  isActive: true,
};

function toForm(editing?: Brand | null): BrandForm {
  if (!editing) return { ...EMPTY_FORM };
  return {
    name: editing.name,
    description: editing.description ?? "",
    isActive: editing.isActive,
  };
}

interface BrandDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  editing?: Brand | null;
  onSaved?: (id: string) => void;
}

export function BrandDialog({
  open,
  onOpenChange,
  editing,
  onSaved,
}: BrandDialogProps) {
  const t = useTranslations("products.brands");
  const qc = useQueryClient();
  const [form, setForm] = useState<BrandForm>(() => toForm(editing));

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload: StoreBrandRequest = {
        name: form.name,
        description: form.description || null,
        is_active: form.isActive,
      };
      if (editing) {
        unwrap(await productsBrandsUpdate(editing.id, payload, withAuth()));
        return editing.id;
      }
      const created = unwrap<{ id: string }>(
        await productsBrandsStore(payload, withAuth())
      );
      return created.id;
    },
    onSuccess: (id) => {
      qc.invalidateQueries({ queryKey: ["brands"] });
      toast.success(editing ? t("updated") : t("created"));
      onOpenChange(false);
      onSaved?.(id);
    },
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{editing ? t("editTitle") : t("newTitle")}</DialogTitle>
          <DialogDescription>
            {editing ? t("editDescription") : t("newDescription")}
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          <div className="flex items-center gap-3">
            <Label
              htmlFor="brand-name"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("name")} <RequiredMark />
            </Label>
            <Input
              id="brand-name"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              placeholder={t("namePlaceholder")}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="brand-description"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("description")}
            </Label>
            <Input
              id="brand-description"
              value={form.description}
              onChange={(e) =>
                setForm((f) => ({ ...f, description: e.target.value }))
              }
              placeholder={t("descriptionPlaceholder")}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="brand-active"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("active")}
            </Label>
            <Switch
              id="brand-active"
              checked={form.isActive}
              onCheckedChange={(checked) =>
                setForm((f) => ({ ...f, isActive: checked }))
              }
            />
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
            disabled={saveMutation.isPending || !form.name.trim()}
          >
            {saveMutation.isPending ? t("saving") : t("save")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

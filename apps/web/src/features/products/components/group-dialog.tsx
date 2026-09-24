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
import {
  productsGroupsStore,
  productsGroupsUpdate,
} from "@/lib/api/product-group/product-group";
import type { StoreProductGroupRequest } from "@/lib/api/model/storeProductGroupRequest";

export type Group = {
  id: string;
  code: string;
  name: string;
  description: string | null;
  isActive: boolean;
};

type GroupForm = {
  name: string;
  description: string;
  isActive: boolean;
};

const EMPTY_FORM: GroupForm = {
  name: "",
  description: "",
  isActive: true,
};

function toForm(editing?: Group | null): GroupForm {
  if (!editing) return { ...EMPTY_FORM };
  return {
    name: editing.name,
    description: editing.description ?? "",
    isActive: editing.isActive,
  };
}

interface GroupDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  editing?: Group | null;
  onSaved?: (id: string) => void;
}

export function GroupDialog({
  open,
  onOpenChange,
  editing,
  onSaved,
}: GroupDialogProps) {
  const t = useTranslations("products.groups");
  const qc = useQueryClient();
  const [form, setForm] = useState<GroupForm>(() => toForm(editing));

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload: StoreProductGroupRequest = {
        name: form.name,
        description: form.description || null,
        is_active: form.isActive,
      };
      if (editing) {
        unwrap(await productsGroupsUpdate(editing.id, payload, withAuth()));
        return editing.id;
      }
      const created = unwrap<{ id: string }>(
        await productsGroupsStore(payload, withAuth())
      );
      return created.id;
    },
    onSuccess: (id) => {
      qc.invalidateQueries({ queryKey: ["groups"] });
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
              htmlFor="group-name"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("name")} <RequiredMark />
            </Label>
            <Input
              id="group-name"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              placeholder={t("namePlaceholder")}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="group-description"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("description")}
            </Label>
            <Input
              id="group-description"
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
              htmlFor="group-active"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("active")}
            </Label>
            <Switch
              id="group-active"
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

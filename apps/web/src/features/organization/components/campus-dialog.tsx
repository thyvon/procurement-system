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
  organizationCampusesStore,
  organizationCampusesUpdate,
} from "@/lib/api/campus/campus";

export type Campus = {
  id: string;
  name: string;
  isActive: boolean;
};

type CampusForm = {
  name: string;
  isActive: boolean;
};

const EMPTY_FORM: CampusForm = {
  name: "",
  isActive: true,
};

function toForm(editing?: Campus | null): CampusForm {
  if (!editing) return { ...EMPTY_FORM };
  return {
    name: editing.name,
    isActive: editing.isActive,
  };
}

interface CampusDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  editing?: Campus | null;
  onSaved?: (id: string) => void;
}

export function CampusDialog({
  open,
  onOpenChange,
  editing,
  onSaved,
}: CampusDialogProps) {
  const t = useTranslations("organization.campuses");
  const qc = useQueryClient();
  const [form, setForm] = useState<CampusForm>(() => toForm(editing));

  const canSave = form.name.trim().length > 0;

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: form.name,
        is_active: form.isActive,
      };
      if (editing) {
        unwrap(await organizationCampusesUpdate(editing.id, payload, withAuth()));
        return editing.id;
      }
      const created = unwrap<{ id: string }>(
        await organizationCampusesStore(payload, withAuth())
      );
      return created.id;
    },
    onSuccess: (id) => {
      qc.invalidateQueries({ queryKey: ["campuses"] });
      toast.success(editing ? t("updated") : t("created"));
      onOpenChange(false);
      onSaved?.(id);
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
              htmlFor="campus-name"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("name")} <RequiredMark />
            </Label>
            <Input
              id="campus-name"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              placeholder={t("namePlaceholder")}
              maxLength={64}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="campus-active"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("active")}
            </Label>
            <Switch
              id="campus-active"
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
            disabled={saveMutation.isPending || !canSave}
          >
            {saveMutation.isPending ? t("saving") : t("save")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

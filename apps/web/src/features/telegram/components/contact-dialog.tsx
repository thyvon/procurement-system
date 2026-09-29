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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { RequiredMark } from "@/components/required-mark";
import { unwrap, withAuth } from "@/lib/api-client";
import {
  telegramContactsStore,
  telegramContactsUpdate,
} from "@/lib/api/telegram-contact/telegram-contact";
import type { StoreTelegramContactRequest } from "@/lib/api/model/storeTelegramContactRequest";

export type TelegramContact = {
  id: string;
  name: string;
  phone: string | null;
  username: string | null;
  telegramUserId: number | null;
  telegramChatId: number | null;
  telegramAccountId: string | null;
  language: string;
  supplierCode: string | null;
  verifiedAt: string | null;
};

type ContactForm = {
  name: string;
  phone: string;
  username: string;
  language: "km" | "en";
  supplierCode: string;
};

const EMPTY_FORM: ContactForm = {
  name: "",
  phone: "",
  username: "",
  language: "en",
  supplierCode: "",
};

function toForm(editing?: TelegramContact | null): ContactForm {
  if (!editing) return { ...EMPTY_FORM };
  return {
    name: editing.name,
    phone: editing.phone ?? "",
    username: editing.username ?? "",
    language: editing.language === "km" ? "km" : "en",
    supplierCode: editing.supplierCode ?? "",
  };
}

/** UX-only: the API accepts a bare handle and lowercases it via the model mutator. */
function normalizeUsername(value: string): string | null {
  const trimmed = value.trim();
  if (!trimmed) return null;
  return trimmed.startsWith("@") ? trimmed : `@${trimmed}`;
}

interface ContactDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  editing?: TelegramContact | null;
  onSaved?: (id: string) => void;
}

export function ContactDialog({
  open,
  onOpenChange,
  editing,
  onSaved,
}: ContactDialogProps) {
  const t = useTranslations("telegram.contacts");
  const qc = useQueryClient();
  const [form, setForm] = useState<ContactForm>(() => toForm(editing));

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload: StoreTelegramContactRequest = {
        name: form.name,
        phone: form.phone.trim() || null,
        username: normalizeUsername(form.username),
        language: form.language,
        supplier_code: form.supplierCode.trim() || null,
      };
      if (editing) {
        unwrap(await telegramContactsUpdate(editing.id, payload, withAuth()));
        return editing.id;
      }
      const created = unwrap<{ id: string }>(
        await telegramContactsStore(payload, withAuth()),
      );
      return created.id;
    },
    onSuccess: (id) => {
      qc.invalidateQueries({ queryKey: ["telegramContacts"] });
      qc.invalidateQueries({ queryKey: ["telegramMessages"] });
      toast.success(editing ? t("updated") : t("created"));
      onOpenChange(false);
      onSaved?.(id);
    },
  });

  const hasIdentifier = Boolean(form.phone.trim() || form.username.trim());

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
              htmlFor="contact-name"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("name")} <RequiredMark />
            </Label>
            <Input
              id="contact-name"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              placeholder={t("namePlaceholder")}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="contact-phone"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("phone")} <RequiredMark />
            </Label>
            <Input
              id="contact-phone"
              value={form.phone}
              onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
              placeholder={t("phonePlaceholder")}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="contact-username"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("username")} <RequiredMark />
            </Label>
            <Input
              id="contact-username"
              value={form.username}
              onChange={(e) =>
                setForm((f) => ({ ...f, username: e.target.value }))
              }
              placeholder={t("usernamePlaceholder")}
              className="flex-1"
            />
          </div>
          <p className="text-xs text-muted-foreground">{t("identifierHint")}</p>
          <div className="flex items-center gap-3">
            <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">
              {t("language")}
            </Label>
            <Select
              items={[
                { value: "en", label: t("languageEn") },
                { value: "km", label: t("languageKm") },
              ]}
              value={form.language}
              onValueChange={(value) =>
                setForm((f) => ({ ...f, language: value as "km" | "en" }))
              }
            >
              <SelectTrigger className="h-8 flex-1">
                <SelectValue placeholder={t("language")} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="en">{t("languageEn")}</SelectItem>
                <SelectItem value="km">{t("languageKm")}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="contact-supplier"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("supplierCode")}
            </Label>
            <Input
              id="contact-supplier"
              value={form.supplierCode}
              onChange={(e) =>
                setForm((f) => ({ ...f, supplierCode: e.target.value }))
              }
              placeholder={t("supplierCodePlaceholder")}
              className="flex-1"
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
            disabled={
              saveMutation.isPending || !form.name.trim() || !hasIdentifier
            }
          >
            {saveMutation.isPending ? t("saving") : t("save")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

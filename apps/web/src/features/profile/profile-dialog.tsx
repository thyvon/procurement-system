"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import {
  usersAvatar,
  usersSignature,
  usersUsersUpdate,
} from "@/lib/api/user/user";
import type { UserResource } from "@/lib/api/model";
import { unwrap, withAuth } from "@/lib/api-client";
import { RequiredMark } from "@/components/required-mark";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

type Props = {
  user: UserResource;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

const initialsOf = (name: string) =>
  name
    .split(" ")
    .map((part) => part[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();

export function ProfileDialog({ user, open, onOpenChange }: Props) {
  const t = useTranslations("profile");
  const queryClient = useQueryClient();
  const [name, setName] = useState(user.name);
  const [email, setEmail] = useState(user.email);
  const [position, setPosition] = useState(user.position ?? "");
  const [password, setPassword] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [signatureFile, setSignatureFile] = useState<File | null>(null);

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload: {
        name: string;
        email: string;
        position: string | null;
        password?: string;
      } = {
        name: name.trim(),
        email: email.trim(),
        position: position.trim() || null,
      };
      if (password.trim()) {
        payload.password = password;
      }

      const updated = unwrap<UserResource>(
        await usersUsersUpdate(user.id, payload, withAuth())
      );

      if (file) {
        unwrap<UserResource>(
          await usersAvatar(user.id, { image: file }, withAuth())
        );
      }

      if (signatureFile) {
        unwrap<UserResource>(
          await usersSignature(user.id, { image: signatureFile }, withAuth())
        );
      }

      return updated;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["me"] });
      queryClient.invalidateQueries({ queryKey: ["users"] });
      toast.success(t("updated"));
      setFile(null);
      setSignatureFile(null);
      setPassword("");
      onOpenChange(false);
    },
  });

  const handleOpenChange = (next: boolean) => {
    if (next) {
      setName(user.name);
      setEmail(user.email);
      setPosition(user.position ?? "");
      setPassword("");
      setFile(null);
      setSignatureFile(null);
    } else {
      saveMutation.reset();
    }
    onOpenChange(next);
  };

  const canSave = name.trim() !== "" && email.trim() !== "";

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{t("title")}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4 text-sm">
          <div className="space-y-3">
            <div className="flex items-center gap-3">
              <Label
                htmlFor="profile-name"
                className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
              >
                {t("name")} <RequiredMark />
              </Label>
              <Input
                id="profile-name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label
                htmlFor="profile-email"
                className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
              >
                {t("email")} <RequiredMark />
              </Label>
              <Input
                id="profile-email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label
                htmlFor="profile-position"
                className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
              >
                {t("position")}
              </Label>
              <Input
                id="profile-position"
                value={position}
                onChange={(e) => setPosition(e.target.value)}
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label
                htmlFor="profile-password"
                className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
              >
                {t("password")}
              </Label>
              <Input
                id="profile-password"
                type="password"
                autoComplete="new-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder={t("passwordPlaceholder")}
                className="flex-1"
              />
            </div>
            <div className="flex items-start gap-3">
              <Label
                htmlFor="avatar-file"
                className="w-28 shrink-0 pt-1.5 text-left after:ml-1 after:content-[':']"
              >
                {t("changePhoto")}
              </Label>
              <div className="min-w-0 flex-1 space-y-1.5">
                <div className="flex items-center gap-3">
                  <Avatar className="size-10 shrink-0">
                    {user.avatar && <AvatarImage src={user.avatar} alt="" />}
                    <AvatarFallback className="text-xs">
                      {initialsOf(user.name)}
                    </AvatarFallback>
                  </Avatar>
                  <Input
                    id="avatar-file"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="flex-1"
                    onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                  />
                </div>
                <p className="text-xs text-muted-foreground">
                  {t("photoHint")}
                </p>
              </div>
            </div>
            <div className="flex items-start gap-3">
              <Label
                htmlFor="signature-file"
                className="w-28 shrink-0 pt-1.5 text-left after:ml-1 after:content-[':']"
              >
                {t("changeSignature")}
              </Label>
              <div className="min-w-0 flex-1 space-y-1.5">
                <div className="flex items-center gap-3">
                  {user.signature ? (
                    <img
                      src={user.signature}
                      alt=""
                      className="h-10 w-24 shrink-0 rounded-md border border-border bg-background object-contain p-1"
                    />
                  ) : (
                    <div className="flex h-10 w-24 shrink-0 items-center justify-center rounded-md border border-dashed border-border text-center text-xs text-muted-foreground">
                      —
                    </div>
                  )}
                  <Input
                    id="signature-file"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="flex-1"
                    onChange={(e) =>
                      setSignatureFile(e.target.files?.[0] ?? null)
                    }
                  />
                </div>
                <p className="text-xs text-muted-foreground">
                  {t("signatureHint")}
                </p>
              </div>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => handleOpenChange(false)}
            disabled={saveMutation.isPending}
          >
            {t("cancel")}
          </Button>
          <Button
            disabled={!canSave || saveMutation.isPending}
            onClick={() => saveMutation.mutate()}
          >
            {saveMutation.isPending ? t("saving") : t("save")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

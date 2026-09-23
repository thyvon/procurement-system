"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useTranslations } from "next-intl";
import { usersAvatar } from "@/lib/api/user/user";
import type { UserResource } from "@/lib/api/model";
import { unwrap, withAuth } from "@/lib/api-client";
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
  const [file, setFile] = useState<File | null>(null);

  const uploadMutation = useMutation({
    mutationFn: async (image: File) =>
      unwrap<UserResource>(await usersAvatar(user.id, { image }, withAuth())),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["me"] });
      setFile(null);
      onOpenChange(false);
    },
  });

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      setFile(null);
      uploadMutation.reset();
    }
    onOpenChange(next);
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{t("title")}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4 text-sm">
          <div className="flex items-center gap-4">
            <Avatar className="size-16">
              {user.avatar && <AvatarImage src={user.avatar} alt="" />}
              <AvatarFallback>{initialsOf(user.name)}</AvatarFallback>
            </Avatar>
            <div className="min-w-0">
              <div className="truncate font-medium">{user.name}</div>
              <div className="truncate text-muted-foreground">{user.email}</div>
            </div>
          </div>
          <div className="space-y-2">
            <Label htmlFor="avatar-file">{t("changePhoto")}</Label>
            <Input
              id="avatar-file"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            />
            <p className="text-xs text-muted-foreground">{t("photoHint")}</p>
          </div>
        </div>
        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => handleOpenChange(false)}
            disabled={uploadMutation.isPending}
          >
            {t("cancel")}
          </Button>
          <Button
            disabled={!file || uploadMutation.isPending}
            onClick={() => file && uploadMutation.mutate(file)}
          >
            {uploadMutation.isPending ? t("uploading") : t("upload")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

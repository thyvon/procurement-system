"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useRef, useState } from "react";
import { useTranslations } from "next-intl";
import { Eye, EyeOff, Upload } from "lucide-react";
import { toast } from "sonner";
import {
  usersAvatar,
  usersSignature,
  usersUsersUpdate,
} from "@/lib/api/user/user";
import type { UserResource } from "@/lib/api/model";
import { ApiError, unwrap, withAuth } from "@/lib/api-client";
import { RequiredMark } from "@/components/required-mark";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { SignatureCropDialog } from "@/features/profile/signature-crop-dialog";
import { TelegramAlertsCard } from "@/features/profile/components/telegram-alerts-card";

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

const PROFILE_ERROR_FIELDS = [
  "name",
  "email",
  "password",
  "current_password",
] as const;

const pickFieldErrors = (errors: Record<string, string[]>) =>
  Object.fromEntries(
    PROFILE_ERROR_FIELDS.filter((field) => errors[field]?.[0]).map((field) => [
      field,
      errors[field][0],
    ]),
  );

/** Employment data is managed by an administrator, so it renders as text. */
function ReadonlyRow({
  label,
  value,
}: {
  label: string;
  value: string | null | undefined;
}) {
  return (
    <div className="flex items-center gap-3">
      <span className="w-28 shrink-0 text-left text-sm after:ml-1 after:content-[':']">
        {label}
      </span>
      <p className="min-w-0 flex-1 truncate text-sm">{value || "—"}</p>
    </div>
  );
}

export function ProfileDialog({ user, open, onOpenChange }: Props) {
  const t = useTranslations("profile");
  const tEmp = useTranslations("employeeProfiles");
  const queryClient = useQueryClient();
  const [name, setName] = useState(user.name);
  const [email, setEmail] = useState(user.email);
  const [password, setPassword] = useState("");
  const [currentPassword, setCurrentPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [avatarPending, setAvatarPending] = useState<{
    file: File;
    previewUrl: string;
  } | null>(null);
  const [signaturePending, setSignaturePending] = useState<{
    file: File;
    previewUrl: string;
  } | null>(null);
  const [signatureCropUrl, setSignatureCropUrl] = useState<string | null>(
    null
  );
  const signatureInputRef = useRef<HTMLInputElement>(null);
  const avatarInputRef = useRef<HTMLInputElement>(null);

  const clearPendingAvatar = () => {
    if (avatarPending) {
      URL.revokeObjectURL(avatarPending.previewUrl);
    }
    setAvatarPending(null);
  };

  const clearPendingSignature = () => {
    if (signaturePending) {
      URL.revokeObjectURL(signaturePending.previewUrl);
    }
    setSignaturePending(null);
  };

  const clearFieldError = (field: string) =>
    setFieldErrors((prev) => {
      if (!prev[field]) return prev;
      const next = { ...prev };
      delete next[field];
      return next;
    });

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload: {
        name: string;
        email: string;
        password?: string;
        current_password?: string;
      } = {
        name: name.trim(),
        email: email.trim(),
      };
      if (password.trim()) {
        payload.password = password;
        payload.current_password = currentPassword.trim();
      }

      const updated = unwrap<UserResource>(
        await usersUsersUpdate(user.id, payload, withAuth())
      );

      if (avatarPending) {
        unwrap<UserResource>(
          await usersAvatar(
            user.id,
            { image: avatarPending.file },
            withAuth()
          )
        );
      }

      if (signaturePending) {
        unwrap<UserResource>(
          await usersSignature(
            user.id,
            { image: signaturePending.file },
            withAuth()
          )
        );
      }

      return updated;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["me"] });
      queryClient.invalidateQueries({ queryKey: ["users"] });
      toast.success(t("updated"));
      setFieldErrors({});
      clearPendingAvatar();
      clearPendingSignature();
      setPassword("");
      setCurrentPassword("");
      onOpenChange(false);
    },
    onError: (error) => {
      setFieldErrors(
        error instanceof ApiError && error.errors
          ? pickFieldErrors(error.errors)
          : {}
      );
    },
  });

  const handleOpenChange = (next: boolean) => {
    if (next) {
      setName(user.name);
      setEmail(user.email);
      setPassword("");
      setCurrentPassword("");
      setShowPassword(false);
      setShowCurrentPassword(false);
      setFieldErrors({});
      clearPendingAvatar();
      clearPendingSignature();
      setSignatureCropUrl(null);
    } else {
      saveMutation.reset();
    }
    onOpenChange(next);
  };

  const canSave = name.trim() !== "" && email.trim() !== "";
  const headerAvatarSrc = avatarPending?.previewUrl ?? user.avatar ?? undefined;

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t("title")}</DialogTitle>
        </DialogHeader>
        <div className="flex flex-col items-center gap-2 text-center">
          <Avatar className="size-16 shrink-0">
            {headerAvatarSrc && <AvatarImage src={headerAvatarSrc} alt="" />}
            <AvatarFallback className="text-lg">
              {initialsOf(user.name)}
            </AvatarFallback>
          </Avatar>
          <div className="min-w-0">
            <p className="truncate text-base font-medium">
              {name || user.name}
            </p>
            {user.position && (
              <p className="truncate text-sm text-muted-foreground">
                {user.position}
              </p>
            )}
            <p className="truncate text-sm text-muted-foreground">{email}</p>
          </div>
        </div>
        <div className="space-y-4">
          <Card size="sm">
            <CardHeader>
              <CardTitle>{t("sectionPersonal")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex items-center gap-3">
                <Label
                  htmlFor="profile-name"
                  className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
                >
                  {t("name")} <RequiredMark />
                </Label>
                <div className="min-w-0 flex-1 space-y-1">
                  <Input
                    id="profile-name"
                    value={name}
                    onChange={(e) => {
                      setName(e.target.value);
                      clearFieldError("name");
                    }}
                  />
                  {fieldErrors.name && (
                    <p className="text-xs text-destructive">
                      {fieldErrors.name}
                    </p>
                  )}
                </div>
              </div>
              <div className="flex items-center gap-3">
                <Label
                  htmlFor="profile-email"
                  className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
                >
                  {t("email")} <RequiredMark />
                </Label>
                <div className="min-w-0 flex-1 space-y-1">
                  <Input
                    id="profile-email"
                    type="email"
                    value={email}
                    onChange={(e) => {
                      setEmail(e.target.value);
                      clearFieldError("email");
                    }}
                  />
                  {fieldErrors.email && (
                    <p className="text-xs text-destructive">
                      {fieldErrors.email}
                    </p>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>
          <Card size="sm">
            <CardHeader>
              <CardTitle>{t("sectionEmployment")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <p className="text-xs text-muted-foreground">
                {t("employmentHint")}
              </p>
              <ReadonlyRow label={tEmp("cardId")} value={user.cardId} />
              <ReadonlyRow label={tEmp("position")} value={user.position} />
              <ReadonlyRow label={tEmp("campus")} value={user.campusName} />
              <ReadonlyRow label={tEmp("division")} value={user.divisionName} />
              <ReadonlyRow
                label={tEmp("department")}
                value={user.departmentName}
              />
            </CardContent>
          </Card>
          <Card size="sm">
            <CardHeader>
              <CardTitle>{t("sectionPhotos")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex items-center gap-3">
                <Avatar className="size-10 shrink-0">
                  {headerAvatarSrc && (
                    <AvatarImage src={headerAvatarSrc} alt="" />
                  )}
                  <AvatarFallback className="text-xs">
                    {initialsOf(user.name)}
                  </AvatarFallback>
                </Avatar>
                <Input
                  id="avatar-file"
                  ref={avatarInputRef}
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  className="hidden"
                  onChange={(e) => {
                    const picked = e.target.files?.[0] ?? null;
                    e.target.value = "";
                    if (picked) {
                      if (avatarPending) {
                        URL.revokeObjectURL(avatarPending.previewUrl);
                      }
                      setAvatarPending({
                        file: picked,
                        previewUrl: URL.createObjectURL(picked),
                      });
                    }
                  }}
                />
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium">{t("changePhoto")}</p>
                  <p className="text-xs text-muted-foreground">
                    {t("photoHint")}
                  </p>
                </div>
                <Button
                  type="button"
                  variant="outline"
                  size="icon"
                  className="shrink-0"
                  onClick={() => avatarInputRef.current?.click()}
                >
                  <Upload className="size-4" />
                  <span className="sr-only">{t("changePhoto")}</span>
                </Button>
              </div>
              <div className="flex items-center gap-3">
                {signaturePending?.previewUrl ?? user.signature ? (
                  <img
                    src={signaturePending?.previewUrl ?? user.signature ?? ""}
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
                  ref={signatureInputRef}
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  className="hidden"
                  onChange={(e) => {
                    const picked = e.target.files?.[0] ?? null;
                    e.target.value = "";
                    if (picked) {
                      setSignatureCropUrl(URL.createObjectURL(picked));
                    }
                  }}
                />
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium">{t("changeSignature")}</p>
                  <p className="text-xs text-muted-foreground">
                    {t("signatureHint")}
                  </p>
                </div>
                <Button
                  type="button"
                  variant="outline"
                  size="icon"
                  className="shrink-0"
                  onClick={() => signatureInputRef.current?.click()}
                >
                  <Upload className="size-4" />
                  <span className="sr-only">{t("changeSignature")}</span>
                </Button>
              </div>
            </CardContent>
          </Card>
          <Card size="sm">
            <CardHeader>
              <CardTitle>{t("sectionPassword")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex items-center gap-3">
                <Label
                  htmlFor="profile-current-password"
                  className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
                >
                  {t("currentPassword")}
                </Label>
                <div className="min-w-0 flex-1 space-y-1">
                  <div className="relative">
                    <Input
                      id="profile-current-password"
                      type={showCurrentPassword ? "text" : "password"}
                      autoComplete="current-password"
                      value={currentPassword}
                      onChange={(e) => {
                        setCurrentPassword(e.target.value);
                        clearFieldError("current_password");
                      }}
                      placeholder={t("currentPasswordPlaceholder")}
                      className="pr-9"
                    />
                    <button
                      type="button"
                      onClick={() => setShowCurrentPassword((v) => !v)}
                      aria-label={
                        showCurrentPassword
                          ? t("hidePassword")
                          : t("showPassword")
                      }
                      aria-pressed={showCurrentPassword}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    >
                      {showCurrentPassword ? (
                        <EyeOff className="size-4" />
                      ) : (
                        <Eye className="size-4" />
                      )}
                    </button>
                  </div>
                  {fieldErrors.current_password && (
                    <p className="text-xs text-destructive">
                      {fieldErrors.current_password}
                    </p>
                  )}
                </div>
              </div>
              <div className="flex items-center gap-3">
                <Label
                  htmlFor="profile-password"
                  className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
                >
                  {t("password")}
                </Label>
                <div className="min-w-0 flex-1 space-y-1">
                  <div className="relative">
                    <Input
                      id="profile-password"
                      type={showPassword ? "text" : "password"}
                      autoComplete="new-password"
                      value={password}
                      onChange={(e) => {
                        setPassword(e.target.value);
                        clearFieldError("password");
                      }}
                      placeholder={t("passwordPlaceholder")}
                      className="pr-9"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((v) => !v)}
                      aria-label={
                        showPassword ? t("hidePassword") : t("showPassword")
                      }
                      aria-pressed={showPassword}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    >
                      {showPassword ? (
                        <EyeOff className="size-4" />
                      ) : (
                        <Eye className="size-4" />
                      )}
                    </button>
                  </div>
                  {fieldErrors.password && (
                    <p className="text-xs text-destructive">
                      {fieldErrors.password}
                    </p>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>
          <TelegramAlertsCard key={open ? "open" : "closed"} user={user} />
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
        {signatureCropUrl && (
          <SignatureCropDialog
            imageUrl={signatureCropUrl}
            onOpenChange={(next) => {
              if (!next) {
                URL.revokeObjectURL(signatureCropUrl);
                setSignatureCropUrl(null);
              }
            }}
            onConfirm={(cropped) => {
              URL.revokeObjectURL(signatureCropUrl);
              setSignatureCropUrl(null);
              setSignaturePending({
                file: cropped,
                previewUrl: URL.createObjectURL(cropped),
              });
            }}
          />
        )}
      </DialogContent>
    </Dialog>
  );
}

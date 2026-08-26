"use client";

import { useForm } from "react-hook-form";
import { useMutation } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useTranslations } from "next-intl";
import { ClipboardList } from "lucide-react";
import { authLogin } from "@/lib/api/auth/auth";
import type { LoginRequest } from "@/lib/api/model";
import { saveTokens } from "@/lib/auth/token-store";
import { LocaleSwitch } from "@/components/layout/locale-switch";
import { ThemeToggle } from "@/components/layout/theme-toggle";
import { Button } from "@/components/ui/button";
import {
  Field,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";

type LoginFormValues = Pick<LoginRequest, "email" | "password">;

function LoginForm() {
  const t = useTranslations("auth");
  const router = useRouter();
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormValues>({
    defaultValues: { email: "", password: "" },
  });

  const loginMutation = useMutation({
    mutationFn: (data: LoginFormValues) => authLogin(data),
    onSuccess: (response) => {
      // API envelope: { data: { user, access_token, refresh_token, ... } }
      const payload = (
        response.data as {
          data?: { access_token?: string; refresh_token?: string };
        }
      ).data;

      if (!payload?.access_token || !payload?.refresh_token) {
        setServerError(t("unexpectedResponse"));
        return;
      }
      saveTokens(payload.access_token, payload.refresh_token);
      router.push("/dashboard");
    },
    onError: () => {
      setServerError(t("invalidCredentials"));
    },
  });

  return (
    <form
      onSubmit={handleSubmit((data) => {
        setServerError(null);
        loginMutation.mutate(data);
      })}
      className="flex flex-col gap-6"
    >
      <FieldGroup>
        <div className="flex flex-col items-center gap-1 text-center">
          <h1 className="text-2xl font-bold">{t("signInTitle")}</h1>
        </div>

        <Field data-invalid={errors.email ? true : undefined}>
          <FieldLabel htmlFor="email">{t("email")}</FieldLabel>
          <Input
            id="email"
            type="email"
            autoComplete="email"
            placeholder={t("emailPlaceholder")}
            aria-invalid={!!errors.email}
            {...register("email", {
              required: t("emailRequired"),
              pattern: {
                value: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                message: t("emailInvalid"),
              },
            })}
          />
          {errors.email && (
            <p className="text-xs text-destructive">{errors.email.message}</p>
          )}
        </Field>

        <Field data-invalid={errors.password ? true : undefined}>
          <FieldLabel htmlFor="password">{t("password")}</FieldLabel>
          <Input
            id="password"
            type="password"
            autoComplete="current-password"
            placeholder={t("passwordPlaceholder")}
            aria-invalid={!!errors.password}
            {...register("password", { required: t("passwordRequired") })}
          />
          {errors.password && (
            <p className="text-xs text-destructive">
              {errors.password.message}
            </p>
          )}
        </Field>

        {serverError && (
          <div
            role="alert"
            className="rounded-lg bg-destructive/10 px-4 py-3 text-sm text-destructive"
          >
            {serverError}
          </div>
        )}

        <Field>
          <Button type="submit" disabled={loginMutation.isPending}>
            {loginMutation.isPending ? t("submitting") : t("submit")}
          </Button>
        </Field>
      </FieldGroup>
    </form>
  );
}

export default function LoginPage() {
  return (
    <div className="grid min-h-svh lg:grid-cols-2">
      <div className="flex flex-col gap-4 p-6 md:p-10">
        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center gap-2 font-medium">
            <div className="flex size-6 items-center justify-center rounded-md bg-primary text-primary-foreground">
              <ClipboardList className="size-4" />
            </div>
            Procurement
          </div>
          <div className="flex items-center gap-1">
            <LocaleSwitch />
            <ThemeToggle />
          </div>
        </div>

        <div className="flex flex-1 items-center justify-center">
          <div className="w-full max-w-xs">
            <LoginForm />
          </div>
        </div>
      </div>

      <div className="relative hidden bg-muted lg:block">
        <div className="absolute inset-0 bg-gradient-to-br from-primary/25 via-muted to-primary/10" />
        <div className="absolute inset-0 flex items-center justify-center p-12">
          <p className="max-w-sm text-balance text-lg font-medium text-muted-foreground">
            Procurement Management System
          </p>
        </div>
      </div>
    </div>
  );
}

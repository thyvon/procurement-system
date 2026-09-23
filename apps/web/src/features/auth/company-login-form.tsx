"use client";

import { useState } from "react";
import { useForm } from "react-hook-form";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { Eye, EyeOff, IdCard, Lock } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { authCompanyLogin } from "@/lib/api/auth/auth";
import type { AuthCompanyLogin200Data } from "@/lib/api/model";
import { unwrap } from "@/lib/api-client";
import { saveTokens } from "@/lib/auth/token-store";

type CompanyLoginFormValues = {
  employee_id: string;
  password: string;
};

export function CompanyLoginForm() {
  const t = useTranslations("auth");
  const router = useRouter();
  const queryClient = useQueryClient();
  const [serverError, setServerError] = useState<string | null>(null);
  const [showPassword, setShowPassword] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<CompanyLoginFormValues>({
    defaultValues: { employee_id: "", password: "" },
  });

  const loginMutation = useMutation({
    mutationFn: async (data: CompanyLoginFormValues) => {
      const payload = unwrap<AuthCompanyLogin200Data>(
        await authCompanyLogin(data)
      );

      if (!payload.access_token || !payload.refresh_token) {
        throw new Error(t("unexpectedResponse"));
      }

      return payload;
    },
    onSuccess: (payload) => {
      saveTokens(payload.access_token, payload.refresh_token);
      queryClient.clear();
      router.push("/");
    },
    onError: (err) => {
      setServerError(err.message || t("invalidCredentials"));
    },
    meta: { silent: true },
  });

  return (
    <form
      onSubmit={handleSubmit((data) => {
        setServerError(null);
        loginMutation.mutate(data);
      })}
      className="flex flex-col gap-5"
    >
      <FieldGroup>
        <Field data-invalid={errors.employee_id ? true : undefined}>
          <FieldLabel htmlFor="employee_id">{t("employeeId")}</FieldLabel>
          <div className="relative">
            <IdCard className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              id="employee_id"
              type="text"
              autoComplete="username"
              placeholder={t("employeeIdPlaceholder")}
              aria-invalid={!!errors.employee_id}
              className="pl-9"
              {...register("employee_id", {
                required: t("employeeIdRequired"),
              })}
            />
          </div>
          {errors.employee_id && (
            <p className="text-xs text-destructive">
              {errors.employee_id.message}
            </p>
          )}
        </Field>

        <Field data-invalid={errors.password ? true : undefined}>
          <FieldLabel htmlFor="company-password">{t("password")}</FieldLabel>
          <div className="relative">
            <Lock className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              id="company-password"
              type={showPassword ? "text" : "password"}
              autoComplete="current-password"
              placeholder={t("passwordPlaceholder")}
              aria-invalid={!!errors.password}
              className="pl-9 pr-9"
              {...register("password", { required: t("passwordRequired") })}
            />
            <button
              type="button"
              tabIndex={-1}
              onClick={() => setShowPassword((v) => !v)}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
            >
              {showPassword ? (
                <EyeOff className="size-4" />
              ) : (
                <Eye className="size-4" />
              )}
            </button>
          </div>
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
          <Button
            type="submit"
            disabled={loginMutation.isPending}
            className="w-full"
            size="lg"
          >
            {loginMutation.isPending ? t("submitting") : t("submit")}
          </Button>
        </Field>
      </FieldGroup>
    </form>
  );
}

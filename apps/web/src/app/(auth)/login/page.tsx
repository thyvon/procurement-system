"use client";

import { useForm } from "react-hook-form";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { useTranslations } from "next-intl";
import { ClipboardList, Mail, Lock, Eye, EyeOff, Package, ShoppingCart, FileText, Truck, ClipboardCheck, Warehouse } from "lucide-react";
import { authLogin } from "@/lib/api/auth/auth";
import { unwrap } from "@/lib/api-client";
import type { LoginRequest } from "@/lib/api/model";
import { saveTokens } from "@/lib/auth/token-store";
import { LocaleSwitch } from "@/components/layout/locale-switch";
import { ThemeToggle } from "@/components/layout/theme-toggle";
import { WelcomeLoader } from "@/features/auth/welcome-loader";
import { CompanyLoginForm } from "@/features/auth/company-login-form";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Field,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";

type LoginFormValues = Pick<LoginRequest, "email" | "password">;

function LoginForm() {
  const t = useTranslations("auth");
  const [tab, setTab] = useState("email");

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-col items-center gap-2 text-center">
        <h1 className="text-2xl font-bold tracking-tight">
          {t("signInTitle")}
        </h1>
        <p className="text-sm text-muted-foreground">
          {t("signInDescription")}
        </p>
      </div>

      <Tabs value={tab} onValueChange={setTab} className="gap-4">
        <TabsList className="grid w-full grid-cols-2">
          <TabsTrigger value="email">{t("tabEmail")}</TabsTrigger>
          <TabsTrigger value="company">{t("tabEPurchase")}</TabsTrigger>
        </TabsList>
        <TabsContent value="email">
          <EmailLoginForm />
        </TabsContent>
        <TabsContent value="company">
          <CompanyLoginForm />
        </TabsContent>
      </Tabs>
    </div>
  );
}

function EmailLoginForm() {
  const t = useTranslations("auth");
  const router = useRouter();
  const queryClient = useQueryClient();
  const [serverError, setServerError] = useState<string | null>(null);
  const [showPassword, setShowPassword] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormValues>({
    defaultValues: { email: "", password: "" },
  });

  const loginMutation = useMutation({
    mutationFn: async (data: LoginFormValues) => {
      const payload = unwrap<{
        access_token?: string;
        refresh_token?: string;
      }>(await authLogin(data));

      if (!payload.access_token || !payload.refresh_token) {
        throw new Error(t("unexpectedResponse"));
      }

      return payload;
    },
    onSuccess: (payload) => {
      saveTokens(payload.access_token!, payload.refresh_token!);
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
        <Field data-invalid={errors.email ? true : undefined}>
          <FieldLabel htmlFor="email">{t("email")}</FieldLabel>
          <div className="relative">
            <Mail className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              id="email"
              type="email"
              autoComplete="email"
              placeholder={t("emailPlaceholder")}
              aria-invalid={!!errors.email}
              className="pl-9"
              {...register("email", {
                required: t("emailRequired"),
                pattern: {
                  value: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                  message: t("emailInvalid"),
                },
              })}
            />
          </div>
          {errors.email && (
            <p className="text-xs text-destructive">{errors.email.message}</p>
          )}
        </Field>

        <Field data-invalid={errors.password ? true : undefined}>
          <FieldLabel htmlFor="password">{t("password")}</FieldLabel>
          <div className="relative">
            <Lock className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              id="password"
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
              {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
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

export default function LoginPage() {
  return (
    <div className="grid min-h-svh lg:grid-cols-[1fr_1.2fr]">
      <WelcomeLoader />
      {/* Left — Form panel */}
      <div className="flex flex-col p-6 md:p-10">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2.5 font-semibold">
            <div className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-md shadow-primary/25">
              <ClipboardList className="size-4" />
            </div>
            <span className="text-lg">Procurement</span>
          </div>
          <div className="flex items-center gap-1">
            <LocaleSwitch />
            <ThemeToggle />
          </div>
        </div>

        <div className="flex flex-1 items-center justify-center">
          <div className="w-full max-w-sm">
            <LoginForm />
          </div>
        </div>
      </div>

      {/* Right — Animated gradient panel */}
      <div className="relative hidden overflow-hidden lg:block" style={{ background: "linear-gradient(135deg, oklch(0.20 0.08 260) 0%, oklch(0.25 0.10 270) 50%, oklch(0.18 0.06 280) 100%)" }}>
        {/* Gradient layers */}
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_20%_0%,rgba(120,140,255,0.20)_0%,transparent_55%)]" />
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_90%_100%,rgba(180,120,255,0.12)_0%,transparent_50%)]" />
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_50%_50%,rgba(255,255,255,0.04)_0%,transparent_40%)]" />

        {/* Floating icons */}
        <div className="login-icon login-icon-1">
          <Package className="size-8" />
        </div>
        <div className="login-icon login-icon-2">
          <ShoppingCart className="size-7" />
        </div>
        <div className="login-icon login-icon-3">
          <FileText className="size-6" />
        </div>
        <div className="login-icon login-icon-4">
          <Truck className="size-5" />
        </div>
        <div className="login-icon login-icon-5">
          <ClipboardCheck className="size-7" />
        </div>
        <div className="login-icon login-icon-6">
          <Warehouse className="size-6" />
        </div>

        {/* Center content */}
        <div className="absolute inset-0 flex flex-col items-center justify-center p-12 text-center">
          <div className="mb-6 flex size-16 items-center justify-center rounded-2xl bg-white/10 shadow-lg backdrop-blur-sm">
            <ClipboardList className="size-8 text-white" />
          </div>
          <h2 className="mb-3 text-2xl font-bold text-white">
            Procurement Management
          </h2>
          <p className="max-w-xs text-sm leading-relaxed text-white/70">
            Streamline your procurement workflow — from requisitions to purchase orders and beyond.
          </p>
        </div>

        {/* Signature */}
        <div className="absolute bottom-8 left-0 right-0 text-center">
          <span className="font-signature text-3xl tracking-wide text-white/30">
            Procurement System
          </span>
        </div>

        {/* Bottom accent line */}
        <div className="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-white/20 via-white/40 to-white/20" />
      </div>
    </div>
  );
}

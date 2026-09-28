"use client"

import { useMutation, useQueryClient } from "@tanstack/react-query"
import { useRouter } from "next/navigation"
import { useTranslations } from "next-intl"
import { Lock } from "lucide-react"
import { authLogout } from "@/lib/api/auth/auth"
import { ApiError } from "@/lib/api-client"
import { skipWelcomeLoader } from "@/features/auth/welcome-loader"
import { authHeaders, clearTokens } from "@/lib/auth/token-store"
import { Button } from "@/components/ui/button"

/**
 * True when the API rejected an E-Purchase proxy call because the signed-in
 * user has no company session: signed in with Email, or the upstream
 * session expired and was forgotten.
 */
export function requiresEPurchaseLogin(error: unknown): boolean {
  return (
    error instanceof ApiError &&
    error.statusCode === 401 &&
    error.error === "EPurchaseSessionExpired"
  )
}

export function EPurchaseLoginRequired() {
  const t = useTranslations("epurchase")
  const router = useRouter()
  const queryClient = useQueryClient()

  const switchAccountMutation = useMutation({
    mutationFn: () => authLogout({ headers: authHeaders() }),
    onSettled: () => {
      skipWelcomeLoader()
      clearTokens()
      queryClient.clear()
      router.push("/login")
    },
  })

  return (
    <div className="mt-1 flex min-h-[50vh] flex-col items-center justify-center px-4 text-center">
      <Lock className="size-10 text-muted-foreground" aria-hidden="true" />
      <h2 className="mt-4 text-lg font-semibold tracking-tight">
        {t("loginRequired.title")}
      </h2>
      <p className="mt-2 max-w-md text-sm text-muted-foreground">
        {t("loginRequired.description")}
      </p>
      <Button
        variant="outline"
        className="mt-5"
        onClick={() => switchAccountMutation.mutate()}
        disabled={switchAccountMutation.isPending}
      >
        {t("loginRequired.switchAccount")}
      </Button>
    </div>
  )
}

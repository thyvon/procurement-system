"use client"

import { useRouter } from "next/navigation"
import { useQuery } from "@tanstack/react-query"
import { useTranslations } from "next-intl"
import { ArrowLeft } from "lucide-react"
import { unwrap, withAuth } from "@/lib/api-client"
import { epurchasePrsShow } from "@/lib/api/epurchase-pr/epurchase-pr"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
  EPurchaseLoginRequired,
  requiresEPurchaseLogin,
} from "@/features/epurchase/components/login-required"

export type EPurchasePrLine = {
  id: number
  itemCode: string
  description: string
  description3: string
  campusCode: string
  divisionCode: string
  departmentCode: string
  qty: number | null
  unitType: string
  unitPrice: number | null
  subTotal: number | null
  currency: string
  status: string | null
  canceled: number
  received: number
  purchaseOrderQty: number
  remainAfterPo: number
  forceClose: number
}

export function EPurchasePrDetail({
  prId,
  refNum,
}: {
  prId: string
  refNum: string | null
}) {
  const router = useRouter()
  const t = useTranslations("epurchasePrs")
  const td = useTranslations("epurchasePrs.detail")

  const query = useQuery({
    queryKey: ["epurchasePrs", "detail", prId],
    queryFn: async () =>
      unwrap<EPurchasePrLine[]>(await epurchasePrsShow(prId, withAuth())),
    retry: false,
  })

  if (query.isPending) {
    return null
  }

  if (query.isError) {
    if (requiresEPurchaseLogin(query.error)) {
      return <EPurchaseLoginRequired />
    }

    return (
      <div className="space-y-4">
        <div className="mt-1 rounded-md border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
          {query.error instanceof Error && query.error.message
            ? query.error.message
            : t("loadFailed")}
        </div>
        <Button variant="outline" onClick={() => router.push("/epurchase/prs")}>
          <ArrowLeft />
          {td("back")}
        </Button>
      </div>
    )
  }

  const lines = query.data ?? []

  const money = (value: number | null, currency: string) =>
    value !== null
      ? `${currency ? `${currency} ` : ""}${Number(value).toFixed(2)}`
      : "—"

  if (lines.length === 0) {
    return (
      <div className="space-y-4">
        <p className="text-sm text-muted-foreground">{td("notFound")}</p>
        <Button variant="outline" onClick={() => router.push("/epurchase/prs")}>
          <ArrowLeft />
          {td("back")}
        </Button>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button
            variant="outline"
            size="icon"
            onClick={() => router.push("/epurchase/prs")}
            aria-label={td("back")}
          >
            <ArrowLeft />
          </Button>
          <div>
            <h1 className="text-xl font-semibold tracking-tight">
              {refNum ?? td("title")}
            </h1>
            {refNum !== null ? (
              <p className="text-sm text-muted-foreground">{td("title")}</p>
            ) : null}
          </div>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{td("lines")}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto rounded-lg border border-border">
            <table className="w-full text-sm">
              <thead className="bg-muted/60">
                <tr>
                  <th className="min-w-[120px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.itemCode")}
                  </th>
                  <th className="min-w-[260px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.description")}
                  </th>
                  <th className="min-w-[90px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.campusCode")}
                  </th>
                  <th className="min-w-[100px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.divisionCode")}
                  </th>
                  <th className="min-w-[110px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.departmentCode")}
                  </th>
                  <th className="min-w-[80px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.qty")}
                  </th>
                  <th className="min-w-[110px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.canceled")}
                  </th>
                  <th className="min-w-[150px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.purchaseOrderQty")}
                  </th>
                  <th className="min-w-[120px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.remainAfterPo")}
                  </th>
                  <th className="min-w-[80px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.unitType")}
                  </th>
                  <th className="min-w-[100px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.unitPrice")}
                  </th>
                  <th className="min-w-[120px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.subTotal")}
                  </th>
                  <th className="min-w-[100px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.status")}
                  </th>
                </tr>
              </thead>
              <tbody>
                {lines.map((line) => (
                  <tr key={line.id} className="border-t border-border">
                    <td className="whitespace-nowrap px-3 py-2 font-mono text-xs">
                      {line.itemCode || "—"}
                    </td>
                    <td className="px-3 py-2">
                      <span className="whitespace-normal break-words">
                        {line.description || "—"}
                      </span>
                    </td>
                    <td className="whitespace-nowrap px-3 py-2">
                      {line.campusCode || "—"}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2">
                      {line.divisionCode || "—"}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2">
                      {line.departmentCode || "—"}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {line.qty ?? "—"}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {line.canceled}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {line.purchaseOrderQty}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {line.remainAfterPo}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2">
                      {line.unitType || "—"}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {money(line.unitPrice, line.currency)}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {money(line.subTotal, line.currency)}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2">
                      {line.status || "—"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

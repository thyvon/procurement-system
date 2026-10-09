"use client"

import { useRouter } from "next/navigation"
import { useQuery } from "@tanstack/react-query"
import { useTranslations } from "next-intl"
import { ArrowLeft } from "lucide-react"
import { unwrap, withAuth } from "@/lib/api-client"
import { epurchasePosShow } from "@/lib/api/epurchase-po/epurchase-po"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
  EPurchaseLoginRequired,
  requiresEPurchaseLogin,
} from "@/features/epurchase/components/login-required"

type EPurchasePoLine = {
  id: number
  prRefNum: string
  itemCode: string
  description: string
  description2: string
  poDescription: string | null
  qty: number | null
  unit: string
  campusCode: string
  divisionCode: string
  departmentCode: string
  location: string
  unitCost: number | null
  deliveryFee: number | null
  discount: number | null
  vat: number | null
  usdAmount: number | null
  purchaseQty: number | null
  cancelQty: number | null
  pendingQty: number | null
  currency: string
  status: string | null
  forceClose: number
}

export function EPurchasePoDetail({
  poId,
  refNum,
}: {
  poId: string
  refNum: string | null
}) {
  const router = useRouter()
  const t = useTranslations("epurchasePos")
  const td = useTranslations("epurchasePos.detail")

  const query = useQuery({
    queryKey: ["epurchasePos", "detail", poId],
    queryFn: async () =>
      unwrap<EPurchasePoLine[]>(await epurchasePosShow(poId, withAuth())),
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
        <Button variant="outline" onClick={() => router.push("/epurchase/pos")}>
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
        <Button variant="outline" onClick={() => router.push("/epurchase/pos")}>
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
            onClick={() => router.push("/epurchase/pos")}
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
                  <th className="min-w-[160px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.prRefNum")}
                  </th>
                  <th className="min-w-[120px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.itemCode")}
                  </th>
                  <th className="min-w-[260px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.description")}
                  </th>
                  <th className="min-w-[80px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.qty")}
                  </th>
                  <th className="min-w-[80px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.unit")}
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
                  <th className="min-w-[110px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.location")}
                  </th>
                  <th className="min-w-[110px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.unitCost")}
                  </th>
                  <th className="min-w-[120px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.deliveryFee")}
                  </th>
                  <th className="min-w-[100px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.discount")}
                  </th>
                  <th className="min-w-[80px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.vat")}
                  </th>
                  <th className="min-w-[120px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.usdAmount")}
                  </th>
                  <th className="min-w-[115px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.purchaseQty")}
                  </th>
                  <th className="min-w-[115px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.cancelQty")}
                  </th>
                  <th className="min-w-[115px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.pendingQty")}
                  </th>
                  <th className="min-w-[100px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.status")}
                  </th>
                  <th className="min-w-[90px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                    {td("columns.currency")}
                  </th>
                </tr>
              </thead>
              <tbody>
                {lines.map((line) => (
                  <tr key={line.id} className="border-t border-border">
                    <td className="whitespace-nowrap px-3 py-2 font-mono text-xs">
                      {line.prRefNum || "—"}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono text-xs">
                      {line.itemCode || "—"}
                    </td>
                    <td className="px-3 py-2">
                      <span className="whitespace-normal break-words">
                        {line.description || "—"}
                      </span>
                      {line.description2 ? (
                        <span className="block whitespace-normal break-words text-xs text-muted-foreground">
                          {line.description2}
                        </span>
                      ) : null}
                      {line.poDescription ? (
                        <span className="block whitespace-pre-line break-words text-xs text-muted-foreground">
                          {line.poDescription}
                        </span>
                      ) : null}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {line.qty ?? "—"}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2">
                      {line.unit || "—"}
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
                    <td className="whitespace-nowrap px-3 py-2">
                      {line.location || "—"}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {money(line.unitCost, line.currency)}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {money(line.deliveryFee, line.currency)}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {money(line.discount, line.currency)}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {line.vat !== null ? `${line.vat}%` : "—"}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {money(line.usdAmount, line.currency)}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {line.purchaseQty ?? "—"}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {line.cancelQty ?? "—"}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 font-mono">
                      {line.pendingQty ?? "—"}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2">
                      {line.status || "—"}
                    </td>
                    <td className="whitespace-nowrap px-3 py-2">
                      {line.currency || "—"}
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

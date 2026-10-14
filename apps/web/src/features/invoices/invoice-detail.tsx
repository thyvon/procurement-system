"use client";

import { useRouter } from "next/navigation";
import type { ReactNode } from "react";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { ArrowLeft, Pencil } from "lucide-react";
import { unwrap, withAuth } from "@/lib/api-client";
import { formatDate, formatDateTime, formatMoney } from "@/lib/utils";
import { invoicesInvoicesShow } from "@/lib/api/invoice/invoice";
import type { InvoiceResource } from "@/lib/api/model/invoiceResource";
import type { InvoiceLineResource } from "@/lib/api/model/invoiceLineResource";
import { Button } from "@/components/ui/button";
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { LineItemsTable } from "@/components/line-items-table";
import { InvoiceStatusBadge } from "./components/invoice-status-badge";
import { useMe } from "@/hooks/use-me";

function MetaRow({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex items-start gap-2">
      <Label className="w-36 shrink-0 text-left text-muted-foreground after:ml-1 after:content-[':']">
        {label}
      </Label>
      <span className="min-w-0 break-words text-sm">{value}</span>
    </div>
  );
}

interface InvoiceDetailProps {
  invoiceId: string;
}

export function InvoiceDetail({ invoiceId }: InvoiceDetailProps) {
  const router = useRouter();
  const tf = useTranslations("invoices.form");
  const td = useTranslations("invoices.detail");
  const tt = useTranslations("invoices.table");
  const tc = useTranslations("invoices.columns");
  const meQuery = useMe();

  const showQuery = useQuery({
    queryKey: ["invoices", "detail", invoiceId],
    queryFn: async (): Promise<InvoiceResource> =>
      unwrap(await invoicesInvoicesShow(invoiceId, withAuth())),
  });

  if (showQuery.isPending) {
    return <DataTableSkeleton columns={6} actions={2} />;
  }

  if (showQuery.isError || !showQuery.data) {
    return (
      <div className="mt-1 min-w-0 space-y-4">
        <Button variant="outline" onClick={() => router.push("/invoices")}>
          {tf("back")}
        </Button>
        <p className="text-sm text-destructive">{tf("loadFailed")}</p>
      </div>
    );
  }

  const invoice = showQuery.data;
  const status = invoice.status;
  const permissions = (meQuery.data?.permissions ?? []) as string[];
  const canManage = permissions.includes("invoices.manage");
  const isOwner =
    invoice.createdById != null &&
    meQuery.data != null &&
    invoice.createdById === meQuery.data.id;
  // Mirrors the API: invoices.manage AND owner, only while a draft.
  const canEdit = canManage && isOwner && status === "draft";

  const txTypeLabel = (
    {
      petty_cash: tf("txTypes.pettyCash"),
      advance: tf("txTypes.advance"),
      credit: tf("txTypes.credit"),
    } as Record<string, string>
  )[invoice.transactionType ?? ""];

  const paymentTermLabel = (
    {
      non_credit: tf("paymentTerms.nonCredit"),
      credit_1w: tf("paymentTerms.credit1w"),
      credit_2w: tf("paymentTerms.credit2w"),
      credit_1m: tf("paymentTerms.credit1m"),
    } as Record<string, string>
  )[invoice.paymentTerm ?? ""];

  const subtotal = (invoice.lines ?? []).reduce(
    (sum, line) => sum + Number(line.lineTotal ?? 0),
    0
  );

  return (
    <div className="mt-1 min-w-0 space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button
            variant="outline"
            size="icon"
            onClick={() => router.push("/invoices")}
            aria-label={tf("back")}
          >
            <ArrowLeft />
          </Button>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-semibold tracking-tight">
                {invoice.code}
              </h1>
              <InvoiceStatusBadge status={status} />
            </div>
            <p className="text-sm text-muted-foreground">{td("subtitle")}</p>
          </div>
        </div>
        {canEdit ? (
          <Button
            onClick={() => router.push(`/invoices/${invoice.id}/edit`)}
          >
            <Pencil />
            {tt("edit")}
          </Button>
        ) : null}
      </div>

      <Card size="sm" className="min-w-0">
        <CardHeader>
          <CardTitle>{tf("detailsCard")}</CardTitle>
        </CardHeader>
        <CardContent className="min-w-0 space-y-3">
          {/* 6:6 split — left column: invoice number, invoice date, then
              Currency and Rate sharing one row as two inline pairs;
              right column: supplier and audit info. Notes runs full
              width underneath. */}
          <div className="grid grid-cols-1 gap-x-6 gap-y-3 md:grid-cols-2">
            <div className="space-y-3">
              <MetaRow
                label={tf("invoiceNumber")}
                value={<span className="font-mono">{invoice.invoiceNumber}</span>}
              />
              <MetaRow
                label={tf("invoiceDate")}
                value={formatDate(invoice.invoiceDate)}
              />
              {/* One row, but the Currency label keeps the same w-36 label
                  column and gap-2 as every MetaRow above, so its value starts
                  on the exact same edge; the currency and rate values then
                  share the remaining space equally. */}
              <div className="flex items-center gap-2">
                <Label className="w-36 shrink-0 text-left text-muted-foreground after:ml-1 after:content-[':']">
                  {tf("currency")}
                </Label>
                <div className="flex min-w-0 flex-1 items-center gap-2">
                  <span className="min-w-24 flex-1 text-sm">
                    {invoice.currency}
                  </span>
                  <Label className="shrink-0 text-left text-muted-foreground after:ml-1 after:content-[':']">
                    {tf("exchangeRate")}
                  </Label>
                  <span className="min-w-24 flex-1 text-sm">
                    {invoice.exchangeRate ?? "—"}
                  </span>
                </div>
              </div>
            </div>

            <div className="space-y-3">
              <MetaRow label={tf("supplierName")} value={invoice.supplierName} />
              <MetaRow
                label={tf("supplierPhone")}
                value={invoice.supplierPhone || "—"}
              />
              <MetaRow
                label={tf("supplierAddress")}
                value={invoice.supplierAddress || "—"}
              />
              <MetaRow
                label={tf("cashReference")}
                value={invoice.cashReference || "—"}
              />
              <MetaRow
                label={tf("transactionType")}
                value={txTypeLabel || "—"}
              />
              {/* Same inline-pair shape as Currency + Rate in the left
                  column. */}
              <div className="flex items-center gap-2">
                <Label className="w-36 shrink-0 text-left text-muted-foreground after:ml-1 after:content-[':']">
                  {tf("paymentTerm")}
                </Label>
                <div className="flex min-w-0 flex-1 items-center gap-2">
                  <span className="min-w-24 flex-1 text-sm">
                    {paymentTermLabel || "—"}
                  </span>
                  <Label className="shrink-0 text-left text-muted-foreground after:ml-1 after:content-[':']">
                    {tf("vatPercent")}
                  </Label>
                  <span className="min-w-24 flex-1 text-sm">
                    {invoice.vatPercent != null
                      ? `${invoice.vatPercent}%`
                      : "—"}
                  </span>
                </div>
              </div>
              <MetaRow label={tc("createdBy")} value={invoice.createdBy ?? "—"} />
              <MetaRow label={tc("date")} value={formatDateTime(invoice.updatedAt)} />
            </div>

            <div className="md:col-span-2">
              <MetaRow label={tf("notes")} value={invoice.notes || "—"} />
            </div>
          </div>
        </CardContent>
      </Card>

      <Card size="sm" className="min-w-0">
        <CardHeader>
          <CardTitle>{tf("lines")}</CardTitle>
        </CardHeader>
        <CardContent className="min-w-0">
          <LineItemsTable
            columns={[
              {
                key: "description",
                label: tf("description"),
                cell: (line) => (
                  <span className="block truncate" title={line.description}>
                    {line.description}
                  </span>
                ),
              },
              {
                key: "campus",
                label: tf("campus"),
                width: "w-28",
                cell: (line) => (
                  <span className="flex h-8 items-center text-sm">
                    {line.campus || "—"}
                  </span>
                ),
              },
              {
                key: "division",
                label: tf("division"),
                width: "w-28",
                cell: (line) => (
                  <span className="flex h-8 items-center text-sm">
                    {line.division || "—"}
                  </span>
                ),
              },
              {
                key: "department",
                label: tf("department"),
                width: "w-32",
                cell: (line) => (
                  <span className="flex h-8 items-center text-sm">
                    {line.department || "—"}
                  </span>
                ),
              },
              {
                key: "location",
                label: tf("location"),
                width: "w-32",
                cell: (line) => (
                  <span
                    className="flex h-8 items-center truncate text-sm"
                    title={line.location ?? undefined}
                  >
                    {line.location || "—"}
                  </span>
                ),
              },
              {
                key: "purpose",
                label: tf("purpose"),
                width: "w-56",
                cell: (line) => (
                  <span
                    className="flex min-h-8 items-center py-1 text-sm whitespace-pre-wrap break-words"
                    title={line.purpose ?? undefined}
                  >
                    {line.purpose || "—"}
                  </span>
                ),
              },
              {
                key: "uom",
                label: tf("uom"),
                width: "w-24",
                cell: (line) => (
                  <span className="flex h-8 items-center text-sm">
                    {line.uom || "—"}
                  </span>
                ),
              },
              {
                key: "qty",
                label: tf("qty"),
                width: "w-24",
                align: "right",
                cell: (line) => (
                  <span className="flex h-8 items-center justify-end text-sm tabular-nums">
                    {line.qty}
                  </span>
                ),
              },
              {
                key: "unitPrice",
                label: tf("unitPrice"),
                width: "w-32",
                align: "right",
                cell: (line) => (
                  <span className="flex h-8 items-center justify-end text-sm tabular-nums">
                    {formatMoney(Number(line.unitPrice), invoice.currency)}
                  </span>
                ),
              },
              {
                key: "discount",
                label: tf("discountAmount"),
                width: "w-28",
                align: "right",
                cell: (line) => (
                  <span className="flex h-8 items-center justify-end text-sm tabular-nums">
                    {formatMoney(Number(line.discount ?? 0), invoice.currency)}
                  </span>
                ),
              },
              {
                key: "lineTotal",
                label: tf("lineTotal"),
                width: "w-28",
                align: "right",
                cell: (line) => (
                  <span className="flex h-8 items-center justify-end text-sm tabular-nums">
                    {formatMoney(Number(line.lineTotal ?? 0), invoice.currency)}
                  </span>
                ),
              },
              // USD equivalent only for KHR documents — on a USD invoice it
              // would just repeat the line total.
              ...(invoice.currency === "KHR"
                ? [
                    {
                      key: "grandTotalUsd",
                      label: tf("grandTotalUsd"),
                      width: "w-28",
                      align: "right" as const,
                      cell: (line: InvoiceLineResource) => (
                        <span className="flex h-8 items-center justify-end text-sm tabular-nums">
                          {formatMoney(Number(line.grandTotalUsd ?? 0), "USD")}
                        </span>
                      ),
                    },
                  ]
                : []),
            ]}
            rows={invoice.lines ?? []}
            rowKey={(_, index) => String(index)}
            summary={[
              {
                key: "subtotal",
                label: tf("subtotal"),
                value: formatMoney(subtotal, invoice.currency),
              },
              {
                key: "discount",
                label: tf("discountAmount"),
                value: formatMoney(
                  Number(invoice.discountAmount ?? 0),
                  invoice.currency
                ),
              },
              {
                key: "vat",
                label: tf("vatAmount"),
                value: formatMoney(
                  Number(invoice.vatAmount ?? 0),
                  invoice.currency
                ),
              },
              {
                key: "total",
                label: tf("total"),
                value: formatMoney(Number(invoice.total ?? 0), invoice.currency),
                strong: true,
              },
              ...(invoice.currency === "KHR"
                ? [
                    {
                      key: "grandTotalUsd",
                      label: tf("grandTotalUsd"),
                      value: formatMoney(
                        Number(invoice.grandTotalUsd ?? 0),
                        "USD"
                      ),
                      strong: true,
                    },
                  ]
                : []),
            ]}
          />
        </CardContent>
      </Card>
    </div>
  );
}

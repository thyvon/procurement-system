"use client";

import { useRouter } from "next/navigation";
import type { ReactNode } from "react";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { ArrowLeft, Pencil } from "lucide-react";
import { unwrap, withAuth } from "@/lib/api-client";
import { formatMoney } from "@/lib/utils";
import { invoicesInvoicesShow } from "@/lib/api/invoice/invoice";
import type { InvoiceResource } from "@/lib/api/model/invoiceResource";
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

function MetaRow({
  label,
  value,
}: {
  label: string;
  value: ReactNode;
}) {
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
          <div className="grid grid-cols-1 gap-x-6 gap-y-3 md:grid-cols-2">
            <MetaRow
              label={tf("invoiceNumber")}
              value={<span className="font-mono">{invoice.invoiceNumber}</span>}
            />
            <MetaRow label={tf("supplierName")} value={invoice.supplierName} />
            <MetaRow
              label={tf("supplierCode")}
              value={
                invoice.supplierCode ? (
                  <span className="font-mono">{invoice.supplierCode}</span>
                ) : (
                  "—"
                )
              }
            />
            <MetaRow
              label={tf("invoiceDate")}
              value={invoice.invoiceDate ?? "—"}
            />
            <MetaRow label={tf("currency")} value={invoice.currency} />
            <MetaRow
              label={tf("exchangeRate")}
              value={invoice.exchangeRate ?? "—"}
            />
            <MetaRow label={tf("dueDate")} value={invoice.dueDate ?? "—"} />
            <MetaRow
              label={tc("createdBy")}
              value={invoice.createdBy ?? "—"}
            />
            <MetaRow label={tc("date")} value={invoice.updatedAt ?? "—"} />
            <div className="md:col-span-2">
              <MetaRow
                label={tf("notes")}
                value={invoice.notes || "—"}
              />
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
                key: "qty",
                label: tf("qty"),
                width: "w-24",
                align: "right",
                cell: (line) => (
                  <span className="flex h-7 items-center justify-end text-xs tabular-nums">
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
                  <span className="flex h-7 items-center justify-end text-xs tabular-nums">
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
                  <span className="flex h-7 items-center justify-end text-xs tabular-nums">
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
                  <span className="flex h-7 items-center justify-end text-xs tabular-nums">
                    {formatMoney(Number(line.lineTotal ?? 0), invoice.currency)}
                  </span>
                ),
              },
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
            ]}
          />
        </CardContent>
      </Card>
    </div>
  );
}

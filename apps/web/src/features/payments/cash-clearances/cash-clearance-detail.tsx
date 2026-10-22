"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import type { ReactNode } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { ArrowLeft, Ban, Pencil, Printer, Trash2 } from "lucide-react";
import { unwrap, withAuth } from "@/lib/api-client";
import { formatDate, formatMoney } from "@/lib/utils";
import {
  getPaymentsCashClearancesPrintUrl,
  paymentsCashClearancesDestroy,
  paymentsCashClearancesShow,
  paymentsCashClearancesStatus,
} from "@/lib/api/cash-clearance/cash-clearance";
import type { CashClearanceResource } from "@/lib/api/model/cashClearanceResource";
import type { CashClearanceLineResource } from "@/lib/api/model/cashClearanceLineResource";
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
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { LineItemsTable } from "@/components/line-items-table";
import {
  fetchApprovalRounds,
  type ApprovalRequestView,
} from "@/features/approvals/approval-types";
import { ApprovalTimeline } from "@/features/approvals/components/approval-timeline";
import { useMe } from "@/hooks/use-me";
import { usePrintPreview } from "@/hooks/use-print-preview";
import { CashClearanceStatusBadge } from "./components/cash-clearance-status-badge";
import { CancelDocumentDialog } from "../components/cancel-document-dialog";
import { CashClearanceDetailSkeleton } from "./components/cash-clearance-detail-skeleton";

const CASH_CLEARANCE_SUBJECT = "cash_clearance";

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

interface CashClearanceDetailProps {
  clearanceId: string;
  /** Approval page embed: no page chrome, just the document. */
  embedded?: boolean;
}

export function CashClearanceDetail({
  clearanceId,
  embedded = false,
}: CashClearanceDetailProps) {
  const router = useRouter();
  const tf = useTranslations("payments.form");
  const tt = useTranslations("payments.table");
  const tn = useTranslations("payments.cashClearances");
  const tabs = useTranslations("payments.tabs");
  const ta = useTranslations("approvals.form");
  const tad = useTranslations("approvals.detail");
  const meQuery = useMe();
  const qc = useQueryClient();
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [cancelOpen, setCancelOpen] = useState(false);

  const showQuery = useQuery({
    queryKey: ["cash-clearances", "detail", clearanceId],
    queryFn: async (): Promise<CashClearanceResource> =>
      unwrap(
        await paymentsCashClearancesShow(clearanceId, withAuth())
      ),
  });

  // Shared key/queryFn with the form and approval-detail: every approval
  // round for this clearance, newest first.
  const roundsQuery = useQuery({
    queryKey: [
      "approvals",
      "requests",
      "subject",
      CASH_CLEARANCE_SUBJECT,
      clearanceId,
    ],
    queryFn: (): Promise<ApprovalRequestView[]> =>
      fetchApprovalRounds(CASH_CLEARANCE_SUBJECT, clearanceId),
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(await paymentsCashClearancesDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["cash-clearances"] });
      toast.success(tn("deleted"));
      router.push("/payments/cash-clearances");
    },
  });

  const cancelMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(
        await paymentsCashClearancesStatus(id, { status: "cancelled" }, withAuth())
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["cash-clearances"] });
      toast.success(tn("cancelled"));
      setCancelOpen(false);
    },
  });

  // The backend renders the official sheet (Blade → PDF); the dialog only
  // fetches and previews it.
  const print = usePrintPreview();

  const clearance = showQuery.data ?? null;
  const rounds = roundsQuery.data ?? [];
  const approvalRequest = rounds[0] ?? null;
  const status = clearance?.status ?? null;

  // The submit panel only invalidates the approvals keys, so a just-submitted
  // round (pending while the locally cached document still reads draft or
  // returned) triggers one refetch of this document.
  const needsStatusRefresh =
    approvalRequest != null &&
    approvalRequest.status === "pending" &&
    (status === "draft" || status === "returned");
  useEffect(() => {
    if (needsStatusRefresh) {
      qc.invalidateQueries({ queryKey: ["cash-clearances"] });
    }
  }, [needsStatusRefresh, qc]);

  if (showQuery.isPending) {
    return <CashClearanceDetailSkeleton />;
  }

  if (showQuery.isError || !clearance) {
    if (embedded) {
      return <p className="text-sm text-destructive">{tn("loadFailed")}</p>;
    }

    return (
      <div className="mt-1 min-w-0 space-y-4">
        <Button
          variant="outline"
          onClick={() => router.push("/payments/cash-clearances")}
        >
          {tf("back")}
        </Button>
        <p className="text-sm text-destructive">{tn("loadFailed")}</p>
      </div>
    );
  }

  const permissions = (meQuery.data?.permissions ?? []) as string[];
  const canManage = permissions.includes("payments.manage");
  const isOwner =
    clearance.createdById != null &&
    meQuery.data != null &&
    Number(clearance.createdById) === meQuery.data.id;
  // Mirrors the API: payments.manage AND owner; editable while draft or
  // returned, deletable only while a draft.
  const canAct = canManage && isOwner;
  const canEdit = canAct && ["draft", "returned"].includes(clearance.status);
  const canCancel = canAct && clearance.status === "draft";
  const canDelete = canAct && clearance.status === "draft";

  return (
    <>
      <div className="mt-1 min-w-0 space-y-6">
        {embedded ? null : (
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <Button
                variant="outline"
                size="icon"
                onClick={() => router.push("/payments/cash-clearances")}
                aria-label={tf("back")}
              >
                <ArrowLeft />
              </Button>
              <div>
                <div className="flex items-center gap-2">
                  <h1 className="text-xl font-semibold tracking-tight">
                    {clearance.code}
                  </h1>
                  <CashClearanceStatusBadge status={status} />
                </div>
                <p className="text-sm text-muted-foreground">
                  {tabs("cashClearances")}
                </p>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                onClick={() =>
                  print.open(
                    getPaymentsCashClearancesPrintUrl(clearance.id),
                    clearance.code,
                  )
                }
                disabled={print.isPending}
              >
                <Printer />
                {tad("print")}
              </Button>
              {canEdit ? (
                <Button
                  onClick={() =>
                    router.push(
                      `/payments/cash-clearances/${clearance.id}/edit`
                    )
                  }
                >
                  <Pencil />
                  {tt("edit")}
                </Button>
              ) : null}
              {canCancel ? (
                <Button variant="outline" onClick={() => setCancelOpen(true)}>
                  <Ban />
                  {tt("cancelDocument")}
                </Button>
              ) : null}
              {canDelete ? (
                <Button
                  variant="outline"
                  className="text-destructive hover:bg-destructive/10"
                  onClick={() => setDeleteOpen(true)}
                >
                  <Trash2 />
                  {tt("delete")}
                </Button>
              ) : null}
            </div>
          </div>
        )}

        <Card size="sm" className="min-w-0">
          <CardHeader>
            <CardTitle>{tabs("cashClearances")}</CardTitle>
          </CardHeader>
          <CardContent className="min-w-0 space-y-3">
            <div className="grid grid-cols-1 gap-x-6 gap-y-3 md:grid-cols-2">
              <div className="space-y-3">
                <MetaRow
                  label={tf("clearanceDate")}
                  value={formatDate(clearance.clearanceDate)}
                />
                <MetaRow
                  label={tf("cashRequest")}
                  value={
                    <span
                      className="font-mono"
                      title={clearance.cashRequestId}
                    >
                      {clearance.cashRequestCode ?? clearance.cashRequestId}
                    </span>
                  }
                />
                <MetaRow
                  label={tf("exchangeRate")}
                  value={
                    clearance.exchangeRate
                      ? clearance.exchangeRate.toLocaleString("en-US")
                      : "—"
                  }
                />
              </div>
              <div className="space-y-3">
                <MetaRow
                  label={tf("requestAmount")}
                  value={
                    <span className="tabular-nums">
                      {formatMoney(Number(clearance.requestAmountUsd), "USD")}
                    </span>
                  }
                />
                <MetaRow
                  label={tf("totalUsd")}
                  value={
                    <span className="tabular-nums">
                      {formatMoney(Number(clearance.totalUsd), "USD")}
                    </span>
                  }
                />
                <MetaRow
                  label={tf("balance")}
                  value={
                    <span className="tabular-nums">
                      {formatMoney(Number(clearance.balanceUsd), "USD")}
                    </span>
                  }
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
                  key: "invoiceDate",
                  label: tf("invoiceDate"),
                  width: "w-28",
                  cell: (line) => (
                    <span className="flex h-8 items-center text-sm">
                      {formatDate(line.invoiceDate)}
                    </span>
                  ),
                },
                {
                  key: "prRef",
                  label: tf("prRef"),
                  width: "w-24",
                  cell: (line) => (
                    <span className="flex h-8 items-center text-sm">
                      {line.prRef || "—"}
                    </span>
                  ),
                },
                {
                  key: "purchaser",
                  label: tf("purchaser"),
                  width: "w-28",
                  cell: (line) => (
                    <span className="flex h-8 items-center text-sm">
                      {line.purchaser || "—"}
                    </span>
                  ),
                },
                {
                  key: "description",
                  label: tf("description"),
                  cell: (line) => (
                    <span
                      className="flex min-h-8 items-center py-1 text-sm whitespace-pre-wrap break-words"
                      title={line.description}
                    >
                      {line.description}
                    </span>
                  ),
                },
                {
                  key: "vendor",
                  label: tf("vendor"),
                  width: "w-32",
                  cell: (line) => (
                    <span className="flex h-8 items-center truncate text-sm" title={line.vendor}>
                      {line.vendor}
                    </span>
                  ),
                },
                {
                  key: "campus",
                  label: tf("campus"),
                  width: "w-24",
                  cell: (line) => (
                    <span className="flex h-8 items-center text-sm">
                      {line.campus || "—"}
                    </span>
                  ),
                },
                {
                  key: "division",
                  label: tf("division"),
                  width: "w-24",
                  cell: (line) => (
                    <span className="flex h-8 items-center text-sm">
                      {line.division || "—"}
                    </span>
                  ),
                },
                {
                  key: "department",
                  label: tf("department"),
                  width: "w-28",
                  cell: (line) => (
                    <span className="flex h-8 items-center text-sm">
                      {line.department || "—"}
                    </span>
                  ),
                },
                {
                  key: "qty",
                  label: tf("qty"),
                  width: "w-20",
                  align: "right",
                  cell: (line) => (
                    <span className="flex h-8 items-center justify-end text-sm tabular-nums">
                      {line.qty}
                    </span>
                  ),
                },
                {
                  key: "uom",
                  label: tf("uom"),
                  width: "w-20",
                  cell: (line) => (
                    <span className="flex h-8 items-center text-sm">
                      {line.uom || "—"}
                    </span>
                  ),
                },
                {
                  key: "unitPriceUsd",
                  label: tf("unitPrice"),
                  width: "w-28",
                  align: "right",
                  cell: (line) => (
                    <span className="flex h-8 items-center justify-end text-sm tabular-nums">
                      {formatMoney(Number(line.unitPriceUsd), "USD")}
                    </span>
                  ),
                },
                {
                  key: "discountUsd",
                  label: tf("discount"),
                  width: "w-24",
                  align: "right",
                  cell: (line) => (
                    <span className="flex h-8 items-center justify-end text-sm tabular-nums">
                      {formatMoney(Number(line.discountUsd), "USD")}
                    </span>
                  ),
                },
                {
                  key: "vatUsd",
                  label: tf("vat"),
                  width: "w-24",
                  align: "right",
                  cell: (line) => (
                    <span className="flex h-8 items-center justify-end text-sm tabular-nums">
                      {formatMoney(Number(line.vatUsd), "USD")}
                    </span>
                  ),
                },
                {
                  key: "amountUsd",
                  label: tf("amountUsd"),
                  width: "w-28",
                  align: "right",
                  cell: (line) => (
                    <span className="flex h-8 items-center justify-end text-sm tabular-nums">
                      {formatMoney(Number(line.amountUsd), "USD")}
                    </span>
                  ),
                },
              ]}
              rows={clearance.lines ?? []}
              rowKey={(line: CashClearanceLineResource, index) =>
                line.id || String(index)
              }
              summary={[
                {
                  key: "total",
                  label: tf("totalUsd"),
                  value: formatMoney(Number(clearance.totalUsd), "USD"),
                  strong: true,
                },
              ]}
            />
          </CardContent>
        </Card>

        {approvalRequest && !embedded ? (
          <div className="space-y-4">
            <h2 className="text-sm font-medium">{ta("reviewTitle")}</h2>
            <ApprovalTimeline request={approvalRequest} rounds={rounds} />
          </div>
        ) : null}

        <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>{tn("deleteTitle")}</DialogTitle>
              <DialogDescription>
                {tt.rich("deleteDescription", {
                  name: clearance.code,
                  strong: (chunks) => <strong>{chunks}</strong>,
                })}
              </DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button
                variant="outline"
                onClick={() => setDeleteOpen(false)}
                disabled={deleteMutation.isPending}
              >
                {tt("cancel")}
              </Button>
              <Button
                variant="destructive"
                onClick={() => deleteMutation.mutate(clearance.id)}
                disabled={deleteMutation.isPending}
              >
                {deleteMutation.isPending ? tt("deleting") : tt("confirmDelete")}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <CancelDocumentDialog
          open={cancelOpen}
          code={clearance.code}
          title={tn("cancelDocumentTitle")}
          pending={cancelMutation.isPending}
          onOpenChange={setCancelOpen}
          onConfirm={() => cancelMutation.mutate(clearance.id)}
        />
      </div>

      {print.dialog}
    </>
  );
}

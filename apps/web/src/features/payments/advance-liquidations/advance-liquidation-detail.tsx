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
  getPaymentsAdvanceLiquidationsPrintUrl,
  paymentsAdvanceLiquidationsDestroy,
  paymentsAdvanceLiquidationsShow,
  paymentsAdvanceLiquidationsStatus,
} from "@/lib/api/advance-liquidation/advance-liquidation";
import type { AdvanceLiquidationResource } from "@/lib/api/model/advanceLiquidationResource";
import type { AdvanceLiquidationLineResource } from "@/lib/api/model/advanceLiquidationLineResource";
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
import { AdvanceLiquidationStatusBadge } from "./components/advance-liquidation-status-badge";
import { CancelDocumentDialog } from "../components/cancel-document-dialog";
import { AdvanceLiquidationDetailSkeleton } from "./components/advance-liquidation-detail-skeleton";

const ADVANCE_LIQUIDATION_SUBJECT = "advance_liquidation";

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

interface AdvanceLiquidationDetailProps {
  liquidationId: string;
  /** Approval page embed: no page chrome, just the document. */
  embedded?: boolean;
}

export function AdvanceLiquidationDetail({
  liquidationId,
  embedded = false,
}: AdvanceLiquidationDetailProps) {
  const router = useRouter();
  const tf = useTranslations("payments.form");
  const tt = useTranslations("payments.table");
  const tn = useTranslations("payments.advanceLiquidations");
  const tabs = useTranslations("payments.tabs");
  const ta = useTranslations("approvals.form");
  const tad = useTranslations("approvals.detail");
  const meQuery = useMe();
  const qc = useQueryClient();
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [cancelOpen, setCancelOpen] = useState(false);

  const showQuery = useQuery({
    queryKey: ["advance-liquidations", "detail", liquidationId],
    queryFn: async (): Promise<AdvanceLiquidationResource> =>
      unwrap(
        await paymentsAdvanceLiquidationsShow(liquidationId, withAuth())
      ),
  });

  // Shared key/queryFn with the form and approval-detail: every approval
  // round for this liquidation, newest first.
  const roundsQuery = useQuery({
    queryKey: [
      "approvals",
      "requests",
      "subject",
      ADVANCE_LIQUIDATION_SUBJECT,
      liquidationId,
    ],
    queryFn: (): Promise<ApprovalRequestView[]> =>
      fetchApprovalRounds(ADVANCE_LIQUIDATION_SUBJECT, liquidationId),
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(
        await paymentsAdvanceLiquidationsDestroy(id, withAuth())
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["advance-liquidations"] });
      toast.success(tn("deleted"));
      router.push("/payments/advance-liquidations");
    },
  });

  const cancelMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(
        await paymentsAdvanceLiquidationsStatus(
          id,
          { status: "cancelled" },
          withAuth()
        )
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["advance-liquidations"] });
      toast.success(tn("cancelled"));
      setCancelOpen(false);
    },
  });

  // The backend renders the official sheet (Blade → PDF); the dialog only
  // fetches and previews it.
  const print = usePrintPreview();

  const liquidation = showQuery.data ?? null;
  const rounds = roundsQuery.data ?? [];
  const approvalRequest = rounds[0] ?? null;
  const status = liquidation?.status ?? null;

  // The submit panel only invalidates the approvals keys, so a just-submitted
  // round (pending while the locally cached document still reads draft or
  // returned) triggers one refetch of this document.
  const needsStatusRefresh =
    approvalRequest != null &&
    approvalRequest.status === "pending" &&
    (status === "draft" || status === "returned");
  useEffect(() => {
    if (needsStatusRefresh) {
      qc.invalidateQueries({ queryKey: ["advance-liquidations"] });
    }
  }, [needsStatusRefresh, qc]);

  if (showQuery.isPending) {
    return <AdvanceLiquidationDetailSkeleton />;
  }

  if (showQuery.isError || !liquidation) {
    if (embedded) {
      return <p className="text-sm text-destructive">{tn("loadFailed")}</p>;
    }

    return (
      <div className="mt-1 min-w-0 space-y-4">
        <Button
          variant="outline"
          onClick={() => router.push("/payments/advance-liquidations")}
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
    liquidation.createdById != null &&
    meQuery.data != null &&
    Number(liquidation.createdById) === meQuery.data.id;
  // Mirrors the API: payments.manage AND owner; editable while draft or
  // returned, deletable only while a draft.
  const canAct = canManage && isOwner;
  const canEdit = canAct && ["draft", "returned"].includes(liquidation.status);
  const canCancel = canAct && liquidation.status === "draft";
  const canDelete = canAct && liquidation.status === "draft";

  return (
    <>
      <div className="mt-1 min-w-0 space-y-6">
        {embedded ? null : (
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <Button
                variant="outline"
                size="icon"
                onClick={() => router.push("/payments/advance-liquidations")}
                aria-label={tf("back")}
              >
                <ArrowLeft />
              </Button>
              <div>
                <div className="flex items-center gap-2">
                  <h1 className="text-xl font-semibold tracking-tight">
                    {liquidation.code}
                  </h1>
                  <AdvanceLiquidationStatusBadge status={status} />
                </div>
                <p className="text-sm text-muted-foreground">
                  {tabs("advanceLiquidations")}
                </p>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                onClick={() =>
                  print.open(
                    getPaymentsAdvanceLiquidationsPrintUrl(liquidation.id),
                    liquidation.code,
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
                      `/payments/advance-liquidations/${liquidation.id}/edit`
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
            <CardTitle>{tabs("advanceLiquidations")}</CardTitle>
          </CardHeader>
          <CardContent className="min-w-0 space-y-3">
            <div className="grid grid-cols-1 gap-x-6 gap-y-3 md:grid-cols-2">
              <div className="space-y-3">
                <MetaRow
                  label={tf("liquidationDate")}
                  value={formatDate(liquidation.liquidationDate)}
                />
                <MetaRow
                  label={tf("cashRequest")}
                  value={
                    <span
                      className="font-mono"
                      title={liquidation.cashRequestId}
                    >
                      {liquidation.cashRequestCode ?? liquidation.cashRequestId}
                    </span>
                  }
                />
                <MetaRow
                  label={tf("exchangeRate")}
                  value={
                    liquidation.exchangeRate
                      ? liquidation.exchangeRate.toLocaleString("en-US")
                      : "—"
                  }
                />
                <MetaRow label={tf("campus")} value={liquidation.campus || "—"} />
              </div>
              <div className="space-y-3">
                <MetaRow
                  label={tf("division")}
                  value={liquidation.division || "—"}
                />
                <MetaRow
                  label={tf("department")}
                  value={liquidation.department || "—"}
                />
                <MetaRow
                  label={tf("totalCashAdvance")}
                  value={
                    <span className="tabular-nums">
                      {formatMoney(
                        Number(liquidation.totalCashAdvanceUsd),
                        "USD"
                      )}
                    </span>
                  }
                />
                <MetaRow
                  label={tf("totalActualExpense")}
                  value={
                    <span className="tabular-nums">
                      {formatMoney(
                        Number(liquidation.totalActualExpenseUsd),
                        "USD"
                      )}
                    </span>
                  }
                />
                <MetaRow
                  label={tf("remainingCash")}
                  value={
                    <span className="tabular-nums">
                      {formatMoney(
                        Number(liquidation.remainingCashUsd),
                        "USD"
                      )}
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
                  key: "currency",
                  label: tf("currency"),
                  width: "w-24",
                  cell: (line) => (
                    <span className="flex h-8 items-center text-sm">
                      {line.currency || "USD"}
                    </span>
                  ),
                },
                {
                  key: "amount",
                  label: tf("amount"),
                  width: "w-32",
                  align: "right",
                  cell: (line) => (
                    <span className="flex h-8 items-center justify-end text-sm tabular-nums">
                      {formatMoney(
                        Number(line.amount),
                        line.currency || "USD"
                      )}
                    </span>
                  ),
                },
                {
                  key: "amountUsd",
                  label: tf("amountUsd"),
                  width: "w-32",
                  align: "right",
                  cell: (line) => (
                    <span className="flex h-8 items-center justify-end text-sm tabular-nums">
                      {formatMoney(Number(line.amountUsd), "USD")}
                    </span>
                  ),
                },
              ]}
              rows={liquidation.lines ?? []}
              rowKey={(line: AdvanceLiquidationLineResource, index) =>
                line.id || String(index)
              }
              summary={[
                {
                  key: "totalActualExpense",
                  label: tf("totalActualExpense"),
                  value: formatMoney(
                    Number(liquidation.totalActualExpenseUsd),
                    "USD"
                  ),
                  strong: true,
                },
                {
                  key: "remainingCash",
                  label: tf("remainingCash"),
                  value: formatMoney(
                    Number(liquidation.remainingCashUsd),
                    "USD"
                  ),
                },
              ]}
            />

            <div className="mt-3">
              <MetaRow label={tf("remark")} value={liquidation.remark || "—"} />
            </div>
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
                  name: liquidation.code,
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
                onClick={() => deleteMutation.mutate(liquidation.id)}
                disabled={deleteMutation.isPending}
              >
                {deleteMutation.isPending ? tt("deleting") : tt("confirmDelete")}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <CancelDocumentDialog
          open={cancelOpen}
          code={liquidation.code}
          title={tn("cancelDocumentTitle")}
          pending={cancelMutation.isPending}
          onOpenChange={setCancelOpen}
          onConfirm={() => cancelMutation.mutate(liquidation.id)}
        />
      </div>

      {print.dialog}
    </>
  );
}

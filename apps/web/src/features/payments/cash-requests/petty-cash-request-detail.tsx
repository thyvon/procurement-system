"use client";

import { useEffect, useState, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { ArrowLeft, Pencil, Printer, Trash2 } from "lucide-react";
import { unwrap, withAuth } from "@/lib/api-client";
import { formatDate, formatDateTime, formatMoney } from "@/lib/utils";
import type { CashRequestResource } from "@/lib/api/model/cashRequestResource";
import {
  paymentsPettyCashRequestsDestroy,
  paymentsPettyCashRequestsShow,
} from "@/lib/api/petty-cash-request/petty-cash-request";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { LineItemsTable } from "@/components/line-items-table";
import {
  fetchApprovalRounds,
  type ApprovalRequestView,
} from "@/features/approvals/approval-types";
import { ApprovalTimeline } from "@/features/approvals/components/approval-timeline";
import { CashRequestStatusBadge } from "../components/cash-request-status-badge";
import { CashRequestDetailSkeleton } from "../components/cash-request-detail-skeleton";
import { CashRequestPrint } from "../components/cash-request-print";
import { approvalSubjectType, typeLabel } from "../cash-request-helpers";
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

interface PettyCashRequestDetailProps {
  pettyCashRequestId: string;
  /** Approval page embed: no page chrome, just the document + print sheet. */
  embedded?: boolean;
}

export function PettyCashRequestDetail({
  pettyCashRequestId,
  embedded = false,
}: PettyCashRequestDetailProps) {
  const router = useRouter();
  const tf = useTranslations("payments.form");
  const tc = useTranslations("payments.pettyCashRequests");
  const tt = useTranslations("payments.table");
  // No payments.* keys for these three: the shared breadcrumb word ("Details"),
  // the purchase-order audit labels and the print button wording are the
  // closest existing keys.
  const tb = useTranslations("breadcrumb");
  const tpo = useTranslations("purchaseOrders");
  const tnav = useTranslations("nav");
  const ta = useTranslations("approvals.form");
  const meQuery = useMe();
  const qc = useQueryClient();
  const [deleteOpen, setDeleteOpen] = useState(false);

  const showQuery = useQuery({
    queryKey: ["petty-cash-requests", "detail", pettyCashRequestId],
    queryFn: async (): Promise<CashRequestResource> =>
      unwrap(await paymentsPettyCashRequestsShow(pettyCashRequestId, withAuth())),
  });

  // Shared key/queryFn with approval-detail: every approval round for this
  // document, newest first — the print sheet signs off on the newest one.
  const subjectType = approvalSubjectType(showQuery.data?.type ?? "");
  const roundsQuery = useQuery({
    queryKey: [
      "approvals",
      "requests",
      "subject",
      subjectType,
      pettyCashRequestId,
    ],
    queryFn: (): Promise<ApprovalRequestView[]> =>
      fetchApprovalRounds(subjectType, pettyCashRequestId),
    enabled: Boolean(showQuery.data),
  });

  // The shared submit panel only invalidates ["approvals"]; this document's
  // own status lives under ["petty-cash-requests"], so re-read the server
  // state whenever the newest round changes (submit → in_review, decision → …).
  const latestRound = roundsQuery.data?.[0] ?? null;
  useEffect(() => {
    if (latestRound) {
      qc.invalidateQueries({ queryKey: ["petty-cash-requests"] });
    }
  }, [latestRound, qc]);

  const deleteMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(await paymentsPettyCashRequestsDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["petty-cash-requests"] });
      setDeleteOpen(false);
      toast.success(tc("deleted"));
      router.push("/payments/petty-cash-requests");
    },
  });

  if (showQuery.isPending) {
    return <CashRequestDetailSkeleton />;
  }

  if (showQuery.isError || !showQuery.data) {
    if (embedded) {
      return <p className="text-sm text-destructive">{tc("loadFailed")}</p>;
    }

    return (
      <div className="mt-1 min-w-0 space-y-4">
        <Button
          variant="outline"
          onClick={() => router.push("/payments/petty-cash-requests")}
        >
          {tf("back")}
        </Button>
        <p className="text-sm text-destructive">{tc("loadFailed")}</p>
      </div>
    );
  }

  const cashRequest = showQuery.data;
  const status = cashRequest.status;
  const permissions = (meQuery.data?.permissions ?? []) as string[];
  const canManage = permissions.includes("payments.manage");
  const isOwner =
    cashRequest.createdById != null &&
    meQuery.data != null &&
    Number(cashRequest.createdById) === meQuery.data.id;
  // Mirrors the API: payments.manage AND owner; editable while draft or
  // returned, deletable only while a draft.
  const canAct = canManage && isOwner;
  const canEdit = canAct && ["draft", "returned"].includes(status);
  const canDelete = canAct && status === "draft";

  const handlePrint = () => {
    const originalTitle = document.title;
    document.title = `${cashRequest.code}-cash-request`;
    try {
      window.print();
    } finally {
      document.title = originalTitle;
    }
  };

  const banner =
    status === "in_review" ? (
      <div className="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm">
        {ta("inReview")}
      </div>
    ) : status === "rejected" ? (
      <div className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm">
        {ta("rejectedState")}
      </div>
    ) : status === "returned" ? (
      <div className="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm">
        {ta("returnedState")}
      </div>
    ) : null;

  return (
    <>
      <div className="mt-1 min-w-0 space-y-6 print:hidden">
        {embedded ? null : (
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <Button
                variant="outline"
                size="icon"
                onClick={() => router.push("/payments/petty-cash-requests")}
                aria-label={tf("back")}
              >
                <ArrowLeft />
              </Button>
              <div>
                <div className="flex items-center gap-2">
                  <h1 className="text-xl font-semibold tracking-tight">
                    {cashRequest.code}
                  </h1>
                  <CashRequestStatusBadge status={status} />
                </div>
                <p className="text-sm text-muted-foreground">
                  {tnav("pettyCashRequests")}
                </p>
              </div>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <Button
                variant="outline"
                onClick={handlePrint}
                disabled={roundsQuery.isPending}
              >
                <Printer />
                {tpo("detail.print")}
              </Button>
              {latestRound ? (
                <Button
                  variant="outline"
                  onClick={() => router.push(`/approvals/${latestRound.id}`)}
                >
                  {ta("viewRequest")}
                </Button>
              ) : null}
              {canDelete ? (
                <Button variant="outline" onClick={() => setDeleteOpen(true)}>
                  <Trash2 />
                  {tt("delete")}
                </Button>
              ) : null}
              {canEdit ? (
                <Button
                  onClick={() =>
                    router.push(
                      `/payments/petty-cash-requests/${cashRequest.id}/edit`
                    )
                  }
                >
                  <Pencil />
                  {tt("edit")}
                </Button>
              ) : null}
            </div>
          </div>
        )}

        {embedded && status === "in_review" ? null : banner}

        <Card size="sm" className="min-w-0">
          <CardHeader>
            <CardTitle>{tb("details")}</CardTitle>
          </CardHeader>
          <CardContent className="min-w-0 space-y-3">
            {/* Mirrors the form's head card — left column: identity and org,
                right column: audit info. */}
            <div className="grid grid-cols-1 gap-x-6 gap-y-3 md:grid-cols-2">
              <div className="space-y-3">
                <MetaRow
                  label={tf("code")}
                  value={<span className="font-mono">{cashRequest.code}</span>}
                />
                <MetaRow
                  label={tf("type")}
                  value={typeLabel(tf, cashRequest.type) || "—"}
                />
                <MetaRow
                  label={tf("requestDate")}
                  value={formatDate(cashRequest.requestDate)}
                />
                <MetaRow label={tf("campus")} value={cashRequest.campus} />
                <MetaRow label={tf("division")} value={cashRequest.division} />
                <MetaRow label={tf("department")} value={cashRequest.department} />
                <MetaRow
                  label={tf("exchangeRate")}
                  value={cashRequest.exchangeRate ?? "—"}
                />
                <MetaRow
                  label={tf("totalUsd")}
                  value={
                    <span className="tabular-nums">
                      {formatMoney(Number(cashRequest.totalUsd ?? 0), "USD")}
                    </span>
                  }
                />
              </div>

              <div className="space-y-3">
                <MetaRow
                  label={tpo("columns.createdBy")}
                  value={
                    <>
                      {cashRequest.createdBy ?? "—"}
                      {cashRequest.createdByPosition ? (
                        <span className="block text-muted-foreground">
                          {cashRequest.createdByPosition}
                        </span>
                      ) : null}
                    </>
                  }
                />
                <MetaRow
                  label={tpo("columns.date")}
                  value={formatDateTime(cashRequest.updatedAt)}
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
                  key: "currency",
                  label: tf("currency"),
                  width: "w-24",
                  cell: (line) => (
                    <span className="flex h-8 items-center text-sm">
                      {line.currency}
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
                      {formatMoney(Number(line.amount ?? 0), line.currency)}
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
                      {formatMoney(Number(line.amountUsd ?? 0), "USD")}
                    </span>
                  ),
                },
              ]}
              rows={cashRequest.lines ?? []}
              rowKey={(line) => line.id}
              summary={[
                {
                  key: "total",
                  label: tf("totalUsd"),
                  value: formatMoney(Number(cashRequest.totalUsd ?? 0), "USD"),
                  strong: true,
                },
              ]}
            />

            <div className="mt-3">
              <MetaRow label={tf("remark")} value={cashRequest.remark || "—"} />
            </div>
          </CardContent>
        </Card>

        {latestRound && !embedded ? (
          <div className="space-y-4">
            <h2 className="text-sm font-medium">{ta("reviewTitle")}</h2>
            <ApprovalTimeline
              request={latestRound}
              rounds={roundsQuery.data ?? []}
            />
          </div>
        ) : null}
      </div>

      <CashRequestPrint cashRequest={cashRequest} approvalRequest={latestRound} />

      <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{tc("deleteTitle")}</DialogTitle>
            <DialogDescription>
              {tt.rich("deleteDescription", {
                name: cashRequest.code,
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
              onClick={() => deleteMutation.mutate(cashRequest.id)}
              disabled={deleteMutation.isPending}
            >
              {deleteMutation.isPending ? tt("deleting") : tt("confirmDelete")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table";
import { Ban, Eye, MoreHorizontal, Pencil, Plus, Trash2 } from "lucide-react";
import {
  paymentsCashClearancesDestroy,
  paymentsCashClearancesIndex,
  paymentsCashClearancesStatus,
} from "@/lib/api/cash-clearance/cash-clearance";
import type { CashClearanceResource } from "@/lib/api/model/cashClearanceResource";
import type { PaymentsCashClearancesIndexStatus } from "@/lib/api/model/paymentsCashClearancesIndexStatus";
import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/ui/data-table";
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { unwrap, unwrapWithMeta, withAuth } from "@/lib/api-client";
import { formatDate, formatMoney } from "@/lib/utils";
import { type DataTableFeatures } from "@/components/ui/data-table-features";
import { useMe } from "@/hooks/use-me";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { CashClearanceStatusBadge } from "./components/cash-clearance-status-badge";
import { CancelDocumentDialog } from "../components/cancel-document-dialog";

type CashClearanceRow = {
  id: string;
  code: string;
  cashRequestId: string;
  cashRequestCode: string | null;
  clearanceDate: string | null;
  requestAmountUsd: number;
  totalUsd: number;
  balanceUsd: number;
  status: string;
  createdById: number | null;
};

type CashClearancesPage = {
  data: CashClearanceRow[];
  meta?: { page: number; perPage: number; total: number };
};

function toRow(resource: CashClearanceResource): CashClearanceRow {
  return {
    id: resource.id,
    code: resource.code,
    cashRequestId: resource.cashRequestId,
    cashRequestCode: resource.cashRequestCode ?? null,
    clearanceDate: resource.clearanceDate,
    requestAmountUsd: resource.requestAmountUsd,
    totalUsd: resource.totalUsd,
    balanceUsd: resource.balanceUsd,
    status: resource.status,
    createdById: resource.createdById,
  };
}

const columnHelper = createColumnHelper<DataTableFeatures, CashClearanceRow>();

function useCashClearanceColumns({
  canManage,
  currentUserId,
  onViewRequest,
  onEditRequest,
  onCancelRequest,
  onDeleteRequest,
}: {
  canManage: boolean;
  currentUserId: number | null;
  onViewRequest: (clearance: CashClearanceRow) => void;
  onEditRequest: (clearance: CashClearanceRow) => void;
  onCancelRequest: (clearance: CashClearanceRow) => void;
  onDeleteRequest: (clearance: CashClearanceRow) => void;
}): ColumnDef<DataTableFeatures, CashClearanceRow>[] {
  const tc = useTranslations("payments.columns");
  const tt = useTranslations("payments.table");

  return [
    columnHelper.accessor("code", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("code")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("code")}</span>
      ),
    }),
    columnHelper.accessor("cashRequestId", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("cashRequest")} />
      ),
      cell: ({ row }) => (
        <span
          className="block max-w-40 truncate font-mono text-xs"
          title={row.original.cashRequestId}
        >
          {row.original.cashRequestCode ?? row.original.cashRequestId}
        </span>
      ),
    }),
    columnHelper.accessor("clearanceDate", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("clearanceDate")} />
      ),
      cell: ({ row }) => (
        <span className="text-muted-foreground">
          {formatDate(row.getValue("clearanceDate"))}
        </span>
      ),
    }),
    columnHelper.accessor("requestAmountUsd", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("requestAmount")} />
      ),
      cell: ({ row }) => (
        <span className="tabular-nums">
          {formatMoney(Number(row.getValue("requestAmountUsd")), "USD")}
        </span>
      ),
    }),
    columnHelper.accessor("totalUsd", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("totalUsd")} />
      ),
      cell: ({ row }) => (
        <span className="tabular-nums">
          {formatMoney(Number(row.getValue("totalUsd")), "USD")}
        </span>
      ),
    }),
    columnHelper.accessor("balanceUsd", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("balance")} />
      ),
      cell: ({ row }) => (
        <span className="tabular-nums">
          {formatMoney(Number(row.getValue("balanceUsd")), "USD")}
        </span>
      ),
    }),
    columnHelper.accessor("status", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("status")} />
      ),
      cell: ({ row }) => (
        <CashClearanceStatusBadge status={row.getValue("status") as string} />
      ),
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => {
        const clearance = row.original;
        // Server rule: payments.manage AND owner may write (own documents
        // only); edit while the API still accepts it, delete while a draft.
        const isOwner =
          clearance.createdById !== null &&
          currentUserId !== null &&
          Number(clearance.createdById) === currentUserId;
        const canAct = canManage && isOwner;
        const canEdit = canAct && ["draft", "returned"].includes(clearance.status);
        const canCancel = canAct && clearance.status === "draft";
        const canDelete = canAct && clearance.status === "draft";
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onViewRequest(clearance)}>
                  <Eye className="mr-2 size-4" />
                  {tt("view")}
                </DropdownMenuItem>
                {canEdit ? (
                  <DropdownMenuItem onClick={() => onEditRequest(clearance)}>
                    <Pencil className="mr-2 size-4" />
                    {tt("edit")}
                  </DropdownMenuItem>
                ) : null}
                {canCancel ? (
                  <DropdownMenuItem onClick={() => onCancelRequest(clearance)}>
                    <Ban className="mr-2 size-4" />
                    {tt("cancelDocument")}
                  </DropdownMenuItem>
                ) : null}
                {canDelete ? (
                  <DropdownMenuItem
                    className="text-destructive"
                    onClick={() => onDeleteRequest(clearance)}
                  >
                    <Trash2 className="mr-2 size-4" />
                    {tt("delete")}
                  </DropdownMenuItem>
                ) : null}
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        );
      },
    }),
  ] as ColumnDef<DataTableFeatures, CashClearanceRow>[];
}

export function CashClearancesTab() {
  const tn = useTranslations("payments.cashClearances");
  const tt = useTranslations("payments.table");
  const router = useRouter();
  const qc = useQueryClient();
  const meQuery = useMe();
  const permissions = (meQuery.data?.permissions ?? []) as string[];
  const canManage = permissions.includes("payments.manage");
  const currentUserId = meQuery.data?.id ?? null;
  const [deleteTarget, setDeleteTarget] = useState<CashClearanceRow | null>(null);
  const [cancelTarget, setCancelTarget] = useState<CashClearanceRow | null>(null);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [page, setPage] = useState(0);
  const [perPage, setPerPage] = useState(20);

  const debouncedSearch = useDebouncedValue(search, 300);

  const deleteMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(await paymentsCashClearancesDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["cash-clearances"] });
      setDeleteTarget(null);
      toast.success(tn("deleted"));
    },
  });

  const cancelMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(
        await paymentsCashClearancesStatus(id, { status: "cancelled" }, withAuth())
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["cash-clearances"] });
      setCancelTarget(null);
      toast.success(tn("cancelled"));
    },
  });

  const query = useQuery({
    queryKey: ["cash-clearances", debouncedSearch, statusFilter, page, perPage],
    queryFn: async (): Promise<CashClearancesPage> => {
      const response = await paymentsCashClearancesIndex(
        {
          search: debouncedSearch || undefined,
          status:
            statusFilter === "all"
              ? undefined
              : (statusFilter as PaymentsCashClearancesIndexStatus),
          page: page + 1,
          per_page: perPage,
        },
        withAuth()
      );
      const envelope = unwrapWithMeta<
        unknown,
        { page: number; perPage: number; total: number }
      >(response);
      const resources = (envelope.data ?? []) as CashClearanceResource[];
      return {
        data: resources.map(toRow),
        meta: envelope.meta,
      };
    },
    placeholderData: keepPreviousData,
  });

  const columns = useCashClearanceColumns({
    canManage,
    currentUserId,
    onViewRequest: (clearance) => router.push(`/payments/cash-clearances/${clearance.id}`),
    onEditRequest: (clearance) =>
      router.push(`/payments/cash-clearances/${clearance.id}/edit`),
    onCancelRequest: setCancelTarget,
    onDeleteRequest: setDeleteTarget,
  });

  if (query.isPending) {
    return <DataTableSkeleton columns={8} actions={2} />;
  }

  const meta = query.data?.meta ?? { page: page + 1, perPage, total: 0 };
  const rows = query.data?.data ?? [];

  return (
    <div className="flex min-h-0 min-w-0 flex-1 flex-col">
      <DataTable
        columns={columns}
        data={rows}
        loading={query.isFetching}
        serverSearch={{
          value: search,
          onChange: (value) => {
            setSearch(value);
            setPage(0);
          },
        }}
        serverPagination={{
          page,
          perPage,
          total: meta.total,
          onPaginationChange: (next) => {
            setPage(next.page);
            setPerPage(next.perPage);
          },
        }}
        filterColumn="status"
        filterValue={statusFilter}
        onFilterChange={(value) => {
          setStatusFilter(value);
          setPage(0);
        }}
        filterOptions={[
          { label: tt("draft"), value: "draft" },
          { label: tt("inReview"), value: "in_review" },
          { label: tt("approved"), value: "approved" },
          { label: tt("rejected"), value: "rejected" },
          { label: tt("returned"), value: "returned" },
          { label: tt("cancelled"), value: "cancelled" },
        ]}
        filterPlaceholder={tt("allStatuses")}
        searchPlaceholder={tt("searchPlaceholder")}
        toolbar={
          canManage ? (
            <Button type="button" onClick={() => router.push("/payments/cash-clearances/new")}>
              <Plus className="mr-2 size-4" />
              {tn("newTitle")}
            </Button>
          ) : null
        }
      />

      <Dialog
        open={!!deleteTarget}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{tn("deleteTitle")}</DialogTitle>
            <DialogDescription>
              {tt.rich("deleteDescription", {
                name: deleteTarget?.code ?? "",
                strong: (chunks) => <strong>{chunks}</strong>,
              })}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDeleteTarget(null)}
              disabled={deleteMutation.isPending}
            >
              {tt("cancel")}
            </Button>
            <Button
              variant="destructive"
              onClick={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
              disabled={deleteMutation.isPending}
            >
              {deleteMutation.isPending ? tt("deleting") : tt("confirmDelete")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <CancelDocumentDialog
        open={!!cancelTarget}
        code={cancelTarget?.code ?? null}
        title={tn("cancelDocumentTitle")}
        pending={cancelMutation.isPending}
        onOpenChange={(open) => !open && setCancelTarget(null)}
        onConfirm={() => cancelTarget && cancelMutation.mutate(cancelTarget.id)}
      />
    </div>
  );
}

"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table";
import { Eye, MoreHorizontal, Pencil, Plus, Trash2 } from "lucide-react";
import {
  paymentsAdvanceLiquidationsDestroy,
  paymentsAdvanceLiquidationsIndex,
} from "@/lib/api/advance-liquidation/advance-liquidation";
import type { AdvanceLiquidationResource } from "@/lib/api/model/advanceLiquidationResource";
import type { PaymentsAdvanceLiquidationsIndexStatus } from "@/lib/api/model/paymentsAdvanceLiquidationsIndexStatus";
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
import { AdvanceLiquidationStatusBadge } from "./components/advance-liquidation-status-badge";

type AdvanceLiquidationRow = {
  id: string;
  code: string;
  cashRequestId: string;
  cashRequestCode: string | null;
  liquidationDate: string | null;
  campus: string;
  division: string;
  department: string;
  totalCashAdvanceUsd: number;
  totalActualExpenseUsd: number;
  remainingCashUsd: number;
  status: string;
  createdById: number | null;
};

type AdvanceLiquidationsPage = {
  data: AdvanceLiquidationRow[];
  meta?: { page: number; perPage: number; total: number };
};

function toRow(resource: AdvanceLiquidationResource): AdvanceLiquidationRow {
  return {
    id: resource.id,
    code: resource.code,
    cashRequestId: resource.cashRequestId,
    cashRequestCode: resource.cashRequestCode ?? null,
    liquidationDate: resource.liquidationDate,
    campus: resource.campus,
    division: resource.division,
    department: resource.department,
    totalCashAdvanceUsd: resource.totalCashAdvanceUsd,
    totalActualExpenseUsd: resource.totalActualExpenseUsd,
    remainingCashUsd: resource.remainingCashUsd,
    status: resource.status,
    createdById: resource.createdById,
  };
}

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs);
    return () => clearTimeout(timer);
  }, [value, delayMs]);

  return debounced;
}

const columnHelper = createColumnHelper<DataTableFeatures, AdvanceLiquidationRow>();

function useAdvanceLiquidationColumns({
  canManage,
  currentUserId,
  onViewRequest,
  onEditRequest,
  onDeleteRequest,
}: {
  canManage: boolean;
  currentUserId: number | null;
  onViewRequest: (liquidation: AdvanceLiquidationRow) => void;
  onEditRequest: (liquidation: AdvanceLiquidationRow) => void;
  onDeleteRequest: (liquidation: AdvanceLiquidationRow) => void;
}): ColumnDef<DataTableFeatures, AdvanceLiquidationRow>[] {
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
    columnHelper.accessor("liquidationDate", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("liquidationDate")} />
      ),
      cell: ({ row }) => (
        <span className="text-muted-foreground">
          {formatDate(row.getValue("liquidationDate"))}
        </span>
      ),
    }),
    columnHelper.accessor("campus", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("campus")} />
      ),
      cell: ({ row }) => row.getValue("campus") || "—",
    }),
    columnHelper.accessor("division", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("division")} />
      ),
      cell: ({ row }) => row.getValue("division") || "—",
    }),
    columnHelper.accessor("department", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("department")} />
      ),
      cell: ({ row }) => row.getValue("department") || "—",
    }),
    columnHelper.accessor("totalCashAdvanceUsd", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("totalCashAdvance")} />
      ),
      cell: ({ row }) => (
        <span className="tabular-nums">
          {formatMoney(Number(row.getValue("totalCashAdvanceUsd")), "USD")}
        </span>
      ),
    }),
    columnHelper.accessor("totalActualExpenseUsd", {
      header: ({ column }) => (
        <DataTableColumnHeader
          column={column}
          title={tc("totalActualExpense")}
        />
      ),
      cell: ({ row }) => (
        <span className="tabular-nums">
          {formatMoney(Number(row.getValue("totalActualExpenseUsd")), "USD")}
        </span>
      ),
    }),
    columnHelper.accessor("remainingCashUsd", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("remainingCash")} />
      ),
      cell: ({ row }) => (
        <span className="tabular-nums">
          {formatMoney(Number(row.getValue("remainingCashUsd")), "USD")}
        </span>
      ),
    }),
    columnHelper.accessor("status", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("status")} />
      ),
      cell: ({ row }) => (
        <AdvanceLiquidationStatusBadge
          status={row.getValue("status") as string}
        />
      ),
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => {
        const liquidation = row.original;
        // Server rule: manage AND owner may write (own documents only);
        // edit/delete only while the API still accepts them (draft).
        const isOwner =
          liquidation.createdById !== null &&
          currentUserId !== null &&
          Number(liquidation.createdById) === currentUserId;
        const canAct = canManage && isOwner;
        const canEdit = canAct && liquidation.status === "draft";
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onViewRequest(liquidation)}>
                  <Eye className="mr-2 size-4" />
                  {tt("view")}
                </DropdownMenuItem>
                {canEdit ? (
                  <DropdownMenuItem onClick={() => onEditRequest(liquidation)}>
                    <Pencil className="mr-2 size-4" />
                    {tt("edit")}
                  </DropdownMenuItem>
                ) : null}
                {canEdit ? (
                  <DropdownMenuItem
                    className="text-destructive"
                    onClick={() => onDeleteRequest(liquidation)}
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
  ] as ColumnDef<DataTableFeatures, AdvanceLiquidationRow>[];
}

export function AdvanceLiquidationsTab() {
  const tn = useTranslations("payments.advanceLiquidations");
  const tt = useTranslations("payments.table");
  const router = useRouter();
  const qc = useQueryClient();
  const meQuery = useMe();
  const permissions = (meQuery.data?.permissions ?? []) as string[];
  const canManage = permissions.includes("payments.manage");
  const currentUserId = meQuery.data?.id ?? null;
  const [deleteTarget, setDeleteTarget] = useState<AdvanceLiquidationRow | null>(
    null
  );
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [page, setPage] = useState(0);
  const [perPage, setPerPage] = useState(20);

  const debouncedSearch = useDebouncedValue(search, 300);

  const deleteMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(
        await paymentsAdvanceLiquidationsDestroy(id, withAuth())
      ),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["advance-liquidations"] });
      setDeleteTarget(null);
      toast.success(tn("deleted"));
    },
  });

  const query = useQuery({
    queryKey: [
      "advance-liquidations",
      debouncedSearch,
      statusFilter,
      page,
      perPage,
    ],
    queryFn: async (): Promise<AdvanceLiquidationsPage> => {
      const response = await paymentsAdvanceLiquidationsIndex(
        {
          search: debouncedSearch || undefined,
          status:
            statusFilter === "all"
              ? undefined
              : (statusFilter as PaymentsAdvanceLiquidationsIndexStatus),
          page: page + 1,
          per_page: perPage,
        },
        withAuth()
      );
      const envelope = unwrapWithMeta<
        unknown,
        { page: number; perPage: number; total: number }
      >(response);
      const resources = (envelope.data ?? []) as AdvanceLiquidationResource[];
      return {
        data: resources.map(toRow),
        meta: envelope.meta,
      };
    },
    placeholderData: keepPreviousData,
  });

  const columns = useAdvanceLiquidationColumns({
    canManage,
    currentUserId,
    onViewRequest: (liquidation) =>
      router.push(`/payments/advance-liquidations/${liquidation.id}`),
    onEditRequest: (liquidation) =>
      router.push(`/payments/advance-liquidations/${liquidation.id}/edit`),
    onDeleteRequest: setDeleteTarget,
  });

  if (query.isPending) {
    return <DataTableSkeleton columns={11} actions={2} />;
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
        onFilterChange={setStatusFilter}
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
            <Button
              type="button"
              onClick={() =>
                router.push("/payments/advance-liquidations/new")
              }
            >
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
    </div>
  );
}

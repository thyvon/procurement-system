"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table";
import { Eye, MoreHorizontal, Pencil, Plus, Trash2 } from "lucide-react";
import {
  paymentsCashRequestsDestroy,
  paymentsCashRequestsIndex,
} from "@/lib/api/cash-request/cash-request";
import type { CashRequestResource } from "@/lib/api/model/cashRequestResource";
import type { PaymentsCashRequestsIndexStatus } from "@/lib/api/model/paymentsCashRequestsIndexStatus";
import type { PaymentsCashRequestsIndexType } from "@/lib/api/model/paymentsCashRequestsIndexType";
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
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { unwrap, unwrapWithMeta, withAuth } from "@/lib/api-client";
import { formatDate, formatMoney } from "@/lib/utils";
import { type DataTableFeatures } from "@/components/ui/data-table-features";
import { useMe } from "@/hooks/use-me";
import { CashRequestStatusBadge } from "./components/cash-request-status-badge";
import { typeLabel, typeOptions } from "./cash-request-helpers";

type CashRequestRow = {
  id: string;
  code: string;
  type: string;
  campus: string;
  division: string;
  department: string;
  requestDate: string | null;
  totalUsd: number;
  status: string;
  updatedAt: string | null;
  createdById: number | null;
};

type CashRequestsPage = {
  data: CashRequestRow[];
  meta?: { page: number; perPage: number; total: number };
};

function toRow(resource: CashRequestResource): CashRequestRow {
  return {
    id: resource.id,
    code: resource.code,
    type: resource.type,
    campus: resource.campus,
    division: resource.division,
    department: resource.department,
    requestDate: resource.requestDate,
    totalUsd: resource.totalUsd,
    status: resource.status,
    updatedAt: resource.updatedAt,
    createdById: resource.createdById ?? null,
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

const columnHelper = createColumnHelper<DataTableFeatures, CashRequestRow>();

function useCashRequestColumns({
  canManage,
  currentUserId,
  onViewRequest,
  onEditRequest,
  onDeleteRequest,
}: {
  canManage: boolean;
  currentUserId: number | null;
  onViewRequest: (cashRequest: CashRequestRow) => void;
  onEditRequest: (cashRequest: CashRequestRow) => void;
  onDeleteRequest: (cashRequest: CashRequestRow) => void;
}): ColumnDef<DataTableFeatures, CashRequestRow>[] {
  const tc = useTranslations("payments.columns");
  const tt = useTranslations("payments.table");
  const tf = useTranslations("payments.form");

  return [
    columnHelper.accessor("code", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("code")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("code")}</span>
      ),
    }),
    columnHelper.accessor("type", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("type")} />
      ),
      cell: ({ row }) => {
        const value = row.getValue("type") as string;
        return <span className="text-muted-foreground">{typeLabel(tf, value)}</span>;
      },
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
    columnHelper.accessor("requestDate", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("requestDate")} />
      ),
      cell: ({ row }) => (
        <span className="text-muted-foreground">
          {formatDate(row.getValue("requestDate"))}
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
    columnHelper.accessor("status", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("status")} />
      ),
      cell: ({ row }) => (
        <CashRequestStatusBadge status={row.getValue("status") as string} />
      ),
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => {
        const cashRequest = row.original;
        // Server rule: payments.manage AND owner may write (own documents
        // only); edit while the API still accepts it, delete while a draft.
        const isOwner =
          cashRequest.createdById !== null &&
          currentUserId !== null &&
          Number(cashRequest.createdById) === currentUserId;
        const canAct = canManage && isOwner;
        const canEdit = canAct && ["draft", "returned"].includes(cashRequest.status);
        const canDelete = canAct && cashRequest.status === "draft";
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onViewRequest(cashRequest)}>
                  <Eye className="mr-2 size-4" />
                  {tt("view")}
                </DropdownMenuItem>
                {canEdit ? (
                  <DropdownMenuItem onClick={() => onEditRequest(cashRequest)}>
                    <Pencil className="mr-2 size-4" />
                    {tt("edit")}
                  </DropdownMenuItem>
                ) : null}
                {canDelete ? (
                  <>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                      className="text-destructive"
                      onClick={() => onDeleteRequest(cashRequest)}
                    >
                      <Trash2 className="mr-2 size-4" />
                      {tt("delete")}
                    </DropdownMenuItem>
                  </>
                ) : null}
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        );
      },
    }),
  ] as ColumnDef<DataTableFeatures, CashRequestRow>[];
}

export function CashRequestsTab() {
  const t = useTranslations("payments.cashRequests");
  const tt = useTranslations("payments.table");
  const tf = useTranslations("payments.form");
  // No payments.* key for "All types" — the approvals tray label is the
  // closest existing one (same wording, same locale files).
  const ta = useTranslations("approvals.table");
  const router = useRouter();
  const qc = useQueryClient();
  const meQuery = useMe();
  const permissions = (meQuery.data?.permissions ?? []) as string[];
  const canManage = permissions.includes("payments.manage");
  const currentUserId = meQuery.data?.id ?? null;
  const [deleteTarget, setDeleteTarget] = useState<CashRequestRow | null>(null);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [typeFilter, setTypeFilter] = useState("all");
  const [page, setPage] = useState(0);
  const [perPage, setPerPage] = useState(20);

  const debouncedSearch = useDebouncedValue(search, 300);
  const typeSelectOptions = typeOptions(tf);

  const deleteMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(await paymentsCashRequestsDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["cash-requests"] });
      setDeleteTarget(null);
      toast.success(t("deleted"));
    },
  });

  const query = useQuery({
    queryKey: ["cash-requests", debouncedSearch, statusFilter, typeFilter, page, perPage],
    queryFn: async (): Promise<CashRequestsPage> => {
      const response = await paymentsCashRequestsIndex(
        {
          search: debouncedSearch || undefined,
          status:
            statusFilter === "all"
              ? undefined
              : (statusFilter as PaymentsCashRequestsIndexStatus),
          type:
            typeFilter === "all"
              ? undefined
              : (typeFilter as PaymentsCashRequestsIndexType),
          page: page + 1,
          per_page: perPage,
        },
        withAuth()
      );
      const envelope = unwrapWithMeta<
        unknown,
        { page: number; perPage: number; total: number }
      >(response);
      const resources = (envelope.data ?? []) as CashRequestResource[];
      return {
        data: resources.map(toRow),
        meta: envelope.meta,
      };
    },
    placeholderData: keepPreviousData,
  });

  const columns = useCashRequestColumns({
    canManage,
    currentUserId,
    onViewRequest: (cashRequest) => router.push(`/payments/cash-requests/${cashRequest.id}`),
    onEditRequest: (cashRequest) =>
      router.push(`/payments/cash-requests/${cashRequest.id}/edit`),
    onDeleteRequest: setDeleteTarget,
  });

  if (query.isPending) {
    return <DataTableSkeleton columns={9} actions={2} />;
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
          { label: tt("cleared"), value: "cleared" },
        ]}
        filterPlaceholder={tt("allStatuses")}
        searchPlaceholder={tt("searchPlaceholder")}
        toolbar={
          <div className="flex flex-wrap items-center gap-2">
            <Select
              value={typeFilter}
              onValueChange={(value) => {
                setTypeFilter(value ?? "all");
                setPage(0);
              }}
              items={[
                { value: "all", label: ta("allSubjects") },
                ...typeSelectOptions.map((option) => ({
                  value: option.value,
                  label: option.label,
                })),
              ]}
            >
              <SelectTrigger className="h-8">
                <SelectValue placeholder={ta("allSubjects")} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{ta("allSubjects")}</SelectItem>
                {typeSelectOptions.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {canManage ? (
              <Button type="button" onClick={() => router.push("/payments/cash-requests/new")}>
                <Plus className="mr-2 size-4" />
                {t("newTitle")}
              </Button>
            ) : null}
          </div>
        }
      />

      <Dialog
        open={!!deleteTarget}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("deleteTitle")}</DialogTitle>
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

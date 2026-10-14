"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table";
import { Ban, CheckCircle2, Eye, MoreHorizontal, Pencil, Plus, Trash2 } from "lucide-react";
import {
  invoicesInvoicesDestroy,
  invoicesInvoicesIndex,
  invoicesStatus,
} from "@/lib/api/invoice/invoice";
import type { InvoiceResource } from "@/lib/api/model/invoiceResource";
import type { InvoicesInvoicesIndexStatus } from "@/lib/api/model/invoicesInvoicesIndexStatus";
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
import { unwrap, unwrapWithMeta, withAuth } from "@/lib/api-client";
import { formatDate, formatDateTime, formatMoney } from "@/lib/utils";
import { type DataTableFeatures } from "@/components/ui/data-table-features";
import { useMe } from "@/hooks/use-me";
import { InvoiceStatusBadge } from "./components/invoice-status-badge";

type InvoiceRow = {
  id: string;
  code: string;
  invoiceNumber: string;
  supplierName: string;
  invoiceDate: string | null;
  total: number;
  currency: string;
  status: string;
  updatedAt: string | null;
  createdBy: string | null;
  createdById: number | null;
};

type InvoicesPage = {
  data: InvoiceRow[];
  meta?: { page: number; perPage: number; total: number };
};

function toRow(resource: InvoiceResource): InvoiceRow {
  return {
    id: resource.id,
    code: resource.code,
    invoiceNumber: resource.invoiceNumber,
    supplierName: resource.supplierName,
    invoiceDate: resource.invoiceDate,
    total: resource.total,
    currency: resource.currency,
    status: resource.status,
    updatedAt: resource.updatedAt,
    createdBy: resource.createdBy ?? null,
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

const columnHelper = createColumnHelper<DataTableFeatures, InvoiceRow>();

function useInvoiceColumns({
  canManage,
  currentUserId,
  onViewRequest,
  onEditRequest,
  onRecordRequest,
  onCancelRequest,
  onDeleteRequest,
}: {
  canManage: boolean;
  currentUserId: number | null;
  onViewRequest: (invoice: InvoiceRow) => void;
  onEditRequest: (invoice: InvoiceRow) => void;
  onRecordRequest: (invoice: InvoiceRow) => void;
  onCancelRequest: (invoice: InvoiceRow) => void;
  onDeleteRequest: (invoice: InvoiceRow) => void;
}): ColumnDef<DataTableFeatures, InvoiceRow>[] {
  const tc = useTranslations("invoices.columns");
  const tt = useTranslations("invoices.table");

  return [
    columnHelper.accessor("code", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("code")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("code")}</span>
      ),
    }),
    columnHelper.accessor("invoiceNumber", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("invoiceNumber")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("invoiceNumber")}</span>
      ),
    }),
    columnHelper.accessor("supplierName", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("supplier")} />
      ),
      cell: ({ row }) => row.getValue("supplierName") || "—",
    }),
    columnHelper.accessor("invoiceDate", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("invoiceDate")} />
      ),
      cell: ({ row }) => (
        <span className="text-muted-foreground">
          {formatDate(row.getValue("invoiceDate"))}
        </span>
      ),
    }),
    columnHelper.accessor("total", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("total")} />
      ),
      cell: ({ row }) => (
        <span className="tabular-nums">
          {formatMoney(Number(row.getValue("total")), row.original.currency)}
        </span>
      ),
    }),
    columnHelper.accessor("status", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("status")} />
      ),
      cell: ({ row }) => (
        <InvoiceStatusBadge status={row.getValue("status") as string} />
      ),
    }),
    columnHelper.accessor("createdBy", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("createdBy")} />
      ),
      cell: ({ row }) => (
        <span className="text-muted-foreground">{row.getValue("createdBy") ?? "—"}</span>
      ),
    }),
    columnHelper.accessor("updatedAt", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("date")} />
      ),
      cell: ({ row }) => (
        <span className="text-muted-foreground">
          {formatDateTime(row.getValue("updatedAt"))}
        </span>
      ),
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => {
        const invoice = row.original;
        // Server rule: manage AND owner may write (own documents only);
        // edit/delete only while the API still accepts them (draft).
        const isOwner =
          invoice.createdById !== null && invoice.createdById === currentUserId;
        const canAct = canManage && isOwner;
        const canEdit = canAct && invoice.status === "draft";
        const canRecord = canAct && invoice.status === "draft";
        const canCancel = canAct && ["draft", "recorded"].includes(invoice.status);
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onViewRequest(invoice)}>
                  <Eye className="mr-2 size-4" />
                  {tt("view")}
                </DropdownMenuItem>
                {canEdit ? (
                  <DropdownMenuItem onClick={() => onEditRequest(invoice)}>
                    <Pencil className="mr-2 size-4" />
                    {tt("edit")}
                  </DropdownMenuItem>
                ) : null}
                {canRecord ? (
                  <DropdownMenuItem onClick={() => onRecordRequest(invoice)}>
                    <CheckCircle2 className="mr-2 size-4" />
                    {tt("record")}
                  </DropdownMenuItem>
                ) : null}
                {canCancel ? (
                  <DropdownMenuItem onClick={() => onCancelRequest(invoice)}>
                    <Ban className="mr-2 size-4" />
                    {tt("markCancelled")}
                  </DropdownMenuItem>
                ) : null}
                {canEdit ? (
                  <>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                      className="text-destructive"
                      onClick={() => onDeleteRequest(invoice)}
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
  ] as ColumnDef<DataTableFeatures, InvoiceRow>[];
}

export function InvoicesTab() {
  const t = useTranslations("invoices.list");
  const tt = useTranslations("invoices.table");
  const router = useRouter();
  const qc = useQueryClient();
  const meQuery = useMe();
  const permissions = (meQuery.data?.permissions ?? []) as string[];
  const canManage = permissions.includes("invoices.manage");
  const currentUserId = meQuery.data?.id ?? null;
  const [deleteTarget, setDeleteTarget] = useState<InvoiceRow | null>(null);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [page, setPage] = useState(0);
  const [perPage, setPerPage] = useState(20);

  const debouncedSearch = useDebouncedValue(search, 300);

  const deleteMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(await invoicesInvoicesDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["invoices"] });
      setDeleteTarget(null);
      toast.success(t("deleted"));
    },
  });

  const statusMutation = useMutation({
    mutationFn: async ({
      id,
      status,
    }: {
      id: string;
      status: "recorded" | "cancelled";
    }) => unwrap(await invoicesStatus(id, { status }, withAuth())),
    onSuccess: (_result, variables) => {
      qc.invalidateQueries({ queryKey: ["invoices"] });
      toast.success(
        variables.status === "recorded" ? t("recorded") : t("cancelled")
      );
    },
  });

  const query = useQuery({
    queryKey: ["invoices", debouncedSearch, statusFilter, page, perPage],
    queryFn: async (): Promise<InvoicesPage> => {
      const response = await invoicesInvoicesIndex(
        {
          search: debouncedSearch || undefined,
          status:
            statusFilter === "all"
              ? undefined
              : (statusFilter as InvoicesInvoicesIndexStatus),
          page: page + 1,
          per_page: perPage,
        },
        withAuth()
      );
      const envelope = unwrapWithMeta<
        unknown,
        { page: number; perPage: number; total: number }
      >(response);
      const resources = (envelope.data ?? []) as InvoiceResource[];
      return {
        data: resources.map(toRow),
        meta: envelope.meta,
      };
    },
    placeholderData: keepPreviousData,
  });

  const columns = useInvoiceColumns({
    canManage,
    currentUserId,
    onViewRequest: (invoice) => router.push(`/invoices/${invoice.id}`),
    onEditRequest: (invoice) => router.push(`/invoices/${invoice.id}/edit`),
    onRecordRequest: (invoice) =>
      statusMutation.mutate({ id: invoice.id, status: "recorded" }),
    onCancelRequest: (invoice) =>
      statusMutation.mutate({ id: invoice.id, status: "cancelled" }),
    onDeleteRequest: setDeleteTarget,
  });

  if (query.isPending) {
    return <DataTableSkeleton columns={8} actions={2} />;
  }

  const meta = query.data?.meta ?? { page: page + 1, perPage, total: 0 };
  const rows = query.data?.data ?? [];

  return (
    <div className="mt-1 min-w-0">
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
          { label: tt("recorded"), value: "recorded" },
          { label: tt("cancelled"), value: "cancelled" },
        ]}
        filterPlaceholder={tt("allStatuses")}
        searchPlaceholder={tt("searchPlaceholder")}
        toolbar={
          canManage ? (
            <Button type="button" onClick={() => router.push("/invoices/new")}>
              <Plus className="mr-2 size-4" />
              {t("newTitle")}
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

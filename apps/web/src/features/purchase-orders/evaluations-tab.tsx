"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table";
import { MoreHorizontal, Pencil, Plus, Trash2 } from "lucide-react";
import {
  purchaseOrdersEvaluationsDestroy,
  purchaseOrdersEvaluationsIndex,
} from "@/lib/api/evaluation/evaluation";
import type { EvaluationResource } from "@/lib/api/model/evaluationResource";
import type { PurchaseOrdersEvaluationsIndexStatus } from "@/lib/api/model/purchaseOrdersEvaluationsIndexStatus";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
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
import { type DataTableFeatures } from "@/components/ui/data-table-features";

type EvaluationRow = {
  id: string;
  code: string;
  suppliers: string;
  total: number;
  status: string | null;
  updatedAt: string | null;
  createdBy: string | null;
};

type EvaluationsPage = {
  data: EvaluationRow[];
  meta?: { page: number; perPage: number; total: number };
};

const money = new Intl.NumberFormat("en-US", {
  style: "currency",
  currency: "USD",
  minimumFractionDigits: 2,
});

const STATUS_LABEL_KEYS: Record<string, string> = {
  draft: "draft",
  in_review: "inReview",
  approved: "approved",
  rejected: "rejected",
  returned: "returned",
};

const STATUS_VARIANTS: Record<
  string,
  "default" | "secondary" | "destructive" | "outline"
> = {
  draft: "outline",
  in_review: "secondary",
  approved: "default",
  rejected: "destructive",
  returned: "outline",
};

function toRow(resource: EvaluationResource): EvaluationRow {
  return {
    id: resource.id,
    code: resource.code,
    suppliers: (resource.suppliers ?? [])
      .map((supplier) => supplier.name)
      .filter(Boolean)
      .join(", "),
    total: resource.awardedTotal,
    status: resource.status,
    updatedAt: resource.updatedAt,
    createdBy: resource.createdBy ?? null,
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

const columnHelper = createColumnHelper<DataTableFeatures, EvaluationRow>();

function useEvaluationColumns({
  onEditRequest,
  onDeleteRequest,
}: {
  onEditRequest: (evaluation: EvaluationRow) => void;
  onDeleteRequest: (evaluation: EvaluationRow) => void;
}): ColumnDef<DataTableFeatures, EvaluationRow>[] {
  const tc = useTranslations("purchaseOrders.columns");
  const tt = useTranslations("purchaseOrders.table");

  return [
    columnHelper.accessor("code", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("code")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("code")}</span>
      ),
    }),
    columnHelper.accessor("suppliers", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("supplier")} />
      ),
      cell: ({ row }) => {
        const suppliers: string = row.getValue("suppliers");
        return suppliers || "—";
      },
    }),
    columnHelper.accessor("total", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("total")} />
      ),
      cell: ({ row }) => (
        <span className="tabular-nums">
          {money.format(Number(row.getValue("total")))}
        </span>
      ),
    }),
    columnHelper.accessor("status", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("status")} />
      ),
      cell: ({ row }) => {
        const status = row.getValue("status") as string | null;
        if (!status) return <span className="text-muted-foreground">—</span>;

        const key = STATUS_LABEL_KEYS[status];
        return (
          <Badge variant={STATUS_VARIANTS[status] ?? "secondary"}>
            {key ? tt(key) : status}
          </Badge>
        );
      },
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
        <span className="text-muted-foreground">{row.getValue("updatedAt") ?? "—"}</span>
      ),
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => {
        const evaluation = row.original;
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onEditRequest(evaluation)}>
                  <Pencil className="mr-2 size-4" />
                  {tt("edit")}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeleteRequest(evaluation)}
                >
                  <Trash2 className="mr-2 size-4" />
                  {tt("delete")}
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        );
      },
    }),
  ] as ColumnDef<DataTableFeatures, EvaluationRow>[];
}

export function EvaluationsTab() {
  const t = useTranslations("purchaseOrders.evaluations");
  const tt = useTranslations("purchaseOrders.table");
  const router = useRouter();
  const qc = useQueryClient();
  const [deleteTarget, setDeleteTarget] = useState<EvaluationRow | null>(null);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [page, setPage] = useState(0);
  const [perPage, setPerPage] = useState(20);

  const debouncedSearch = useDebouncedValue(search, 300);

  const deleteMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(await purchaseOrdersEvaluationsDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["evaluations"] });
      setDeleteTarget(null);
      toast.success(t("deleted"));
    },
  });

  const columns = useEvaluationColumns({
    onEditRequest: (evaluation) =>
      router.push(`/purchase-orders/evaluations/${evaluation.id}`),
    onDeleteRequest: setDeleteTarget,
  });

  const query = useQuery({
    queryKey: ["evaluations", debouncedSearch, statusFilter, page, perPage],
    queryFn: async (): Promise<EvaluationsPage> => {
      const response = await purchaseOrdersEvaluationsIndex(
        {
          search: debouncedSearch || undefined,
          status:
            statusFilter === "all"
              ? undefined
              : (statusFilter as PurchaseOrdersEvaluationsIndexStatus),
          page: page + 1,
          per_page: perPage,
        },
        withAuth()
      );
      const envelope = unwrapWithMeta<
        unknown,
        { page: number; perPage: number; total: number }
      >(response);
      const resources = (envelope.data ?? []) as EvaluationResource[];
      return {
        data: resources.map(toRow),
        meta: envelope.meta,
      };
    },
    placeholderData: keepPreviousData,
  });

  if (query.isPending) {
    return <DataTableSkeleton columns={6} actions={2} />;
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
          { label: tt("inReview"), value: "in_review" },
          { label: tt("approved"), value: "approved" },
          { label: tt("rejected"), value: "rejected" },
          { label: tt("returned"), value: "returned" },
        ]}
        filterPlaceholder={tt("allStatuses")}
        searchPlaceholder={tt("searchPlaceholder")}
        toolbar={
          <Button
            type="button"
            onClick={() => router.push("/purchase-orders/evaluations/new")}
          >
            <Plus className="mr-2 size-4" />
            {t("newTitle")}
          </Button>
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

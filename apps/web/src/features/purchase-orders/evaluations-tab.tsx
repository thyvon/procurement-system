"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table";
import { MoreHorizontal, Pencil, Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/ui/data-table";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { type DataTableFeatures } from "@/components/ui/data-table-features";
import { EvaluationForm } from "./evaluation-form";

type EvaluationRow = {
  id: string;
  code: string;
  supplier: string;
  total: string;
  status: string;
  updatedAt: string;
};

const columnHelper = createColumnHelper<DataTableFeatures, EvaluationRow>();

function useEvaluationColumns(): ColumnDef<DataTableFeatures, EvaluationRow>[] {
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
    columnHelper.accessor("supplier", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("supplier")} />
      ),
    }),
    columnHelper.accessor("total", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("total")} />
      ),
      cell: ({ row }) => (
        <span className="tabular-nums">{row.getValue("total")}</span>
      ),
    }),
    columnHelper.accessor("status", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("status")} />
      ),
      cell: ({ row }) => (
        <span className="text-muted-foreground">{row.getValue("status")}</span>
      ),
    }),
    columnHelper.accessor("updatedAt", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("date")} />
      ),
      cell: ({ row }) => (
        <span className="text-muted-foreground">{row.getValue("updatedAt")}</span>
      ),
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: () => {
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem>
                  <Pencil className="mr-2 size-4" />
                  {tt("edit")}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem className="text-destructive">
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
  const [creating, setCreating] = useState(false);
  const [statusFilter, setStatusFilter] = useState("all");
  const columns = useEvaluationColumns();

  if (creating) {
    return (
      <div className="min-w-0">
        <EvaluationForm onBack={() => setCreating(false)} />
      </div>
    );
  }

  return (
    <div className="mt-1 min-w-0">
      <DataTable
        columns={columns}
        data={[]}
        searchColumn="code"
        searchPlaceholder={tt("searchPlaceholder")}
        filterColumn="status"
        filterValue={statusFilter}
        onFilterChange={setStatusFilter}
        filterOptions={[
          { label: tt("draft"), value: "draft" },
          { label: tt("approved"), value: "approved" },
        ]}
        filterPlaceholder={tt("allStatuses")}
        toolbar={
          <Button type="button" onClick={() => setCreating(true)}>
            <Plus className="mr-2 size-4" />
            {t("newTitle")}
          </Button>
        }
      />
    </div>
  );
}

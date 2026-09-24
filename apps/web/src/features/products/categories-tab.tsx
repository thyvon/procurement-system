"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useState } from "react"
import { useTranslations } from "next-intl"
import { toast } from "sonner"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { MoreHorizontal, Plus, Pencil, Trash2 } from "lucide-react"
import {
  productsCategoriesDestroy,
  productsCategoriesIndex,
} from "@/lib/api/product-category/product-category"
import { Button } from "@/components/ui/button"
import { DataTable } from "@/components/ui/data-table"
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton"
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { unwrap, withAuth } from "@/lib/api-client"
import { type DataTableFeatures } from "@/components/ui/data-table-features"
import { CategoryDialog, type Category } from "./components/category-dialog"

const columnHelper = createColumnHelper<DataTableFeatures, Category>()

function useCategoryColumns({
  onEditRequest,
  onDeleteRequest,
}: {
  onEditRequest: (category: Category) => void
  onDeleteRequest: (category: Category) => void
}): ColumnDef<DataTableFeatures, Category>[] {
  const tc = useTranslations("products.columns")
  const tt = useTranslations("products.table")
  return [
    columnHelper.accessor("code", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("refCode")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("code")}</span>
      ),
    }),
    columnHelper.accessor("shortCode", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("shortCode")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("shortCode") ?? "—"}</span>
      ),
    }),
    columnHelper.accessor("name", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("name")} />
      ),
    }),
    columnHelper.accessor("nameKm", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("nameKm")} />
      ),
      cell: ({ row }) => row.getValue("nameKm") ?? "—",
    }),
    columnHelper.accessor("sortOrder", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("order")} />
      ),
      cell: ({ row }) => (
        <span className="text-muted-foreground">{row.getValue("sortOrder")}</span>
      ),
    }),
    columnHelper.accessor("isActive", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("status")} />
      ),
      cell: ({ row }) => (
        <span className={row.getValue("isActive") ? "text-green-600" : "text-muted-foreground"}>
          {row.getValue("isActive") ? tt("active") : tt("inactive")}
        </span>
      ),
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => {
        const category = row.original
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onEditRequest(category)}>
                  <Pencil className="mr-2 size-4" />
                  {tt("edit")}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeleteRequest(category)}
                >
                  <Trash2 className="mr-2 size-4" />
                  {tt("delete")}
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        )
      },
    }),
  ] as ColumnDef<DataTableFeatures, Category>[]
}

export function CategoriesTab() {
  const t = useTranslations("products.categories")
  const tt = useTranslations("products.table")
  const qc = useQueryClient()
  const [statusFilter, setStatusFilter] = useState("all")
  const [editing, setEditing] = useState<Category | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Category | null>(null)
  const [formOpen, setFormOpen] = useState(false)

  const query = useQuery({
    queryKey: ["categories"],
    queryFn: async () =>
      unwrap<Category[]>(await productsCategoriesIndex(withAuth())),
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: string) => unwrap(await productsCategoriesDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["categories"] })
      setDeleteTarget(null)
      toast.success(t("deleted"))
    },
  })

  const columns = useCategoryColumns({
    onEditRequest: (cat) => {
      setEditing(cat)
      setFormOpen(true)
    },
    onDeleteRequest: setDeleteTarget,
  })

  if (query.isPending) {
    return <DataTableSkeleton columns={7} actions={1} />
  }

  return (
    <div>
      <DataTable
        columns={columns}
        data={query.data ?? []}
        searchColumn="name"
        searchPlaceholder={tt("searchPlaceholder")}
        filterColumn="isActive"
        filterValue={statusFilter}
        onFilterChange={setStatusFilter}
        filterOptions={[
          { label: tt("active"), value: "true" },
          { label: tt("inactive"), value: "false" },
        ]}
        filterPlaceholder={tt("allStatuses")}
        toolbar={
          <Button
            onClick={() => {
              setEditing(null)
              setFormOpen(true)
            }}
          >
            <Plus className="mr-2 size-4" />
            {t("newTitle")}
          </Button>
        }
      />

      {formOpen && (
        <CategoryDialog
          key={editing?.id ?? "new"}
          open
          onOpenChange={(open) => {
            if (!open) {
              setFormOpen(false)
              setEditing(null)
            }
          }}
          editing={editing}
        />
      )}

      {/* Delete confirmation dialog */}
      <Dialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("deleteTitle")}</DialogTitle>
            <DialogDescription>
              {tt.rich("deleteDescription", {
                name: deleteTarget?.name ?? "",
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
  )
}

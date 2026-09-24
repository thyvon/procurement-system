"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useState } from "react"
import { useTranslations } from "next-intl"
import { toast } from "sonner"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { MoreHorizontal, Plus, Pencil, Trash2 } from "lucide-react"
import {
  productsVariationTemplatesDestroy,
  productsVariationTemplatesIndex,
} from "@/lib/api/variation/variation"
import type { VariationTemplateResource } from "@/lib/api/model/variationTemplateResource"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
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
import { VariationTemplateDialog } from "./components/variation-template-dialog"

const columnHelper = createColumnHelper<DataTableFeatures, VariationTemplateResource>()

function useTemplateColumns({
  onEditRequest,
  onDeleteRequest,
}: {
  onEditRequest: (template: VariationTemplateResource) => void
  onDeleteRequest: (template: VariationTemplateResource) => void
}): ColumnDef<DataTableFeatures, VariationTemplateResource>[] {
  const tc = useTranslations("products.columns")
  const tt = useTranslations("products.table")
  return [
    columnHelper.accessor("name", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("name")} />
      ),
    }),
    columnHelper.display({
      id: "options",
      enableSorting: false,
      enableHiding: false,
      header: () => tc("options"),
      cell: ({ row }) => {
        const options = row.original.options ?? []
        if (options.length === 0) {
          return <Badge variant="outline">0</Badge>
        }
        return (
          <span className="flex flex-wrap items-center gap-1 text-xs">
            <Badge variant="outline">{options.length}</Badge>
            <span className="text-muted-foreground">
              {options.map((o) => o.value).join(", ")}
            </span>
          </span>
        )
      },
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
        const template = row.original
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onEditRequest(template)}>
                  <Pencil className="mr-2 size-4" />
                  {tt("edit")}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeleteRequest(template)}
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
  ] as ColumnDef<DataTableFeatures, VariationTemplateResource>[]
}

export function VariationTemplatesTab() {
  const t = useTranslations("products.variationTemplates")
  const tt = useTranslations("products.table")
  const qc = useQueryClient()
  const [statusFilter, setStatusFilter] = useState("all")
  const [editing, setEditing] = useState<VariationTemplateResource | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<VariationTemplateResource | null>(null)
  const [formOpen, setFormOpen] = useState(false)

  const query = useQuery({
    queryKey: ["variation-templates"],
    queryFn: async () =>
      unwrap<VariationTemplateResource[]>(
        await productsVariationTemplatesIndex(withAuth())
      ),
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(await productsVariationTemplatesDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["variation-templates"] })
      setDeleteTarget(null)
      toast.success(t("deleted"))
    },
  })

  const columns = useTemplateColumns({
    onEditRequest: (template) => {
      setEditing(template)
      setFormOpen(true)
    },
    onDeleteRequest: setDeleteTarget,
  })

  if (query.isPending) {
    return <DataTableSkeleton columns={4} actions={1} />
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
        <VariationTemplateDialog
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

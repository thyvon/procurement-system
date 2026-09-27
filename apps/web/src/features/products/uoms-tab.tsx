"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useState } from "react"
import { useTranslations } from "next-intl"
import { toast } from "sonner"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { MoreHorizontal, Plus, Pencil, Trash2 } from "lucide-react"
import {
  productsUomsDestroy,
  productsUomsIndex,
} from "@/lib/api/uom/uom"
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
import { useMe } from "@/hooks/use-me"
import { UomDialog, type Uom } from "./components/uom-dialog"

const columnHelper = createColumnHelper<DataTableFeatures, Uom>()

function useUomColumns({
  canManage,
  onEditRequest,
  onDeleteRequest,
}: {
  canManage: boolean
  onEditRequest: (uom: Uom) => void
  onDeleteRequest: (uom: Uom) => void
}): ColumnDef<DataTableFeatures, Uom>[] {
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
    columnHelper.accessor("shortName", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("shortName")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("shortName")}</span>
      ),
    }),
    columnHelper.accessor("name", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("name")} />
      ),
    }),
    columnHelper.display({
      id: "subUnits",
      enableSorting: false,
      enableHiding: false,
      header: () => tc("subUnits"),
      cell: ({ row }) => {
        const subUnits = row.original.subUnits ?? []
        if (subUnits.length === 0) {
          return <span className="text-muted-foreground">—</span>
        }
        return (
          <span className="text-xs">
            {subUnits
              .map(
                (s) =>
                  `${s.shortName || s.name}${
                    s.conversionFactor ? ` ×${s.conversionFactor}` : ""
                  }`,
              )
              .join(", ")}
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
        if (!canManage) return null
        const uom = row.original
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onEditRequest(uom)}>
                  <Pencil className="mr-2 size-4" />
                  {tt("edit")}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeleteRequest(uom)}
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
  ] as ColumnDef<DataTableFeatures, Uom>[]
}

export function UomsTab() {
  const t = useTranslations("products.uoms")
  const tt = useTranslations("products.table")
  const qc = useQueryClient()
  const meQuery = useMe()
  const permissions = (meQuery.data?.permissions ?? []) as string[]
  const canManage = permissions.includes("uoms.manage")
  const [statusFilter, setStatusFilter] = useState("all")
  const [editing, setEditing] = useState<Uom | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Uom | null>(null)
  const [formOpen, setFormOpen] = useState(false)

  const query = useQuery({
    queryKey: ["uoms"],
    queryFn: async () =>
      unwrap<Uom[]>(await productsUomsIndex(withAuth())),
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: string) => unwrap(await productsUomsDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["uoms"] })
      setDeleteTarget(null)
      toast.success(t("deleted"))
    },
  })

  const columns = useUomColumns({
    canManage,
    onEditRequest: (uom) => {
      setEditing(uom)
      setFormOpen(true)
    },
    onDeleteRequest: setDeleteTarget,
  })

  if (query.isPending) {
    return <DataTableSkeleton columns={6} actions={1} />
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
          canManage ? (
            <Button
              onClick={() => {
                setEditing(null)
                setFormOpen(true)
              }}
            >
              <Plus className="mr-2 size-4" />
              {t("newTitle")}
            </Button>
          ) : null
        }
      />

      {formOpen && (
        <UomDialog
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

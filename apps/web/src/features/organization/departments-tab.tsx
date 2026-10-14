"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useState } from "react"
import { useTranslations } from "next-intl"
import { toast } from "sonner"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { MoreHorizontal, Plus, Upload, Pencil, Trash2 } from "lucide-react"
import {
  organizationDepartmentsDestroy,
  organizationDepartmentsIndex,
} from "@/lib/api/department/department"
import {
  organizationDivisionsIndex,
} from "@/lib/api/division/division"
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
import { DepartmentDialog, type Department } from "./components/department-dialog"
import { ImportDialog } from "./import-dialog"

const columnHelper = createColumnHelper<DataTableFeatures, Department>()

function useDepartmentColumns({
  canManage,
  divisionsById,
  onEditRequest,
  onDeleteRequest,
}: {
  canManage: boolean
  divisionsById: Map<string, string>
  onEditRequest: (department: Department) => void
  onDeleteRequest: (department: Department) => void
}): ColumnDef<DataTableFeatures, Department>[] {
  const tc = useTranslations("organization.columns")
  const tt = useTranslations("organization.table")
  return [
    columnHelper.accessor("name", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("name")} />
      ),
    }),
    columnHelper.display({
      id: "divisions",
      enableSorting: false,
      enableHiding: false,
      header: () => tc("divisions"),
      cell: ({ row }) => {
        const ids = row.original.divisionIds ?? []
        if (ids.length === 0) {
          return <span className="text-muted-foreground">—</span>
        }
        return (
          <span className="text-xs">
            {ids
              .map((id) => divisionsById.get(id) ?? id)
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
        const department = row.original
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onEditRequest(department)}>
                  <Pencil className="mr-2 size-4" />
                  {tt("edit")}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeleteRequest(department)}
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
  ] as ColumnDef<DataTableFeatures, Department>[]
}

export function DepartmentsTab() {
  const t = useTranslations("organization.departments")
  const tt = useTranslations("organization.table")
  const qc = useQueryClient()
  const meQuery = useMe()
  const permissions = (meQuery.data?.permissions ?? []) as string[]
  const canManage = permissions.includes("departments.manage")
  const [statusFilter, setStatusFilter] = useState("all")
  const [editing, setEditing] = useState<Department | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Department | null>(null)
  const [formOpen, setFormOpen] = useState(false)
  const [importOpen, setImportOpen] = useState(false)

  const query = useQuery({
    queryKey: ["departments"],
    queryFn: async () =>
      unwrap<Department[]>(await organizationDepartmentsIndex(withAuth())),
  })

  const divisionsQuery = useQuery({
    queryKey: ["divisions"],
    queryFn: async () =>
      unwrap<{ id: string; name: string }[]>(
        await organizationDivisionsIndex(withAuth())
      ),
  })

  const divisionsById = new Map(
    (divisionsQuery.data ?? []).map((d) => [d.id, d.name])
  )

  const deleteMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(await organizationDepartmentsDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["departments"] })
      setDeleteTarget(null)
      toast.success(t("deleted"))
    },
  })

  const columns = useDepartmentColumns({
    canManage,
    divisionsById,
    onEditRequest: (department) => {
      setEditing(department)
      setFormOpen(true)
    },
    onDeleteRequest: setDeleteTarget,
  })

  if (query.isPending) {
    return <DataTableSkeleton columns={3} actions={1} />
  }

  return (
    <div className="flex min-h-0 flex-1 flex-col">
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
            <>
              <Button variant="outline" onClick={() => setImportOpen(true)}>
                <Upload className="mr-2 size-4" />
                {t("import")}
              </Button>
              <Button
                onClick={() => {
                  setEditing(null)
                  setFormOpen(true)
                }}
              >
                <Plus className="mr-2 size-4" />
                {t("newTitle")}
              </Button>
            </>
          ) : null
        }
      />

      <ImportDialog kind="department" open={importOpen} onOpenChange={setImportOpen} />

      {formOpen && (
        <DepartmentDialog
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

"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useState } from "react"
import { useTranslations } from "next-intl"
import { toast } from "sonner"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { MoreHorizontal, Plus, Upload, Pencil, Trash2 } from "lucide-react"
import {
  organizationCampusesDestroy,
  organizationCampusesIndex,
} from "@/lib/api/campus/campus"
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
import { CampusDialog, type Campus } from "./components/campus-dialog"
import { ImportDialog } from "./import-dialog"

const columnHelper = createColumnHelper<DataTableFeatures, Campus>()

function useCampusColumns({
  canManage,
  onEditRequest,
  onDeleteRequest,
}: {
  canManage: boolean
  onEditRequest: (campus: Campus) => void
  onDeleteRequest: (campus: Campus) => void
}): ColumnDef<DataTableFeatures, Campus>[] {
  const tc = useTranslations("organization.columns")
  const tt = useTranslations("organization.table")
  return [
    columnHelper.accessor("name", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("name")} />
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
        if (!canManage) return null
        const campus = row.original
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onEditRequest(campus)}>
                  <Pencil className="mr-2 size-4" />
                  {tt("edit")}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeleteRequest(campus)}
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
  ] as ColumnDef<DataTableFeatures, Campus>[]
}

export function CampusesTab() {
  const t = useTranslations("organization.campuses")
  const tt = useTranslations("organization.table")
  const qc = useQueryClient()
  const meQuery = useMe()
  const permissions = (meQuery.data?.permissions ?? []) as string[]
  const canManage = permissions.includes("campuses.manage")
  const [statusFilter, setStatusFilter] = useState("all")
  const [editing, setEditing] = useState<Campus | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Campus | null>(null)
  const [formOpen, setFormOpen] = useState(false)
  const [importOpen, setImportOpen] = useState(false)

  const query = useQuery({
    queryKey: ["campuses"],
    queryFn: async () =>
      unwrap<Campus[]>(await organizationCampusesIndex(withAuth())),
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(await organizationCampusesDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["campuses"] })
      setDeleteTarget(null)
      toast.success(t("deleted"))
    },
  })

  const columns = useCampusColumns({
    canManage,
    onEditRequest: (campus) => {
      setEditing(campus)
      setFormOpen(true)
    },
    onDeleteRequest: setDeleteTarget,
  })

  if (query.isPending) {
    return <DataTableSkeleton columns={2} actions={1} />
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

      <ImportDialog kind="campus" open={importOpen} onOpenChange={setImportOpen} />

      {formOpen && (
        <CampusDialog
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

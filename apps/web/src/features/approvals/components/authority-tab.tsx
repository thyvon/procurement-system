"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useState } from "react"
import { useTranslations } from "next-intl"
import { toast } from "sonner"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { MoreHorizontal, Plus, Pencil, Trash2 } from "lucide-react"
import {
  approvalsTocaEntriesDestroy,
  approvalsTocaEntriesIndex,
} from "@/lib/api/toca-entry/toca-entry"
import { unwrap, withAuth } from "@/lib/api-client"
import { useMe } from "@/hooks/use-me"
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
import { type DataTableFeatures } from "@/components/ui/data-table-features"
import { AuthorityDialog, type TocaEntryRow } from "./authority-dialog"

const money = new Intl.NumberFormat("en-US", {
  style: "currency",
  currency: "USD",
  minimumFractionDigits: 2,
})

const columnHelper = createColumnHelper<DataTableFeatures, TocaEntryRow>()

function useAuthorityColumns({
  canManage,
  onEditRequest,
  onDeleteRequest,
}: {
  canManage: boolean
  onEditRequest: (entry: TocaEntryRow) => void
  onDeleteRequest: (entry: TocaEntryRow) => void
}): ColumnDef<DataTableFeatures, TocaEntryRow>[] {
  const tc = useTranslations("approvals.settings.authority")
  const tt = useTranslations("approvals.settings.table")
  const ts = useTranslations("approvals.subjects")
  return [
    columnHelper.accessor("userName", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("userCol")} />
      ),
    }),
    columnHelper.accessor("subjectType", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("subjectCol")} />
      ),
      cell: ({ row }) => {
        const subject = row.getValue("subjectType")
        return subject === "evaluation" ? ts("evaluation") : subject
      },
    }),
    columnHelper.display({
      id: "band",
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("bandCol")} />
      ),
      cell: ({ row }) => {
        const entry = row.original
        const from = money.format(Number(entry.minAmount))
        const to = entry.maxAmount
          ? money.format(Number(entry.maxAmount))
          : tc("noUpperLimit")
        return (
          <span className="tabular-nums">
            {from} – {to}
          </span>
        )
      },
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => {
        if (!canManage) return null
        const entry = row.original
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onEditRequest(entry)}>
                  <Pencil className="mr-2 size-4" />
                  {tt("edit")}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeleteRequest(entry)}
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
  ] as ColumnDef<DataTableFeatures, TocaEntryRow>[]
}

export function AuthorityTab() {
  const t = useTranslations("approvals.settings.authority")
  const tt = useTranslations("approvals.settings.table")
  const qc = useQueryClient()
  const meQuery = useMe()
  const permissions = (meQuery.data?.permissions ?? []) as string[]
  const canManage = permissions.includes("approvals.manage")
  const [editing, setEditing] = useState<TocaEntryRow | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<TocaEntryRow | null>(null)
  const [formOpen, setFormOpen] = useState(false)

  const query = useQuery({
    queryKey: ["tocaEntries"],
    queryFn: async () =>
      unwrap<TocaEntryRow[]>(await approvalsTocaEntriesIndex(withAuth())),
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(await approvalsTocaEntriesDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["tocaEntries"] })
      setDeleteTarget(null)
      toast.success(t("deleted"))
    },
  })

  const columns = useAuthorityColumns({
    canManage,
    onEditRequest: (entry) => {
      setEditing(entry)
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
        searchColumn="userName"
        searchPlaceholder={tt("searchPlaceholder")}
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
        <AuthorityDialog
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
                name: deleteTarget?.userName ?? "",
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

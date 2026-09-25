"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useState } from "react"
import { useTranslations } from "next-intl"
import { toast } from "sonner"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { MoreHorizontal, Plus, Pencil, Trash2 } from "lucide-react"
import {
  approvalsFlowsDestroy,
  approvalsFlowsIndex,
} from "@/lib/api/approval-flow/approval-flow"
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
import { FlowDialog, type ApprovalFlowRow } from "./flow-dialog"

const money = new Intl.NumberFormat("en-US", {
  style: "currency",
  currency: "USD",
  minimumFractionDigits: 2,
})

const columnHelper = createColumnHelper<DataTableFeatures, ApprovalFlowRow>()

function useFlowColumns({
  canManage,
  onEditRequest,
  onDeleteRequest,
}: {
  canManage: boolean
  onEditRequest: (flow: ApprovalFlowRow) => void
  onDeleteRequest: (flow: ApprovalFlowRow) => void
}): ColumnDef<DataTableFeatures, ApprovalFlowRow>[] {
  const tc = useTranslations("approvals.settings.flows")
  const tt = useTranslations("approvals.settings.table")
  return [
    columnHelper.accessor("code", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("code")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("code")}</span>
      ),
    }),
    columnHelper.accessor("name", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("name")} />
      ),
    }),
    columnHelper.display({
      id: "band",
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("band")} />
      ),
      cell: ({ row }) => {
        const flow = row.original
        const from = money.format(Number(flow.minAmount))
        const to = flow.maxAmount
          ? money.format(Number(flow.maxAmount))
          : tc("noUpperLimit")
        return (
          <span className="tabular-nums">
            {from} – {to}
          </span>
        )
      },
    }),
    columnHelper.display({
      id: "steps",
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("stepsCol")} />
      ),
      cell: ({ row }) => {
        const labels = row.original.steps.map((step) => step.label).join(" → ")
        return (
          <span
            className="block max-w-64 truncate text-sm text-muted-foreground"
            title={labels}
          >
            {labels}
          </span>
        )
      },
    }),
    columnHelper.accessor("isActive", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tt("status")} />
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
        const flow = row.original
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onEditRequest(flow)}>
                  <Pencil className="mr-2 size-4" />
                  {tt("edit")}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeleteRequest(flow)}
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
  ] as ColumnDef<DataTableFeatures, ApprovalFlowRow>[]
}

export function FlowsTab() {
  const t = useTranslations("approvals.settings.flows")
  const tt = useTranslations("approvals.settings.table")
  const qc = useQueryClient()
  const meQuery = useMe()
  const permissions = (meQuery.data?.permissions ?? []) as string[]
  const canManage = permissions.includes("approvals.manage")
  const [statusFilter, setStatusFilter] = useState("all")
  const [editing, setEditing] = useState<ApprovalFlowRow | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<ApprovalFlowRow | null>(null)
  const [formOpen, setFormOpen] = useState(false)

  const query = useQuery({
    queryKey: ["approvalFlows"],
    queryFn: async () =>
      unwrap<ApprovalFlowRow[]>(await approvalsFlowsIndex(withAuth())),
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: string) =>
      unwrap(await approvalsFlowsDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["approvalFlows"] })
      setDeleteTarget(null)
      toast.success(t("deleted"))
    },
  })

  const columns = useFlowColumns({
    canManage,
    onEditRequest: (flow) => {
      setEditing(flow)
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
        <FlowDialog
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

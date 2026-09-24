"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useState } from "react"
import { useTranslations } from "next-intl"
import { toast } from "sonner"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { MoreHorizontal, Plus, Pencil, Trash2 } from "lucide-react"
import {
  rolesRolesDestroy,
  rolesRolesIndex,
  rolesRolesStore,
  rolesRolesUpdate,
} from "@/lib/api/role/role"
import { permissionsIndex } from "@/lib/api/permission/permission"
import { Button } from "@/components/ui/button"
import { DataTable } from "@/components/ui/data-table"
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton"
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header"
import { Checkbox } from "@/components/ui/checkbox"
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
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { RequiredMark } from "@/components/required-mark"
import { unwrap, withAuth } from "@/lib/api-client"
import { type DataTableFeatures } from "@/components/ui/data-table-features"

type RoleRow = {
  id: number
  name: string
  guardName: string
  permissions?: string[]
  createdAt: string | null
}

/** Modules of the permission vocabulary, in matrix display order. */
const PERMISSION_MODULES = [
  "users",
  "roles",
  "entities",
  "products",
  "categories",
  "brands",
  "groups",
  "uoms",
  "variations",
] as const

const columnHelper = createColumnHelper<DataTableFeatures, RoleRow>()

function useRoleColumns({
  onEditRequest,
  onDeleteRequest,
}: {
  onEditRequest: (role: RoleRow) => void
  onDeleteRequest: (role: RoleRow) => void
}): ColumnDef<DataTableFeatures, RoleRow>[] {
  const tc = useTranslations("users.columns")
  const tt = useTranslations("users.table")
  return [
    columnHelper.accessor("name", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("name")} />
      ),
      cell: ({ row }) => (
        <span className="font-medium">{row.getValue("name")}</span>
      ),
    }),
    columnHelper.accessor("guardName", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("guard")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("guardName")}</span>
      ),
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => {
        const role = row.original
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onEditRequest(role)}>
                  <Pencil className="mr-2 size-4" />
                  {tt("edit")}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeleteRequest(role)}
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
  ] as ColumnDef<DataTableFeatures, RoleRow>[]
}

const EMPTY_FORM = {
  name: "",
  permissions: [] as string[],
}

export function RolesTab() {
  const t = useTranslations("users.roles")
  const tp = useTranslations("users.permissions")
  const tt = useTranslations("users.table")
  const qc = useQueryClient()
  const [editing, setEditing] = useState<RoleRow | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<RoleRow | null>(null)
  const [formOpen, setFormOpen] = useState(false)
  const [form, setForm] = useState({ ...EMPTY_FORM })

  const query = useQuery({
    queryKey: ["roles"],
    queryFn: async () =>
      unwrap<RoleRow[]>(await rolesRolesIndex(withAuth())),
  })

  const permissionsQuery = useQuery({
    queryKey: ["permissions"],
    queryFn: async () =>
      unwrap<{ id: number; name: string }[]>(
        await permissionsIndex(withAuth())
      ),
    enabled: formOpen,
  })

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (editing) {
        return unwrap(
          await rolesRolesUpdate(
            editing.id,
            { name: form.name, permissions: form.permissions },
            withAuth()
          )
        )
      }
      return unwrap(
        await rolesRolesStore(
          { name: form.name, permissions: form.permissions },
          withAuth()
        )
      )
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["roles"] })
      qc.invalidateQueries({ queryKey: ["users"] })
      setFormOpen(false)
      toast.success(editing ? t("updated") : t("created"))
      setEditing(null)
      setForm({ ...EMPTY_FORM })
    },
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => unwrap(await rolesRolesDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["roles"] })
      qc.invalidateQueries({ queryKey: ["users"] })
      setDeleteTarget(null)
      toast.success(t("deleted"))
    },
  })

  const togglePermission = (name: string) =>
    setForm((f) => ({
      ...f,
      permissions: f.permissions.includes(name)
        ? f.permissions.filter((p) => p !== name)
        : [...f.permissions, name],
    }))

  const columns = useRoleColumns({
    onEditRequest: (role) => {
      setEditing(role)
      setForm({ name: role.name, permissions: role.permissions ?? [] })
      setFormOpen(true)
    },
    onDeleteRequest: setDeleteTarget,
  })

  if (query.isPending) {
    return <DataTableSkeleton columns={3} actions={1} />
  }

  return (
    <div>
      <DataTable
        columns={columns}
        data={query.data ?? []}
        searchColumn="name"
        searchPlaceholder={tt("searchPlaceholder")}
        toolbar={
          <Button
            onClick={() => {
              setEditing(null)
              setForm({ ...EMPTY_FORM })
              setFormOpen(true)
            }}
          >
            <Plus className="mr-2 size-4" />
            {t("newTitle")}
          </Button>
        }
      />

      {/* Create / Edit dialog */}
      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{editing ? t("editTitle") : t("newTitle")}</DialogTitle>
            <DialogDescription>
              {editing ? t("editDescription") : t("newDescription")}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="flex items-center gap-3">
              <Label htmlFor="role-name" className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">{t("name")} <RequiredMark /></Label>
              <Input
                id="role-name"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                placeholder={t("namePlaceholder")}
                className="flex-1"
              />
            </div>
            <div className="flex items-start gap-3">
              <span className="w-28 shrink-0 pt-1.5 text-left after:ml-1 after:content-[':']">{tp("label")}</span>
              <div className="flex flex-1 flex-col gap-2">
                <div className="grid grid-cols-[1fr_auto_auto] items-center gap-x-5">
                  <span />
                  <span className="text-xs font-medium text-muted-foreground">{tp("view")}</span>
                  <span className="text-xs font-medium text-muted-foreground">{tp("manage")}</span>
                </div>
                {PERMISSION_MODULES.map((mod) => (
                  <div
                    key={mod}
                    className="grid grid-cols-[1fr_auto_auto] items-center gap-x-5"
                  >
                    <span className="text-sm leading-none">
                      {tp(`modules.${mod}`)}
                    </span>
                    <Checkbox
                      checked={form.permissions.includes(`${mod}.view`)}
                      onCheckedChange={() => togglePermission(`${mod}.view`)}
                      aria-label={`${tp(`modules.${mod}`)} — ${tp("view")}`}
                    />
                    <Checkbox
                      checked={form.permissions.includes(`${mod}.manage`)}
                      onCheckedChange={() => togglePermission(`${mod}.manage`)}
                      aria-label={`${tp(`modules.${mod}`)} — ${tp("manage")}`}
                    />
                  </div>
                ))}
                {permissionsQuery.isPending && (
                  <span className="text-xs text-muted-foreground">
                    {tp("loadingPermissions")}
                  </span>
                )}
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setFormOpen(false)}
              disabled={saveMutation.isPending}
            >
              {t("cancel")}
            </Button>
            <Button
              onClick={() => saveMutation.mutate()}
              disabled={saveMutation.isPending || !form.name.trim()}
            >
              {saveMutation.isPending ? t("saving") : t("save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete confirmation dialog */}
      <Dialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("deleteTitle")}</DialogTitle>
            <DialogDescription>
              {t.rich("deleteDescription", {
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

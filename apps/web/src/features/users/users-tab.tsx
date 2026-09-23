"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useState } from "react"
import { useTranslations } from "next-intl"
import { toast } from "sonner"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { MoreHorizontal, Plus, Pencil, UserX } from "lucide-react"
import {
  usersUsersDestroy,
  usersUsersIndex,
  usersUsersStore,
  usersUsersUpdate,
} from "@/lib/api/user/user"
import { rolesRolesIndex } from "@/lib/api/role/role"
import { Button } from "@/components/ui/button"
import { DataTable } from "@/components/ui/data-table"
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton"
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
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
import { Switch } from "@/components/ui/switch"
import { unwrap, withAuth } from "@/lib/api-client"
import { type DataTableFeatures } from "@/components/ui/data-table-features"

type UserRow = {
  id: number
  name: string
  email: string
  avatar: string | null
  entityId: string | null
  isActive: boolean
  roles?: string[]
  createdAt: string | null
}

const columnHelper = createColumnHelper<DataTableFeatures, UserRow>()

const initialsOf = (name: string) =>
  name
    .split(" ")
    .map((part) => part[0])
    .slice(0, 2)
    .join("")
    .toUpperCase()

function useUserColumns({
  onEditRequest,
  onDeactivateRequest,
}: {
  onEditRequest: (user: UserRow) => void
  onDeactivateRequest: (user: UserRow) => void
}): ColumnDef<DataTableFeatures, UserRow>[] {
  const tc = useTranslations("users.columns")
  const tt = useTranslations("users.table")
  return [
    columnHelper.accessor("name", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("name")} />
      ),
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          <Avatar className="size-8">
            {row.original.avatar && (
              <AvatarImage src={row.original.avatar} alt="" />
            )}
            <AvatarFallback className="text-xs">
              {initialsOf(row.original.name)}
            </AvatarFallback>
          </Avatar>
          <span className="font-medium">{row.getValue("name")}</span>
        </div>
      ),
    }),
    columnHelper.accessor("email", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("email")} />
      ),
    }),
    columnHelper.accessor("roles", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("roles")} />
      ),
      enableSorting: false,
      cell: ({ row }) => {
        const roles = (row.original.roles ?? []) as string[]
        if (roles.length === 0) {
          return <span className="text-muted-foreground">—</span>
        }
        return (
          <div className="flex flex-wrap gap-1">
            {roles.map((role) => (
              <Badge key={role} variant="secondary">
                {role}
              </Badge>
            ))}
          </div>
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
        const user = row.original
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onEditRequest(user)}>
                  <Pencil className="mr-2 size-4" />
                  {tt("edit")}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeactivateRequest(user)}
                >
                  <UserX className="mr-2 size-4" />
                  {tt("deactivate")}
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        )
      },
    }),
  ] as ColumnDef<DataTableFeatures, UserRow>[]
}

const EMPTY_FORM = {
  name: "",
  email: "",
  password: "",
  roles: [] as string[],
  isActive: true,
}

export function UsersTab() {
  const t = useTranslations("users.users")
  const tt = useTranslations("users.table")
  const qc = useQueryClient()
  const [statusFilter, setStatusFilter] = useState("all")
  const [editing, setEditing] = useState<UserRow | null>(null)
  const [deactivateTarget, setDeactivateTarget] = useState<UserRow | null>(null)
  const [formOpen, setFormOpen] = useState(false)
  const [form, setForm] = useState({ ...EMPTY_FORM })

  const query = useQuery({
    queryKey: ["users"],
    queryFn: async () =>
      unwrap<UserRow[]>(await usersUsersIndex(withAuth())),
  })

  const rolesQuery = useQuery({
    queryKey: ["roles"],
    queryFn: async () =>
      unwrap<{ id: number; name: string }[]>(await rolesRolesIndex(withAuth())),
    enabled: formOpen,
  })

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (editing) {
        const payload: {
          name: string
          email: string
          password?: string
          is_active: boolean
          roles: string[]
        } = {
          name: form.name,
          email: form.email,
          is_active: form.isActive,
          roles: form.roles,
        }
        if (form.password.trim()) {
          payload.password = form.password
        }
        return unwrap(await usersUsersUpdate(editing.id, payload, withAuth()))
      }
      return unwrap(
        await usersUsersStore(
          {
            name: form.name,
            email: form.email,
            password: form.password,
            is_active: form.isActive,
            roles: form.roles,
          },
          withAuth()
        )
      )
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["users"] })
      setFormOpen(false)
      toast.success(editing ? t("updated") : t("created"))
      setEditing(null)
      setForm({ ...EMPTY_FORM })
    },
  })

  const deactivateMutation = useMutation({
    mutationFn: async (id: number) =>
      unwrap(await usersUsersDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["users"] })
      setDeactivateTarget(null)
      toast.success(t("deactivated"))
    },
  })

  const columns = useUserColumns({
    onEditRequest: (user) => {
      setEditing(user)
      setForm({
        name: user.name,
        email: user.email,
        password: "",
        roles: user.roles ?? [],
        isActive: user.isActive,
      })
      setFormOpen(true)
    },
    onDeactivateRequest: setDeactivateTarget,
  })

  const canSave =
    form.name.trim() !== "" &&
    form.email.trim() !== "" &&
    (editing !== null || form.password.trim() !== "")

  if (query.isPending) {
    return <DataTableSkeleton columns={5} actions={1} />
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
              <Label htmlFor="user-name" className="w-28 shrink-0 text-right after:content-[':']">{t("name")} *</Label>
              <Input
                id="user-name"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                placeholder={t("namePlaceholder")}
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label htmlFor="user-email" className="w-28 shrink-0 text-right after:content-[':']">{t("email")} *</Label>
              <Input
                id="user-email"
                type="email"
                value={form.email}
                onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                placeholder={t("emailPlaceholder")}
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label htmlFor="user-password" className="w-28 shrink-0 text-right after:content-[':']">{t("password")} {!editing && "*"}</Label>
              <Input
                id="user-password"
                type="password"
                value={form.password}
                onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
                placeholder={editing ? t("passwordKeepPlaceholder") : t("passwordCreatePlaceholder")}
                className="flex-1"
              />
            </div>
            <div className="flex items-start gap-3">
              <span className="w-28 shrink-0 pt-1.5 text-right after:content-[':']">{t("roles")}</span>
              <div className="flex flex-1 flex-col gap-2">
                {(rolesQuery.data ?? []).map((role) => (
                  <label
                    key={role.id}
                    className="flex items-center gap-2 text-sm leading-none"
                  >
                    <Checkbox
                      checked={form.roles.includes(role.name)}
                      onCheckedChange={(checked) =>
                        setForm((f) => ({
                          ...f,
                          roles: checked
                            ? [...f.roles, role.name]
                            : f.roles.filter((r) => r !== role.name),
                        }))
                      }
                    />
                    {role.name}
                  </label>
                ))}
                {rolesQuery.isPending && formOpen && (
                  <span className="text-xs text-muted-foreground">
                    {t("loadingRoles")}
                  </span>
                )}
              </div>
            </div>
            <div className="flex items-center gap-3">
              <Label htmlFor="user-active" className="w-28 shrink-0 text-right after:content-[':']">{t("active")}</Label>
              <Switch
                id="user-active"
                checked={form.isActive}
                onCheckedChange={(checked) => setForm((f) => ({ ...f, isActive: checked }))}
              />
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
              disabled={saveMutation.isPending || !canSave}
            >
              {saveMutation.isPending ? t("saving") : t("save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Deactivate confirmation dialog */}
      <Dialog open={!!deactivateTarget} onOpenChange={(open) => !open && setDeactivateTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("deactivateTitle")}</DialogTitle>
            <DialogDescription>
              {t.rich("deactivateDescription", {
                name: deactivateTarget?.name ?? "",
                strong: (chunks) => <strong>{chunks}</strong>,
              })}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDeactivateTarget(null)}
              disabled={deactivateMutation.isPending}
            >
              {tt("cancel")}
            </Button>
            <Button
              variant="destructive"
              onClick={() => deactivateTarget && deactivateMutation.mutate(deactivateTarget.id)}
              disabled={deactivateMutation.isPending}
            >
              {deactivateMutation.isPending ? tt("deleting") : tt("confirmDeactivate")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

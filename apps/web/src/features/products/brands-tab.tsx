"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useState } from "react"
import { useTranslations } from "next-intl"
import { toast } from "sonner"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { MoreHorizontal, Plus, Pencil, Trash2 } from "lucide-react"
import {
  productsBrandsDestroy,
  productsBrandsIndex,
  productsBrandsStore,
  productsBrandsUpdate,
} from "@/lib/api/brand/brand"
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
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { unwrap, withAuth } from "@/lib/api-client"
import { type DataTableFeatures } from "@/components/ui/data-table-features"

type Brand = {
  id: string
  code: string
  name: string
  description: string | null
  isActive: boolean
}

const columnHelper = createColumnHelper<DataTableFeatures, Brand>()

function useBrandColumns({
  onEditRequest,
  onDeleteRequest,
}: {
  onEditRequest: (brand: Brand) => void
  onDeleteRequest: (brand: Brand) => void
}): ColumnDef<DataTableFeatures, Brand>[] {
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
    columnHelper.accessor("name", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("name")} />
      ),
    }),
    columnHelper.accessor("description", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("description")} />
      ),
      cell: ({ row }) => row.getValue("description") ?? "—",
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
        const brand = row.original
        return (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onEditRequest(brand)}>
                  <Pencil className="mr-2 size-4" />
                  {tt("edit")}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeleteRequest(brand)}
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
  ] as ColumnDef<DataTableFeatures, Brand>[]
}

const EMPTY_FORM = {
  name: "",
  description: "",
  isActive: true,
}

export function BrandsTab() {
  const t = useTranslations("products.brands")
  const tt = useTranslations("products.table")
  const qc = useQueryClient()
  const [statusFilter, setStatusFilter] = useState("all")
  const [editing, setEditing] = useState<Brand | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Brand | null>(null)
  const [formOpen, setFormOpen] = useState(false)
  const [form, setForm] = useState({ ...EMPTY_FORM })

  const query = useQuery({
    queryKey: ["brands"],
    queryFn: async () =>
      unwrap<Brand[]>(await productsBrandsIndex(withAuth())),
  })

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload: {
        name: string
        description?: string | null
        is_active?: boolean
      } = {
        name: form.name,
        description: form.description || null,
        is_active: form.isActive,
      }
      if (editing) {
        return unwrap(await productsBrandsUpdate(editing.id, payload, withAuth()))
      }
      return unwrap(await productsBrandsStore(payload, withAuth()))
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["brands"] })
      setFormOpen(false)
      toast.success(editing ? t("updated") : t("created"))
      setEditing(null)
      setForm({ ...EMPTY_FORM })
    },
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: string) => unwrap(await productsBrandsDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["brands"] })
      setDeleteTarget(null)
      toast.success(t("deleted"))
    },
  })

  const columns = useBrandColumns({
    onEditRequest: (brand) => {
      setEditing(brand)
      setForm({
        name: brand.name,
        description: brand.description ?? "",
        isActive: brand.isActive,
      })
      setFormOpen(true)
    },
    onDeleteRequest: setDeleteTarget,
  })

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
              <Label htmlFor="brand-name" className="w-28 shrink-0 text-right after:content-[':']">{t("name")} *</Label>
              <Input
                id="brand-name"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                placeholder={t("namePlaceholder")}
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label htmlFor="brand-description" className="w-28 shrink-0 text-right after:content-[':']">{t("description")}</Label>
              <Input
                id="brand-description"
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                placeholder={t("descriptionPlaceholder")}
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label htmlFor="brand-active" className="w-28 shrink-0 text-right after:content-[':']">{t("active")}</Label>
              <Switch
                id="brand-active"
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

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
  productsCategoriesStore,
  productsCategoriesUpdate,
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
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { unwrap, withAuth } from "@/lib/api-client"
import { type DataTableFeatures } from "@/components/ui/data-table-features"

type Category = {
  id: string
  parentId: string | null
  code: string
  shortCode: string | null
  name: string
  nameKm: string | null
  sortOrder: number
  isActive: boolean
}

const columnHelper = createColumnHelper<DataTableFeatures, Category>()

function useCategoryColumns({
  onEditRequest,
  onDeleteRequest,
}: {
  onEditRequest: (category: Category) => void
  onDeleteRequest: (category: Category) => void
}): ColumnDef<DataTableFeatures, Category>[] {
  return [
    columnHelper.accessor("code", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title="Ref. Code" />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("code")}</span>
      ),
    }),
    columnHelper.accessor("shortCode", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title="Short Code" />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("shortCode") ?? "—"}</span>
      ),
    }),
    columnHelper.accessor("name", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title="Name" />
      ),
    }),
    columnHelper.accessor("nameKm", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title="Name (KH)" />
      ),
      cell: ({ row }) => row.getValue("nameKm") ?? "—",
    }),
    columnHelper.accessor("sortOrder", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title="Order" />
      ),
      cell: ({ row }) => (
        <span className="text-muted-foreground">{row.getValue("sortOrder")}</span>
      ),
    }),
    columnHelper.accessor("isActive", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title="Status" />
      ),
      cell: ({ row }) => (
        <span className={row.getValue("isActive") ? "text-green-600" : "text-muted-foreground"}>
          {row.getValue("isActive") ? "Active" : "Inactive"}
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
              <span className="sr-only">Open menu</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onEditRequest(category)}>
                  <Pencil className="mr-2 size-4" />
                  Edit
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="text-destructive"
                  onClick={() => onDeleteRequest(category)}
                >
                  <Trash2 className="mr-2 size-4" />
                  Delete
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        )
      },
    }),
  ] as ColumnDef<DataTableFeatures, Category>[]
}

const EMPTY_FORM = {
  name: "",
  shortCode: "",
  nameKm: "",
  sortOrder: 0,
  isActive: true,
  parentId: null as string | null,
}

export function CategoriesTab() {
  const t = useTranslations("products.categories")
  const qc = useQueryClient()
  const [statusFilter, setStatusFilter] = useState("all")
  const [editing, setEditing] = useState<Category | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Category | null>(null)
  const [formOpen, setFormOpen] = useState(false)
  const [form, setForm] = useState({ ...EMPTY_FORM })

  const query = useQuery({
    queryKey: ["categories"],
    queryFn: async () =>
      unwrap<Category[]>(await productsCategoriesIndex(withAuth())),
  })

  // While editing, hide the category itself and its descendants as parent
  // options — re-parenting onto a child would form a cycle.
  const excludedParents = (() => {
    if (!editing) return new Set<string>()
    const childrenOf = new Map<string | null, string[]>()
    for (const cat of query.data ?? []) {
      childrenOf.set(cat.parentId, [...(childrenOf.get(cat.parentId) ?? []), cat.id])
    }
    const excluded = new Set([editing.id])
    const stack = [editing.id]
    while (stack.length > 0) {
      for (const childId of childrenOf.get(stack.pop()!) ?? []) {
        if (!excluded.has(childId)) {
          excluded.add(childId)
          stack.push(childId)
        }
      }
    }
    return excluded
  })()

  const parentOptions = (query.data ?? []).filter((c) => !excludedParents.has(c.id))

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload: {
        name: string
        parent_id?: string | null
        short_code?: string
        name_km?: string
        sort_order?: number
        is_active?: boolean
      } = {
        name: form.name,
        parent_id: form.parentId,
        name_km: form.nameKm || undefined,
        sort_order: form.sortOrder,
        is_active: form.isActive,
      }
      if (form.shortCode.trim()) {
        payload.short_code = form.shortCode.trim()
      }
      if (editing) {
        return unwrap(await productsCategoriesUpdate(editing.id, payload, withAuth()))
      }
      return unwrap(await productsCategoriesStore(payload, withAuth()))
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["categories"] })
      setFormOpen(false)
      toast.success(editing ? t("updated") : t("created"))
      setEditing(null)
      setForm({ ...EMPTY_FORM })
    },
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: string) => unwrap(await productsCategoriesDestroy(id, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["categories"] })
      setDeleteTarget(null)
      toast.success("Category deleted.")
    },
  })

  const columns = useCategoryColumns({
    onEditRequest: (cat) => {
      setEditing(cat)
      setForm({
        name: cat.name,
        shortCode: cat.shortCode ?? "",
        nameKm: cat.nameKm ?? "",
        sortOrder: cat.sortOrder,
        isActive: cat.isActive,
        parentId: cat.parentId,
      })
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
        searchPlaceholder="Filter by name..."
        filterColumn="isActive"
        filterValue={statusFilter}
        onFilterChange={setStatusFilter}
        filterOptions={[
          { label: "Active", value: "true" },
          { label: "Inactive", value: "false" },
        ]}
        filterPlaceholder="All statuses"
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
              <Label htmlFor="cat-name" className="w-28 shrink-0 text-right after:content-[':']">{t("name")} *</Label>
              <Input
                id="cat-name"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                placeholder={t("namePlaceholder")}
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label htmlFor="cat-name-km" className="w-28 shrink-0 text-right after:content-[':']">{t("nameKm")}</Label>
              <Input
                id="cat-name-km"
                value={form.nameKm}
                onChange={(e) => setForm((f) => ({ ...f, nameKm: e.target.value }))}
                placeholder={t("nameKmPlaceholder")}
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label className="w-28 shrink-0 text-right after:content-[':']">{t("parent")}</Label>
              <Select
                value={form.parentId}
                onValueChange={(v) => setForm((f) => ({ ...f, parentId: v ?? null }))}
                items={[
                  { value: null, label: t("rootOption") },
                  ...parentOptions.map((c) => ({ value: c.id, label: `${c.code} — ${c.name}` })),
                ]}
              >
                <SelectTrigger className="min-w-0 flex-1">
                  <SelectValue className="min-w-0" placeholder={t("rootOption")} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={null}>{t("rootOption")}</SelectItem>
                  {parentOptions.map((c) => (
                    <SelectItem key={c.id} value={c.id}>
                      {c.code} — {c.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex items-center gap-3">
              <Label htmlFor="cat-short-code" className="w-28 shrink-0 text-right after:content-[':']">{t("shortCode")}</Label>
              <Input
                id="cat-short-code"
                value={form.shortCode}
                onChange={(e) => setForm((f) => ({ ...f, shortCode: e.target.value.toUpperCase() }))}
                placeholder={t("shortCodePlaceholder")}
                className="flex-1 font-mono"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label htmlFor="cat-sort" className="w-28 shrink-0 text-right after:content-[':']">{t("sortOrder")}</Label>
              <Input
                id="cat-sort"
                type="number"
                min={0}
                value={form.sortOrder}
                onChange={(e) => setForm((f) => ({ ...f, sortOrder: Number(e.target.value) }))}
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label htmlFor="cat-active" className="w-28 shrink-0 text-right after:content-[':']">{t("active")}</Label>
              <Switch
                id="cat-active"
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
            <DialogTitle>Delete category</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete <strong>{deleteTarget?.name}</strong>? This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDeleteTarget(null)}
              disabled={deleteMutation.isPending}
            >
              Cancel
            </Button>
            <Button
              variant="destructive"
              onClick={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
              disabled={deleteMutation.isPending}
            >
              {deleteMutation.isPending ? "Deleting..." : "Delete"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

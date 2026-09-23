"use client"

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { useState } from "react"
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
import { unwrap, withAuth } from "./api"
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

export function CategoriesTab() {
  const qc = useQueryClient()
  const [statusFilter, setStatusFilter] = useState("all")
  const [editing, setEditing] = useState<Category | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Category | null>(null)
  const [formOpen, setFormOpen] = useState(false)
  const [form, setForm] = useState({ name: "", shortCode: "", nameKm: "", sortOrder: 0, isActive: true })

  const query = useQuery({
    queryKey: ["categories"],
    queryFn: async () =>
      unwrap<Category[]>(await productsCategoriesIndex(withAuth())),
  })

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload: {
        name: string
        short_code?: string
        name_km?: string
        sort_order?: number
        is_active?: boolean
      } = {
        name: form.name,
        name_km: form.nameKm || undefined,
        sort_order: form.sortOrder,
        is_active: form.isActive,
      }
      if (form.shortCode.trim()) {
        payload.short_code = form.shortCode.trim()
      }
      if (editing) {
        return productsCategoriesUpdate(editing.id, payload, withAuth())
      }
      return productsCategoriesStore(payload, withAuth())
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["categories"] })
      setFormOpen(false)
      setEditing(null)
      setForm({ name: "", shortCode: "", nameKm: "", sortOrder: 0, isActive: true })
      toast.success(editing ? "Category updated." : "Category created.")
    },
    onError: (err) => {
      toast.error(err.message || "Failed to save category.")
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => productsCategoriesDestroy(id, withAuth()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["categories"] })
      setDeleteTarget(null)
      toast.success("Category deleted.")
    },
    onError: (err) => {
      toast.error(err.message || "Failed to delete category.")
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
      })
      setFormOpen(true)
    },
    onDeleteRequest: setDeleteTarget,
  })

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
              setForm({ name: "", shortCode: "", nameKm: "", sortOrder: 0, isActive: true })
              setFormOpen(true)
            }}
          >
            <Plus className="mr-2 size-4" />
            New category
          </Button>
        }
      />

      {/* Create / Edit dialog */}
      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{editing ? "Edit" : "New"} category</DialogTitle>
            <DialogDescription>
              {editing ? "Update the category details." : "Add a new product category."}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="flex items-center gap-3">
              <Label htmlFor="cat-name" className="w-28 shrink-0 text-right after:content-[':']">Name *</Label>
              <Input
                id="cat-name"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                placeholder="Category name"
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label htmlFor="cat-name-km" className="w-28 shrink-0 text-right after:content-[':']">Name (KH)</Label>
              <Input
                id="cat-name-km"
                value={form.nameKm}
                onChange={(e) => setForm((f) => ({ ...f, nameKm: e.target.value }))}
                placeholder="Khmer name (optional)"
                className="flex-1"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label htmlFor="cat-short-code" className="w-28 shrink-0 text-right after:content-[':']">Short Code</Label>
              <Input
                id="cat-short-code"
                value={form.shortCode}
                onChange={(e) => setForm((f) => ({ ...f, shortCode: e.target.value.toUpperCase() }))}
                placeholder="e.g. CAT-01"
                className="flex-1 font-mono"
              />
            </div>
            <div className="flex items-center gap-3">
              <Label htmlFor="cat-sort" className="w-28 shrink-0 text-right after:content-[':']">Sort Order</Label>
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
              <Label htmlFor="cat-active" className="w-28 shrink-0 text-right after:content-[':']">Active</Label>
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
              Cancel
            </Button>
            <Button
              onClick={() => saveMutation.mutate()}
              disabled={saveMutation.isPending || !form.name.trim()}
            >
              {saveMutation.isPending ? "Saving..." : "Save"}
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
